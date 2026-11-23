<?php

namespace App\Service;

/** Création ou modification de compte refusée ; `codeMetier` est un code d'erreur stable de l'API. */
final class CompteRefuse extends \RuntimeException
{
    public function __construct(public readonly string $codeMetier, string $message)
    {
        parent::__construct($message);
    }
}
