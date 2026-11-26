<?php

namespace App\Webhook;

use App\Entity\WebhookEnvoi;
use App\Message\EnvoyerWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoie un webhook signé. Une réponse hors 2xx ou une erreur réseau lève une exception : Messenger réessaie
 * (stratégie du transport `async`) ; après le dernier essai, WebhookEchecSubscriber marque l'envoi en échec.
 */
#[AsMessageHandler]
final class EnvoyerWebhookHandler
{
    public const TIMEOUT = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(EnvoyerWebhook $message): void
    {
        $envoi = $this->em->getRepository(WebhookEnvoi::class)->find(Uuid::fromString($message->envoiId));
        if (null === $envoi || !$envoi->getWebhook()->isActif() || WebhookEnvoi::SUCCES === $envoi->getStatut()) {
            return;
        }
        $corps = json_encode([
            'id' => $message->envoiId,
            'evenement' => $envoi->getEvenement(),
            'date' => $envoi->getCreatedAt()->format(\DATE_ATOM),
            'donnees' => $envoi->getDonnees(),
        ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $horodatage = $this->clock->now()->getTimestamp();

        try {
            $code = $this->http->request('POST', $envoi->getWebhook()->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Navinum-Webhooks/1.0',
                    'X-Navinum-Evenement' => $envoi->getEvenement(),
                    'X-Navinum-Horodatage' => (string) $horodatage,
                    'X-Navinum-Signature' => Webhooks::signer($envoi->getWebhook()->getSecret(), $horodatage, $corps),
                ],
                'body' => $corps,
                'timeout' => self::TIMEOUT,
                'max_redirects' => 0,
            ])->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $envoi->tentative(null, $e->getMessage());
            $this->em->flush();
            throw new \RuntimeException('Webhook injoignable : '.$e->getMessage(), 0, $e);
        }

        if ($code >= 200 && $code < 300) {
            $envoi->tentative($code, null);
            $this->em->flush();

            return;
        }
        $envoi->tentative($code, 'Réponse HTTP '.$code);
        $this->em->flush();
        throw new \RuntimeException(\sprintf('Webhook refusé (HTTP %d)', $code));
    }
}
