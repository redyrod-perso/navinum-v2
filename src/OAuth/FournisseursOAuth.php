<?php

namespace App\OAuth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fournisseurs OAuth des visiteurs (#4). Chacun est **actif** dès que ses identifiants sont renseignés dans
 * l'environnement (`.env` du serveur, jamais dans le dépôt) : OAUTH_GOOGLE_ID / OAUTH_GOOGLE_SECRET,
 * OAUTH_MICROSOFT_ID / …_SECRET, OAUTH_FACEBOOK_ID / …_SECRET. Adresse de redirection à déclarer chez le
 * fournisseur : `https://<site>/api/visiteur/oauth/<nom>/retour`.
 *
 * `emailVerifie` : champ du profil qui garantit l'adresse (null : jamais garantie, donc jamais de liaison à un
 * compte existant par l'adresse).
 */
final class FournisseursOAuth
{
    public const REGLAGES = [
        'google' => [
            'libelle' => 'Google',
            'autorisation' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'jeton' => 'https://oauth2.googleapis.com/token',
            'profil' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'portee' => 'openid email profile',
            'sujet' => 'sub',
            'emailVerifie' => 'email_verified',
        ],
        'microsoft' => [
            'libelle' => 'Microsoft',
            'autorisation' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'jeton' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'profil' => 'https://graph.microsoft.com/oidc/userinfo',
            'portee' => 'openid email profile',
            'sujet' => 'sub',
            'emailVerifie' => null,
        ],
        'facebook' => [
            'libelle' => 'Facebook',
            'autorisation' => 'https://www.facebook.com/v19.0/dialog/oauth',
            'jeton' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'profil' => 'https://graph.facebook.com/me?fields=id,name,email',
            'portee' => 'email public_profile',
            'sujet' => 'id',
            'emailVerifie' => null,
        ],
    ];

    /** @var array<string, FournisseurOAuth> */
    private array $actifs = [];

    public function __construct(
        HttpClientInterface $http,
        #[Autowire('%env(default::OAUTH_GOOGLE_ID)%')] ?string $googleId,
        #[Autowire('%env(default::OAUTH_GOOGLE_SECRET)%')] ?string $googleSecret,
        #[Autowire('%env(default::OAUTH_MICROSOFT_ID)%')] ?string $microsoftId,
        #[Autowire('%env(default::OAUTH_MICROSOFT_SECRET)%')] ?string $microsoftSecret,
        #[Autowire('%env(default::OAUTH_FACEBOOK_ID)%')] ?string $facebookId,
        #[Autowire('%env(default::OAUTH_FACEBOOK_SECRET)%')] ?string $facebookSecret,
    ) {
        $identifiants = [
            'google' => [$googleId, $googleSecret],
            'microsoft' => [$microsoftId, $microsoftSecret],
            'facebook' => [$facebookId, $facebookSecret],
        ];
        foreach ($identifiants as $nom => [$id, $secret]) {
            if ('' !== (string) $id && '' !== (string) $secret) {
                $this->actifs[$nom] = new FournisseurOAuth($nom, self::REGLAGES[$nom], (string) $id, (string) $secret, $http);
            }
        }
    }

    /** @return list<FournisseurOAuth> */
    public function actifs(): array
    {
        return array_values($this->actifs);
    }

    public function get(string $nom): ?FournisseurOAuth
    {
        return $this->actifs[$nom] ?? null;
    }
}
