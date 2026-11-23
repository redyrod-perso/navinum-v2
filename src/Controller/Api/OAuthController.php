<?php

namespace App\Controller\Api;

use App\Entity\Visiteur;
use App\OAuth\ConnexionOAuth;
use App\OAuth\EtatOAuth;
use App\OAuth\FournisseursOAuth;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Connexion des visiteurs par un fournisseur OAuth (Google, Microsoft, Facebook… — #4). Délivre le même jeton
 * visiteur que les autres modes ; un invité connecté devient le compte (mêmes XP et médailles).
 *
 * 1. GET  /api/visiteur/oauth/fournisseurs           → fournisseurs actifs `[{nom, libelle}]`
 * 2. POST /api/visiteur/oauth/{nom}/demarrer {retour} (Bearer d'un invité facultatif) → `{url}` du fournisseur
 * 3. GET  /api/visiteur/oauth/{nom}/retour?code&state  (appelée par le fournisseur) → redirection vers
 *    `retour#navinum-oauth=<code à usage unique>` (ou `#navinum-oauth-erreur=<CODE>`)
 * 4. POST /api/visiteur/oauth/echange {code}           → `{visiteur, token, expiresAt, appareil}` (une seule fois, 2 min)
 *
 * Sécurité : `state` signé et limité à 10 min, adresse de retour sur le même site uniquement, jeton jamais
 * dans une URL (seulement un code d'échange à usage unique).
 */
#[Route('/api/visiteur/oauth', name: 'api_visiteur_oauth_')]
final class OAuthController extends AbstractController
{
    private const ECHANGE_DUREE = 120;

    public function __construct(
        private readonly FournisseursOAuth $fournisseurs,
        private readonly EtatOAuth $etats,
        private readonly ConnexionOAuth $connexions,
        private readonly VisiteurToken $tokens,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'cache.app')] private readonly CacheItemPoolInterface $cache,
        #[Autowire(service: 'limiter.inscription_visiteur')] private readonly RateLimiterFactory $limiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/fournisseurs', name: 'fournisseurs', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        return $this->json(['fournisseurs' => array_map(
            static fn ($f) => ['nom' => $f->nom, 'libelle' => $f->libelle()],
            $this->fournisseurs->actifs(),
        )]);
    }

    #[Route('/{nom}/demarrer', name: 'demarrer', requirements: ['nom' => '[a-z]+'], methods: ['POST'])]
    public function demarrer(string $nom, Request $request): JsonResponse
    {
        $fournisseur = $this->fournisseurs->get($nom);
        if (null === $fournisseur) {
            return new JsonResponse(['error' => \sprintf('La connexion « %s » n’est pas disponible.', $nom), 'code' => 'MODE_INDISPONIBLE'], 404);
        }
        if (!$this->limiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'Trop de tentatives : réessayez dans quelques minutes.', 'code' => 'TROP_DE_TENTATIVES'], 429);
        }
        $data = json_decode($request->getContent(), true);
        $retour = self::retour($request, \is_array($data) ? ($data['retour'] ?? null) : null);
        if (null === $retour) {
            return new JsonResponse(['error' => 'Adresse de retour invalide (même site uniquement).', 'code' => 'VALIDATION'], 400);
        }
        $session = $this->tokens->session(VisiteurToken::bearer($request));
        $etat = $this->etats->signer([
            'f' => $nom,
            'r' => $retour,
            'v' => null !== $session ? $session['appareil']->getVisiteur()->getId()->toRfc4122() : null,
            'm' => false !== (\is_array($data) ? ($data['memoriser'] ?? true) : true), // borne : rien conservé sur l'appareil
        ]);

        return $this->json(['url' => $fournisseur->urlAutorisation($etat, $this->redirection($nom))]);
    }

    #[Route('/{nom}/retour', name: 'retour', requirements: ['nom' => '[a-z]+'], methods: ['GET'])]
    public function retourFournisseur(string $nom, Request $request): Response
    {
        $etat = $this->etats->lire((string) $request->query->get('state', ''));
        if (null === $etat || ($etat['f'] ?? null) !== $nom || null === ($fournisseur = $this->fournisseurs->get($nom))) {
            return new JsonResponse(['error' => 'Connexion expirée ou invalide : recommencez.', 'code' => 'NON_IDENTIFIE'], 400);
        }
        $retour = (string) $etat['r'];
        if ($request->query->has('error') || !$request->query->has('code')) {
            return new RedirectResponse($retour.'#navinum-oauth-erreur=CONNEXION_ANNULEE');
        }

        try {
            $identite = $fournisseur->identite((string) $request->query->get('code'), $this->redirection($nom));
        } catch (\Throwable $e) {
            $this->logger->warning('Connexion OAuth impossible', ['fournisseur' => $nom, 'erreur' => $e->getMessage()]);

            return new RedirectResponse($retour.'#navinum-oauth-erreur=SERVEUR');
        }
        $invite = null;
        if (\is_string($etat['v'] ?? null)) {
            $invite = $this->em->getRepository(Visiteur::class)->find(Uuid::fromString($etat['v']));
        }
        $visiteur = $this->connexions->visiteur($nom, $identite, $invite);
        if (!$visiteur->isActive()) {
            return new RedirectResponse($retour.'#navinum-oauth-erreur=NON_AUTORISE');
        }

        [$appareil, $secret] = $this->tokens->creerAppareil($visiteur);
        $this->em->flush();
        $session = $this->tokens->issue($appareil);
        $this->em->flush();

        $code = bin2hex(random_bytes(24));
        $item = $this->cache->getItem('oauth_echange_'.$code);
        $this->cache->save($item->set([
            'visiteur' => VisiteurController::profile($visiteur),
            'mode' => 'oauth',
            'fournisseur' => $nom,
            'memoriser' => (bool) ($etat['m'] ?? true),
            'appareil' => $secret,
        ] + $session)->expiresAfter(self::ECHANGE_DUREE));

        return new RedirectResponse($retour.'#navinum-oauth='.$code);
    }

    #[Route('/echange', name: 'echange', methods: ['POST'])]
    public function echange(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $code = \is_array($data) && \is_string($data['code'] ?? null) && preg_match('/^[0-9a-f]{48}$/', $data['code']) ? $data['code'] : null;
        $item = null !== $code ? $this->cache->getItem('oauth_echange_'.$code) : null;
        if (null === $item || !$item->isHit()) {
            return new JsonResponse(['error' => 'Connexion expirée : recommencez.', 'code' => 'NON_IDENTIFIE'], 401);
        }
        $this->cache->deleteItem('oauth_echange_'.$code); // usage unique

        return $this->json($item->get());
    }

    /** Adresse de retour autorisée : chemin relatif ou adresse absolue du même site. */
    private static function retour(Request $request, mixed $retour): ?string
    {
        if (!\is_string($retour) || '' === $retour || \strlen($retour) > 2048) {
            return null;
        }
        $retour = strtok($retour, '#');
        if (str_starts_with($retour, '/') && !str_starts_with($retour, '//')) {
            return $request->getSchemeAndHttpHost().$retour;
        }
        $parts = parse_url($retour);

        return \is_array($parts) && \in_array($parts['scheme'] ?? '', ['http', 'https'], true) && ($parts['host'] ?? '') === $request->getHost()
            ? $retour : null;
    }

    private function redirection(string $nom): string
    {
        return $this->generateUrl('api_visiteur_oauth_retour', ['nom' => $nom], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
