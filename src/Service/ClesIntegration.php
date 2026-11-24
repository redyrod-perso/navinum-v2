<?php

namespace App\Service;

use App\Entity\CleIntegration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Création, vérification et révocation des clés d'intégration (#11). */
final class ClesIntegration
{
    public const PREFIX = 'nvk1.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $droits
     *
     * @return array{0: CleIntegration, 1: string} la clé et son secret (à afficher une seule fois)
     */
    public function creer(string $libelle, array $droits): array
    {
        $secret = self::PREFIX.bin2hex(random_bytes(32));
        $cle = new CleIntegration(mb_substr(trim($libelle), 0, 120), substr($secret, 0, 13).'…', hash('sha256', $secret), $droits);
        $this->em->persist($cle);
        $this->em->flush();

        return [$cle, $secret];
    }

    /** Clé active correspondant au secret, ou null ; mémorise la date de dernière utilisation. */
    public function verifier(string $secret): ?CleIntegration
    {
        if (!str_starts_with($secret, self::PREFIX)) {
            return null;
        }
        $cle = $this->em->getRepository(CleIntegration::class)->findOneBy(['secretHash' => hash('sha256', $secret)]);
        if (null === $cle || $cle->isRevoked()) {
            return null;
        }
        $cle->utilisee($this->clock->now());
        $this->em->flush();

        return $cle;
    }

    public function revoquer(CleIntegration $cle): void
    {
        $cle->revoquer($this->clock->now());
        $this->em->flush();
    }
}
