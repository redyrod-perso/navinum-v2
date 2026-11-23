<?php

namespace App\Service;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\LogVisite;
use App\Controller\Api\VisiteurController;
use App\Entity\ParcoursInteractif;
use App\Entity\Visiteur;
use App\Entity\VisiteurAppareil;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Lancement des interactifs : création des jetons, contexte injecté, enregistrement des événements.
 */
final class LaunchService
{
    /** Nombre maximum d'événements conservés dans LogVisite::resultats. */
    private const MAX_EVENTS = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requests,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * URL publique du hub Mercure. Le hub est intégré à FrankenPHP, donc sur le même domaine que le site :
     * une URL relative est rendue absolue pour les interactifs hébergés ailleurs.
     */
    private function hubPublicUrl(): string
    {
        $url = $this->hub->getPublicUrl();
        $request = $this->requests->getCurrentRequest();
        if (str_starts_with($url, '/') && null !== $request) {
            return $request->getSchemeAndHttpHost().$url;
        }

        return $url;
    }

    /** Topic Mercure des événements d'un parcours (fil en direct de l'admin). */
    public static function parcoursEventsTopic(string $parcoursId): string
    {
        return "/navinum/parcours/$parcoursId/events";
    }

    /** Topic Mercure de la configuration d'un interactif placé (poussée aux interactifs ouverts). */
    public static function configTopic(string $placementId): string
    {
        return "/navinum/parcours-interactif/$placementId/config";
    }

    /** Publie la nouvelle configuration effective d'un placement aux interactifs en cours. */
    public function publishConfig(ParcoursInteractif $placement): void
    {
        $this->publish(self::configTopic((string) $placement->getId()), ['config' => (object) $placement->getEffectiveConfig()]);
    }

