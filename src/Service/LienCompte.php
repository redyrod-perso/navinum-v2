<?php

namespace App\Service;

use App\Entity\Visiteur;
use App\Repository\VisiteurRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Jetons des liens envoyés par e-mail aux comptes visiteurs (#71) : `<données>.<signature>`, HMAC-SHA256 avec
 * APP_SECRET, sans stockage.
 *
 * La signature couvre aussi une **empreinte** de l'état du compte, ce qui rend les liens caducs d'eux-mêmes :
 * - `verification` (48 h) : l'adresse ; changer d'adresse invalide le lien ;
 * - `mot-de-passe` (1 h) : l'adresse et le hachage du mot de passe ; le lien ne sert donc **qu'une fois**
 *   (le nouveau mot de passe change le hachage).
 */
final class LienCompte
{
    public const VERIFICATION = 'verification';
    public const MOT_DE_PASSE = 'mot-de-passe';

    private const DUREES = [self::VERIFICATION => 48 * 3600, self::MOT_DE_PASSE => 3600];

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly VisiteurRepository $visiteurs,
    ) {
    }

    public function creer(string $type, Visiteur $visiteur, ?int $maintenant = null): string
    {
        $donnees = self::b64((string) json_encode([
            't' => $type,
            'v' => $visiteur->getId()->toRfc4122(),
            'x' => ($maintenant ?? time()) + self::DUREES[$type],
        ]));

        return $donnees.'.'.$this->signer($donnees, $visiteur, $type);
    }

    /** Visiteur (compte actif) désigné par un lien valide, non expiré, du type attendu ; sinon null. */
    public function verifier(string $type, string $jeton, ?int $maintenant = null): ?Visiteur
    {
        $morceaux = explode('.', $jeton);
        if (2 !== \count($morceaux) || \strlen($jeton) > 1000) {
            return null;
        }
        [$donnees, $signature] = $morceaux;
        $contenu = json_decode((string) base64_decode(strtr($donnees, '-_', '+/'), true), true);
        if (!\is_array($contenu) || $type !== ($contenu['t'] ?? null) || !\is_int($contenu['x'] ?? null)
            || $contenu['x'] < ($maintenant ?? time()) || !\is_string($contenu['v'] ?? null) || !Uuid::isValid($contenu['v'])) {
            return null;
        }
        $visiteur = $this->visiteurs->find(Uuid::fromString($contenu['v']));
        if (null === $visiteur || !$visiteur->isActive() || CompteVisiteur::TYPE !== $visiteur->getType() || null === $visiteur->getEmail()) {
            return null;
        }

        return hash_equals($this->signer($donnees, $visiteur, $type), $signature) ? $visiteur : null;
    }

    private function signer(string $donnees, Visiteur $visiteur, string $type): string
    {
        $empreinte = self::MOT_DE_PASSE === $type
            ? $visiteur->getEmail().'|'.$visiteur->getPasswordSon()
            : (string) $visiteur->getEmail();

        return self::b64(hash_hmac('sha256', 'lien-compte|'.$donnees.'|'.$empreinte, $this->secret, true));
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
