<?php

namespace App\Controller\Api;

use App\Entity\Visiteur;
use App\Service\CompteRefuse;
use App\Service\CompteVisiteur;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Identification des visiteurs par les interactifs (librairie : `api.auth`).
 *
 * - POST /api/visiteur/invite      {pseudo}            → visiteur invité + jeton (30 min) + secret d'appareil
 * - POST /api/visiteur/reprendre   {pseudo, appareil}  → nouveau jeton pour le même visiteur (même appareil)
 *                                  {pseudo, ancienJeton} → échange d'un ancien jeton `nvv1` (+ secret d'appareil)
 * - POST /api/visiteur/renouveler  (Bearer)            → jeton prolongé de 30 min (12 h au plus)
 * - GET  /api/visiteur/moi         (Bearer)            → profil
 * - POST /api/visiteur/deconnexion (Bearer)            → révoque cet appareil
 * - POST /api/visiteur/inscription {email, motDePasse, pseudo?, newsletter?} (Bearer d'un invité facultatif : il
 *                                  devient un compte, mêmes XP et médailles) → profil + jeton (+ appareil)
 * - POST /api/visiteur/connexion   {email, motDePasse}  → profil + jeton + appareil
 *
 * Refus : 401 `NON_IDENTIFIE` (jeton expiré, révoqué ou invalide ; secret d'appareil ou pseudo incorrect),
 * 401 `IDENTIFIANTS_INVALIDES`, 409 `EMAIL_DEJA_UTILISE`, 429 `TROP_DE_TENTATIVES`, 400 `VALIDATION`.
 * Le jeton s'utilise avec POST /api/launch/{jeton}/visiteur pour relier une partie au visiteur.
 */
#[Route('/api/visiteur', name: 'api_visiteur_')]
final class VisiteurController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VisiteurToken $tokens,
        private readonly CompteVisiteur $comptes,
        #[Autowire(service: 'limiter.connexion_visiteur')] private readonly RateLimiterFactory $connexionLimiter,
        #[Autowire(service: 'limiter.inscription_visiteur')] private readonly RateLimiterFactory $inscriptionLimiter,
    ) {
    }

    #[Route('/inscription', name: 'inscription', methods: ['POST'])]
    public function inscription(Request $request): JsonResponse
    {
        if (!$this->inscriptionLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return self::tropDeTentatives();
        }
        $data = json_decode($request->getContent(), true);
        $data = \is_array($data) ? $data : [];

        // Invité de cet appareil (Bearer) : il devient un compte et garde son identité
        $session = $this->tokens->session(VisiteurToken::bearer($request));
        $invite = null !== $session ? $session['appareil']->getVisiteur() : null;
        try {
            $visiteur = $this->comptes->inscrire(
                (string) ($data['email'] ?? ''),
                (string) ($data['motDePasse'] ?? ''),
                isset($data['pseudo']) ? (string) $data['pseudo'] : null,
                true === ($data['newsletter'] ?? false),
                $invite,
            );
        } catch (CompteRefuse $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->codeMetier], 'EMAIL_DEJA_UTILISE' === $e->codeMetier ? 409 : 400);
        }

        if (null !== $session && $session['appareil']->getVisiteur() === $visiteur) {
            // Même appareil, même visiteur : nouvelle session, le client garde son secret d'appareil
            $reponse = $this->tokens->issue($session['appareil']);
        } else {
            [$appareil, $secret] = $this->tokens->creerAppareil($visiteur);
            $this->em->flush();
            $reponse = $this->tokens->issue($appareil) + ['appareil' => $secret];
        }
        $this->em->flush();

        return $this->json(['visiteur' => self::profile($visiteur), ...$reponse], 201);
    }

    #[Route('/connexion', name: 'connexion', methods: ['POST'])]
    public function connexion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = \is_array($data) ? mb_strtolower(trim((string) ($data['email'] ?? ''))) : '';
        $limite = $this->connexionLimiter->create($request->getClientIp().'|'.$email);
        if (0 === $limite->consume(0)->getRemainingTokens()) {
            return self::tropDeTentatives();
        }

        $visiteur = $this->comptes->authentifier($email, \is_array($data) ? (string) ($data['motDePasse'] ?? '') : '');
        if (null === $visiteur) {
            $limite->consume(); // seuls les échecs comptent
            // Message générique : ne révèle pas si l'adresse a un compte
            return new JsonResponse(['error' => 'Adresse e-mail ou mot de passe incorrect.', 'code' => 'IDENTIFIANTS_INVALIDES'], 401);
        }
        $limite->reset();

        [$appareil, $secret] = $this->tokens->creerAppareil($visiteur);
        $this->em->flush();
        $reponse = $this->tokens->issue($appareil);
        $this->em->flush();

        return $this->json(['visiteur' => self::profile($visiteur), ...$reponse, 'appareil' => $secret]);
    }

    private static function tropDeTentatives(): JsonResponse
    {
        return new JsonResponse(['error' => 'Trop de tentatives : réessayez dans quelques minutes.', 'code' => 'TROP_DE_TENTATIVES'], 429);
    }

    #[Route('/invite', name: 'invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $pseudo = self::pseudo($request);
        if (null === $pseudo) {
            return self::invalidPseudo();
        }

        $visiteur = (new Visiteur())
            ->setPseudoSon($pseudo)
            ->setIsAnonyme(true)
            ->setType('invite');
        $this->em->persist($visiteur);
        $this->em->flush(); // identifiant du visiteur nécessaire à l'appareil
        [$appareil, $secret] = $this->tokens->creerAppareil($visiteur);
        $this->em->flush();
        $session = $this->tokens->issue($appareil);
        $this->em->flush();

        return $this->json(['visiteur' => self::profile($visiteur), ...$session, 'appareil' => $secret], 201);
    }

    #[Route('/reprendre', name: 'reprendre', methods: ['POST'])]
    public function reprendre(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $pseudo = self::pseudo($request);
        if (null === $pseudo || !\is_array($data)) {
            return self::invalidPseudo();
        }

        $secret = null;
        if (\is_string($data['appareil'] ?? null)) {
            $appareil = $this->tokens->appareil($data['appareil'], $pseudo);
        } elseif (\is_string($data['ancienJeton'] ?? null) && null !== ($visiteur = $this->tokens->visiteurAncienJeton($data['ancienJeton'], $pseudo))) {
            [$appareil, $secret] = $this->tokens->creerAppareil($visiteur);
            $this->em->flush();
        } else {
            $appareil = null;
        }
        if (null === $appareil) {
            return self::nonIdentifie('Visiteur non reconnu sur cet appareil : identifiez-vous à nouveau.');
        }

        $session = $this->tokens->issue($appareil);
        $this->em->flush();

        return $this->json(['visiteur' => self::profile($appareil->getVisiteur()), ...$session] + (null !== $secret ? ['appareil' => $secret] : []));
    }

    #[Route('/renouveler', name: 'renouveler', methods: ['POST'])]
    public function renouveler(Request $request): JsonResponse
    {
        $session = $this->tokens->renouveler(VisiteurToken::bearer($request));
        if (null === $session) {
            return self::nonIdentifie('Session expirée : identifiez-vous à nouveau.');
        }
        $this->em->flush();

        return $this->json($session);
    }

    #[Route('/moi', name: 'moi', methods: ['GET'])]
    public function moi(Request $request): JsonResponse
    {
        $visiteur = $this->tokens->fromRequest($request);
        if (null === $visiteur) {
            return self::nonIdentifie('Jeton visiteur absent ou invalide');
        }

        return $this->json(['visiteur' => self::profile($visiteur)]);
    }

    #[Route('/deconnexion', name: 'deconnexion', methods: ['POST'])]
    public function deconnexion(Request $request): Response
    {
        $session = $this->tokens->session(VisiteurToken::bearer($request));
        if (null !== $session) {
            $this->tokens->revoquer($session['appareil']);
            $this->em->flush();
        }

        return new Response(null, 204); // idempotent : déjà déconnecté = succès
    }

    /** Données publiques d'un visiteur : jamais d'email ni d'informations personnelles. */
    public static function profile(Visiteur $visiteur): array
    {
        return [
            'id' => (string) $visiteur->getId(),
            'pseudo' => VisiteurToken::pseudo($visiteur),
            'anonyme' => (bool) $visiteur->isAnonyme(),
        ];
    }

    private static function pseudo(Request $request): ?string
    {
        $data = json_decode($request->getContent(), true);
        $pseudo = \is_array($data) && \is_string($data['pseudo'] ?? null) ? trim($data['pseudo']) : '';

        return mb_strlen($pseudo) >= 2 && mb_strlen($pseudo) <= 40 ? $pseudo : null;
    }

    private static function invalidPseudo(): JsonResponse
    {
        return new JsonResponse(['error' => 'Le pseudo doit faire entre 2 et 40 caractères', 'code' => 'VALIDATION'], 400);
    }

    private static function nonIdentifie(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message, 'code' => 'NON_IDENTIFIE'], 401);
    }
}
