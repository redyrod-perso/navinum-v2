<?php

namespace App\Service;

use App\Entity\Visiteur;
use App\Entity\VisiteurAppareil;
use App\Repository\VisiteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Jetons d'identification des visiteurs côté interactifs (`Authorization: Bearer <jeton>`).
 *
 * Jeton d'accès `nvv2.<données>.<signature>` (HMAC-SHA256 avec APP_SECRET), sans stockage :
 * - **30 minutes d'inactivité** : le jeton expire 30 min après sa délivrance ; tant qu'il est valide, il se
 *   renouvelle (`POST /api/visiteur/renouveler`), ce que fait la librairie à chaque activité ;
 * - **12 heures au plus** depuis l'ouverture de la session, même en activité continue ;
 * - **révocable** : il est rattaché à un appareil (VisiteurAppareil) ; un appareil révoqué invalide
 *   immédiatement ses jetons (« Déconnecter partout »).
 *
 * Après expiration, le visiteur retrouve son profil sur le même appareil en redonnant son pseudo et le
 * secret de l'appareil (`POST /api/visiteur/reprendre`), conservé par la librairie.
 *
 * Changement de APP_SECRET : renseigner l'ancien dans VISITEUR_SECRET_PRECEDENT pendant au moins 30 min
 * (les jetons en cours restent valides) ; les secrets d'appareil ne dépendent pas de APP_SECRET.
 */
final class VisiteurToken
{
    private const PREFIX = 'nvv2';
    private const LEGACY_PREFIX = 'nvv1';
    private const APPAREIL_PREFIX = 'nva1';

    /** Durée de vie d'un jeton d'accès sans activité. */
    public const INACTIVITE = 30 * 60;
    /** Durée maximale d'une session, même en activité continue. */
    public const DUREE_MAX = 12 * 3600;

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        #[Autowire('%env(default::VISITEUR_SECRET_PRECEDENT)%')] private readonly ?string $previousSecret,
        private readonly VisiteurRepository $visiteurs,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Enregistre un nouvel appareil pour le visiteur.
     *
     * @return array{0: VisiteurAppareil, 1: string} l'appareil et son secret (à remettre une seule fois au client)
     */
    public function creerAppareil(Visiteur $visiteur): array
    {
        $secret = self::APPAREIL_PREFIX.'.'.bin2hex(random_bytes(32));
        $appareil = new VisiteurAppareil($visiteur, hash('sha256', $secret), $this->now());
        $this->em->persist($appareil);

        return [$appareil, $secret];
    }

    /**
     * Délivre un jeton d'accès pour l'appareil (nouvelle session, ou renouvellement d'une session ouverte à `$debut`).
     *
     * @return array{token: string, expiresAt: string}
     */
    public function issue(VisiteurAppareil $appareil, ?int $debut = null): array
    {
        $now = $this->now()->getTimestamp();
        $debut ??= $now;
        $exp = min($now + self::INACTIVITE, $debut + self::DUREE_MAX);
        $payload = self::b64(json_encode([
            'v' => $appareil->getVisiteur()->getId()->toRfc4122(),
            'a' => $appareil->getId()->toRfc4122(),
            'st' => $debut,
            'exp' => $exp,
        ]));
        $appareil->touch($this->now());

        return [
            'token' => self::PREFIX.'.'.$payload.'.'.$this->sign($payload, $this->secret),
            'expiresAt' => (new \DateTimeImmutable('@'.$exp))->format(\DATE_ATOM),
        ];
    }

    /**
     * Session correspondant à un jeton d'accès valide (signature, expiration, appareil non révoqué, visiteur actif).
     *
     * @return array{appareil: VisiteurAppareil, debut: int, exp: int}|null
     */
    public function session(?string $token): ?array
    {
        $parts = explode('.', (string) $token);
        if (3 !== \count($parts) || self::PREFIX !== $parts[0] || !$this->validSignature($parts[1], $parts[2])) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        if (!\is_array($data) || !\is_int($data['exp'] ?? null) || !\is_int($data['st'] ?? null) || !Uuid::isValid((string) ($data['a'] ?? ''))) {
            return null;
        }
        if ($data['exp'] <= $this->now()->getTimestamp()) {
            return null;
        }
        $appareil = $this->em->getRepository(VisiteurAppareil::class)->find(Uuid::fromString($data['a']));
        if (null === $appareil || $appareil->isRevoked() || !$appareil->getVisiteur()->isActive()) {
            return null;
        }

        return ['appareil' => $appareil, 'debut' => $data['st'], 'exp' => $data['exp']];
    }

