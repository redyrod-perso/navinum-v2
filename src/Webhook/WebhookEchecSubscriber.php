<?php

namespace App\Webhook;

use App\Entity\WebhookEnvoi;
use App\Message\EnvoyerWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

/** Dernier essai d'un webhook échoué (plus de réessai prévu) : l'envoi passe en « échec » dans le journal. */
#[AsEventListener]
final class WebhookEchecSubscriber
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof EnvoyerWebhook || $event->willRetry()) {
            return;
        }
        $envoi = $this->em->getRepository(WebhookEnvoi::class)->find(Uuid::fromString($message->envoiId));
        if (null !== $envoi) {
            $envoi->echec();
            $this->em->flush();
        }
    }
}
