<?php

namespace App\Webhook;

use App\Entity\Visiteur;
use App\Entity\Webhook;
use App\Entity\WebhookEnvoi;
use App\Message\EnvoyerWebhook;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publication des événements métier vers les webhooks abonnés (#10). L'envoi est **asynchrone** (worker
 * Messenger) : un webhook lent ou en panne ne ralentit jamais le visiteur. Jamais bloquant.
 *
 * Format envoyé (POST JSON) : `{ id, evenement, date, donnees }` ; en-têtes `X-Navinum-Evenement`,
 * `X-Navinum-Horodatage` (secondes) et `X-Navinum-Signature: sha256=<hex>` = HMAC-SHA256 du texte
 * `<horodatage>.<corps>` avec le secret du webhook (voir signer()).
 */
final class Webhooks
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publier(string $evenement, array $donnees): void
    {
        try {
            $abonnes = array_filter(
                $this->em->getRepository(Webhook::class)->findBy(['actif' => true]),
                static fn (Webhook $w) => $w->ecoute($evenement),
            );
            foreach ($abonnes as $webhook) {
                $envoi = new WebhookEnvoi($webhook, $evenement, $donnees);
                $this->em->persist($envoi);
                $this->em->flush();
                $this->bus->dispatch(new EnvoyerWebhook($envoi->getId()->toRfc4122()));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Publication de webhook impossible', ['evenement' => $evenement, 'erreur' => $e->getMessage()]);
        }
    }

    /** Envoi de test depuis l'admin (événement `test.ping`). */
    public function tester(Webhook $webhook): void
    {
        $envoi = new WebhookEnvoi($webhook, 'test.ping', ['message' => 'Test depuis l’admin Navinum']);
        $this->em->persist($envoi);
        $this->em->flush();
        $this->bus->dispatch(new EnvoyerWebhook($envoi->getId()->toRfc4122()));
    }

    /** Signature d'un envoi : `sha256=` + HMAC-SHA256 hexadécimal de `<horodatage>.<corps>`. */
    public static function signer(string $secret, int $horodatage, string $corps): string
    {
        return 'sha256='.hash_hmac('sha256', $horodatage.'.'.$corps, $secret);
    }

    /** Données d'un visiteur dans un webhook (destinataires de confiance, configurés par l'admin). */
    public static function visiteur(?Visiteur $visiteur): ?array
    {
        return null === $visiteur ? null : [
            'id' => $visiteur->getId()->toRfc4122(),
            'pseudo' => VisiteurToken::pseudo($visiteur),
            'compte' => !(bool) $visiteur->isAnonyme(),
            'email' => $visiteur->getEmail(),
            'emailVerifie' => null !== $visiteur->getEmailVerifieAt(),
            // Consentement explicite : à respecter par le destinataire pour tout envoi non indispensable
            'newsletter' => true === $visiteur->getHasNewsletter(),
        ];
    }
}