    /** Visiteur d'un jeton d'accès valide, ou null. */
    public function resolve(?string $token): ?Visiteur
    {
        $session = $this->session($token);

        return null === $session ? null : $session['appareil']->getVisiteur();
    }

    /** Lit le jeton dans `Authorization: Bearer …`. */
    public function fromRequest(Request $request): ?Visiteur
    {
        return $this->resolve(self::bearer($request));
    }

    public static function bearer(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }

    /**
     * Renouvelle un jeton encore valide (activité) ; null s'il a expiré (inactivité), si la session dépasse
     * 12 h ou si l'appareil est révoqué.
     *
     * @return array{token: string, expiresAt: string}|null
     */
    public function renouveler(?string $token): ?array
    {
        $session = $this->session($token);
        if (null === $session || $session['debut'] + self::DUREE_MAX <= $this->now()->getTimestamp()) {
            return null;
        }

        return $this->issue($session['appareil'], $session['debut']);
    }

    /** Appareil (non révoqué) d'un visiteur actif dont le secret et le pseudo correspondent. */
    public function appareil(string $secret, string $pseudo): ?VisiteurAppareil
    {
        if (!str_starts_with($secret, self::APPAREIL_PREFIX.'.')) {
            return null;
        }
        $appareil = $this->em->getRepository(VisiteurAppareil::class)->findOneBy(['secretHash' => hash('sha256', $secret)]);
        if (null === $appareil || $appareil->isRevoked() || !$appareil->getVisiteur()->isActive()) {
            return null;
        }

        return self::pseudo($appareil->getVisiteur()) === $pseudo ? $appareil : null;
    }

    /**
     * Visiteur d'un ancien jeton `nvv1` (prototype, sans expiration) : sert uniquement à l'échanger contre un
     * appareil lors de la reprise, pour que les visiteurs existants gardent leur profil.
     */
    public function visiteurAncienJeton(string $token, string $pseudo): ?Visiteur
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts) || self::LEGACY_PREFIX !== $parts[0] || !Uuid::isValid($parts[1])) {
            return null;
        }
        $valid = false;
        foreach (array_filter([$this->secret, $this->previousSecret]) as $secret) {
            $valid = $valid || hash_equals($this->sign('visiteur:'.$parts[1], $secret), $parts[2]);
        }
        $visiteur = $valid ? $this->visiteurs->find(Uuid::fromString($parts[1])) : null;

        return null !== $visiteur && $visiteur->isActive() && self::pseudo($visiteur) === $pseudo ? $visiteur : null;
    }

    /** Révoque l'appareil d'un jeton (déconnexion depuis cet appareil). */
    public function revoquer(VisiteurAppareil $appareil): void
    {
        $appareil->revoke($this->now());
    }

    /** « Déconnecter partout » : révoque tous les appareils du visiteur. Renvoie le nombre d'appareils révoqués. */
    public function revoquerTout(Visiteur $visiteur): int
    {
        $count = 0;
        foreach ($this->em->getRepository(VisiteurAppareil::class)->findBy(['visiteur' => $visiteur, 'revokedAt' => null]) as $appareil) {
            $appareil->revoke($this->now());
            ++$count;
        }

        return $count;
    }

    public static function pseudo(Visiteur $visiteur): string
    {
        return (string) ($visiteur->getPseudoSon() ?? $visiteur->getPrenom());
    }

    private function validSignature(string $payload, string $signature): bool
    {
        foreach (array_filter([$this->secret, $this->previousSecret]) as $secret) {
            if (hash_equals($this->sign($payload, $secret), $signature)) {
                return true;
            }
        }

        return false;
    }

    private function sign(string $data, string $secret): string
    {
        return self::b64(hash_hmac('sha256', $data, $secret, true));
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
