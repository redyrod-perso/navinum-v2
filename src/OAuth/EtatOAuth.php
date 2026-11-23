<?php

namespace App\OAuth;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paramètre `state` d'OAuth : données signées (HMAC avec APP_SECRET) et valables 10 minutes — protège contre
 * la falsification du retour et transporte l'adresse de retour et l'invité éventuel.
 */
final class EtatOAuth
{
    public const DUREE = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    public function signer(array $donnees): string
    {
        $charge = self::b64(json_encode($donnees + ['exp' => $this->clock->now()->getTimestamp() + self::DUREE, 'n' => bin2hex(random_bytes(8))]));

        return $charge.'.'.self::b64(hash_hmac('sha256', 'oauth:'.$charge, $this->secret, true));
    }

    /** Données de l'état, ou null s'il est falsifié ou expiré. */
    public function lire(string $etat): ?array
    {
        $parts = explode('.', $etat);
        if (2 !== \count($parts) || !hash_equals(self::b64(hash_hmac('sha256', 'oauth:'.$parts[0], $this->secret, true)), $parts[1])) {
            return null;
        }
        $donnees = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        return \is_array($donnees) && ($donnees['exp'] ?? 0) > $this->clock->now()->getTimestamp() ? $donnees : null;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
