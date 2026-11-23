<?php

namespace App\OAuth;

/** Identité renvoyée par un fournisseur OAuth après connexion. */
final readonly class IdentiteOAuth
{
    public function __construct(
        /** Identifiant stable du compte chez le fournisseur (`sub`). */
        public string $sujet,
        public ?string $email,
        /** Le fournisseur garantit que l'adresse appartient à ce compte (seulement alors, liaison par e-mail). */
        public bool $emailVerifie,
        public ?string $nom,
    ) {
    }
}
