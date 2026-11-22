<?php

namespace App\Service;

use App\Entity\Visiteur;
use App\Repository\VisiteurRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton d'identification d'un visiteur côté interactifs : `nvv1.<uuid>.<signature>`.
 *
 * Signé avec APP_SECRET (HMAC-SHA256), sans stockage. C'est le format que le futur module
 * d'authentification JS (en ligne ou par badge RFID) délivrera aussi : les interactifs n'auront pas à changer.
 * Transmis dans l'en-tête `Authorization: Bearer <jeton>`.
 *
 * Limite du prototype : pas d'expiration ni de révocation (à ajouter avec le vrai module d'authentification).
 */
final class VisiteurToken
{
    private const PREFIX = 'nvv1';

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly VisiteurRepository $visiteurs,
    ) {
    }

    public function issue(Visiteur $visiteur): string
    {
        $id = $visiteur->getId()->toRfc4122();

        return self::PREFIX.'.'.$id.'.'.$this->sign($id);
    }

    /** Visiteur correspondant au jeton, ou null si le jeton est absent, invalide ou si le visiteur est désactivé. */
    public function resolve(?string $token): ?Visiteur
    {
        if (null === $token) {
            return null;
        }
        $parts = explode('.', $token);
        if (3 !== \count($parts) || self::PREFIX !== $parts[0] || !Uuid::isValid($parts[1])) {
            return null;
        }
        if (!hash_equals($this->sign($parts[1]), $parts[2])) {
            return null;
        }
        $visiteur = $this->visiteurs->find(Uuid::fromString($parts[1]));

        return $visiteur?->isActive() ? $visiteur : null;
    }

    /** Lit le jeton dans `Authorization: Bearer …`. */
    public function fromRequest(Request $request): ?Visiteur
    {
        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? $this->resolve(substr($header, 7)) : null;
    }

    private function sign(string $id): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'visiteur:'.$id, $this->secret, true)), '+/', '-_'), '=');
    }
}
