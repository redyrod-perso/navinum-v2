<?php

namespace App\Message;

/** Envoi asynchrone d'un webhook (#10) : traité par le worker Messenger (transport `async`), avec réessais. */
final readonly class EnvoyerWebhook
{
    public function __construct(public string $envoiId)
    {
    }
}