    public function createToken(Interactif $interactif, ?ParcoursInteractif $placement = null, ?Visiteur $visiteur = null): LaunchToken
    {
        $token = (new LaunchToken($interactif, $placement))->setVisiteur($visiteur);
        $exposition = $placement?->getParcours()->getExpositions()->first();
        if ($exposition) {
            $token->setExposition($exposition);
        }
        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    /**
     * URL d'ouverture de l'interactif avec son jeton (`?nv=`).
     * Null si l'interactif n'a pas d'URL (`url_fichier_interactif`).
     */
    public function launchUrl(LaunchToken $token): ?string
    {
        $url = $token->getInteractif()->getUrlFichierInteractif();
        if (null === $url || '' === trim($url)) {
            return null;
        }
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'nv='.$token->getToken();
    }

    /** Contexte renvoyé par GET /api/launch/{token}. */
    public function context(LaunchToken $token): array
    {
        $interactif = $token->getInteractif();
        $parcours = $token->getParcoursInteractif()?->getParcours();
        $exposition = $token->getExposition();
        $visiteur = $token->getVisiteur();

        return [
            'interactif' => [
                'id' => (string) $interactif->getId(),
                'libelle' => $interactif->getLibelle(),
                'version' => $interactif->getVersion(),
            ],
            'parcours' => $parcours ? ['id' => (string) $parcours->getId(), 'libelle' => $parcours->getLibelle()] : null,
            'exposition' => $exposition ? ['id' => (string) $exposition->getId(), 'libelle' => $exposition->getLibelle()] : null,
            'config' => (object) $token->getConfig(),
            'visiteur' => $visiteur ? VisiteurController::profile($visiteur) : null,
            'expiresAt' => $token->getExpiresAt()->format(\DATE_ATOM),
            // Temps réel (hub Mercure intégré à FrankenPHP) : la config peut être mise à jour à chaud
            'mercure' => $token->getParcoursInteractif() ? [
                'hub' => $this->hubPublicUrl(),
                'topics' => ['config' => self::configTopic((string) $token->getParcoursInteractif()->getId())],
            ] : null,
        ];
    }

    /**
     * Rattache un visiteur à un lancement (et au LogVisite déjà commencé, le cas échéant).
     *
     * Le même lancement peut servir à plusieurs joueurs successifs ; le nouveau joueur le reprend
     * (ses événements partent dans un nouveau LogVisite) :
     * - toujours en **mode borne** (`modeBorne` dans la configuration du placement, fixée par l'admin) : un
     *   joueur parti sans se déconnecter ne doit ni bloquer la borne ni recevoir les gains des suivants ;
     * - sinon, dès que le précédent n'est plus actif (déconnecté, ou 30 min sans activité).
     *
     * @return bool false si le lancement appartient à un autre visiteur encore actif (hors mode borne)
     */
    public function identify(LaunchToken $token, Visiteur $visiteur): bool
    {
        $current = $token->getVisiteur();
        if (null !== $current && !$current->getId()->equals($visiteur->getId())) {
            $borne = true === ($token->getConfig()['modeBorne'] ?? false);
            if (!$borne && $this->isActive($current)) {
                return false;
            }
            $token->setLogVisite(null); // la partie du joueur précédent reste à son nom
        }
        $token->setVisiteur($visiteur);
        if (null !== ($log = $token->getLogVisite()) && null === $log->getVisiteur()) {
            $log->setVisiteur($visiteur);
        }
        $this->em->flush();

        return true;
    }

    /** Le visiteur a-t-il une session en cours (appareil non révoqué, actif depuis moins de 30 min) ? */
    private function isActive(Visiteur $visiteur): bool
    {
        $depuis = $this->clock->now()->modify(\sprintf('-%d seconds', VisiteurToken::INACTIVITE));

        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(VisiteurAppareil::class, 'a')
            ->where('a.visiteur = :visiteur AND a.revokedAt IS NULL AND a.lastSeenAt > :depuis')
            ->setParameter('visiteur', $visiteur->getId(), 'uuid')
            ->setParameter('depuis', $depuis)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Consigne un événement de l'interactif dans le LogVisite associé au jeton (créé au premier événement).
     * - `payload.score` (entier) met à jour le score ;
     * - un type finissant par `.completed` ou `.ended` clôt la visite (end_at).
     */
    public function recordEvent(LaunchToken $token, string $type, array $payload): LogVisite
    {
        $log = $token->getLogVisite();
        if (null === $log) {
            $log = (new LogVisite())
                ->setInteractif($token->getInteractif())
                ->setInteractifLibelle($token->getInteractif()->getLibelle())
                ->setVisiteur($token->getVisiteur())
                ->setExposition($token->getExposition())
                ->setConnection('launch:'.substr($token->getToken(), 0, 16))
                ->setStartAt(new \DateTime());
            $this->em->persist($log);
            $token->setLogVisite($log);
        }

        $events = json_decode($log->getResultats() ?? '[]', true);
        $events = \is_array($events) ? $events : [];
        $events[] = ['type' => $type, 'payload' => $payload, 'at' => (new \DateTimeImmutable())->format(\DATE_ATOM)];
        $log->setResultats(json_encode(\array_slice($events, -self::MAX_EVENTS), \JSON_UNESCAPED_UNICODE));

        if (isset($payload['score']) && \is_int($payload['score'])) {
            $log->setScore($payload['score']);
        }
        if (str_ends_with($type, '.completed') || str_ends_with($type, '.ended')) {
            $log->setEndAt(new \DateTime());
        }

        $this->em->flush();

        if ($placement = $token->getParcoursInteractif()) {
            // Fil en direct de l'admin : pas d'identité visiteur, seulement l'événement
            $this->publish(self::parcoursEventsTopic((string) $placement->getParcours()->getId()), [
                'interactif' => $token->getInteractif()->getLibelle(),
                'placement' => (string) $placement->getId(),
                'type' => $type,
                'score' => $log->getScore(),
                'session' => substr($token->getToken(), 0, 8),
                'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ]);
        }

        return $log;
    }

    /** Publication Mercure « best effort » : une indisponibilité du hub ne bloque jamais l'interactif. */
    private function publish(string $topic, array $data): void
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($data, \JSON_UNESCAPED_UNICODE)));
        } catch (\Throwable $e) {
            $this->logger->warning('Publication Mercure impossible', ['topic' => $topic, 'error' => $e->getMessage()]);
        }
    }
}
