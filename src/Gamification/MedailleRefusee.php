<?php

namespace App\Gamification;

/** Médaille demandée par un interactif mais refusée ; `code` est un code d'erreur stable de l'API. */
final class MedailleRefusee extends \RuntimeException
{
    public function __construct(public readonly string $codeMetier, string $message)
    {
        parent::__construct($message);
    }
}
