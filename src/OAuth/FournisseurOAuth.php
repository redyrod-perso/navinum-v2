<?php

namespace App\OAuth;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fournisseur OAuth 2 / OpenID Connect (code d'autorisation), configuré par un préréglage de
 * FournisseursOAuth : adresses d'autorisation, de jeton et de profil, champs du profil.
 */
final class FournisseurOAuth
{
    /**
     * @param array{libelle: string, autorisation: string, jeton: string, profil: string, portee: string,
     *              sujet: string, emailVerifie: ?string} $reglage
     */
    public function __construct(
        public readonly string $nom,
        private readonly array $reglage,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly HttpClientInterface $http,
    ) {
    }

    public function libelle(): string
    {
        return $this->reglage['libelle'];
    }

    public function urlAutorisation(string $etat, string $redirection): string
    {
        return $this->reglage['autorisation'].'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirection,
            'response_type' => 'code',
            'scope' => $this->reglage['portee'],
            'state' => $etat,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Échange le code d'autorisation contre un jeton, puis lit le profil.
     *
     * @throws \RuntimeException si le fournisseur refuse ou répond mal
     */
    public function identite(string $code, string $redirection): IdentiteOAuth
    {
        $jeton = $this->http->request('POST', $this->reglage['jeton'], ['body' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirection,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]])->toArray(false);
        if (!\is_string($jeton['access_token'] ?? null)) {
            throw new \RuntimeException(\sprintf('%s : jeton refusé (%s)', $this->nom, (string) ($jeton['error'] ?? 'réponse invalide')));
        }

        $profil = $this->http->request('GET', $this->reglage['profil'], ['auth_bearer' => $jeton['access_token']])->toArray(false);
        $sujet = $profil[$this->reglage['sujet']] ?? null;
        if (!\is_string($sujet) && !\is_int($sujet)) {
            throw new \RuntimeException(\sprintf('%s : profil sans identifiant', $this->nom));
        }
        $email = \is_string($profil['email'] ?? null) ? mb_strtolower(trim($profil['email'])) : null;
        $champVerifie = $this->reglage['emailVerifie'];

        return new IdentiteOAuth(
            (string) $sujet,
            $email,
            null !== $champVerifie && null !== $email && true === filter_var($profil[$champVerifie] ?? false, \FILTER_VALIDATE_BOOL),
            \is_string($profil['name'] ?? null) ? $profil['name'] : null,
        );
    }
}
