<?php

namespace App\Service;

use App\Entity\Visiteur;
use App\Repository\VisiteurRepository;
use App\Webhook\Webhooks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Comptes en ligne des visiteurs (e-mail + mot de passe, #4).
 *
 * - Un visiteur **invité** qui crée un compte garde son identité (mêmes XP, médailles, historique).
 * - L'adresse est unique (enregistrée en minuscules) et n'apparaît jamais dans un profil public.
 * - Mot de passe : 8 caractères au moins, haché (`visiteur` dans security.password_hashers), stocké dans
 *   `password_son`.
 * - Consentement newsletter explicite (`has_newsletter`, faux par défaut).
 * - Vérification de l'adresse : `emailVerifieAt`, renseignée quand l'envoi d'e-mails sera branché.
 */
final class CompteVisiteur
{
    public const MOT_DE_PASSE_MIN = 8;
    public const TYPE = 'compte';

    private readonly PasswordHasherInterface $hasher;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VisiteurRepository $visiteurs,
        PasswordHasherFactoryInterface $hashers,
        private readonly Webhooks $webhooks,
    ) {
        $this->hasher = $hashers->getPasswordHasher('visiteur');
    }

    /**
     * Crée un compte, ou transforme l'invité `$invite` en compte (même visiteur).
     *
     * @throws CompteRefuse code VALIDATION ou EMAIL_DEJA_UTILISE
     */
    public function inscrire(string $email, string $motDePasse, ?string $pseudo, bool $newsletter, ?Visiteur $invite = null): Visiteur
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            throw new CompteRefuse('VALIDATION', 'Adresse e-mail invalide.');
        }
        if (mb_strlen($motDePasse) < self::MOT_DE_PASSE_MIN || \strlen($motDePasse) > 4096) {
            throw new CompteRefuse('VALIDATION', \sprintf('Le mot de passe doit faire au moins %d caractères.', self::MOT_DE_PASSE_MIN));
        }
        $pseudo = null !== $pseudo ? trim($pseudo) : null;
        if (null !== $pseudo && '' !== $pseudo && (mb_strlen($pseudo) < 2 || mb_strlen($pseudo) > 40)) {
            throw new CompteRefuse('VALIDATION', 'Le pseudo doit faire entre 2 et 40 caractères.');
        }
        $existant = $this->visiteurs->findOneBy(['email' => $email]);
        if (null !== $existant && $existant !== $invite) {
            throw new CompteRefuse('EMAIL_DEJA_UTILISE', 'Un compte existe déjà avec cette adresse : connectez-vous.');
        }

        // Seul un invité peut devenir un compte ; sinon, nouveau visiteur
        $visiteur = null !== $invite && self::TYPE !== $invite->getType() ? $invite : new Visiteur();
        $pseudo = '' !== (string) $pseudo ? $pseudo : (VisiteurToken::pseudo($visiteur) ?: strstr($email, '@', true));
        $visiteur->setEmail($email)
            ->setPasswordSon($this->hasher->hash($motDePasse))
            ->setPseudoSon(mb_substr($pseudo, 0, 40))
            ->setIsAnonyme(false)
            ->setType(self::TYPE)
            ->setHasNewsletter($newsletter);
        $this->em->persist($visiteur);
        $this->em->flush();
        $this->webhooks->publier('visiteur.inscrit', ['visiteur' => Webhooks::visiteur($visiteur), 'mode' => 'compte', 'depuisInvite' => $visiteur === $invite]);

        return $visiteur;
    }

    /** Visiteur dont l'adresse et le mot de passe correspondent, ou null (message générique côté API). */
    public function authentifier(string $email, string $motDePasse): ?Visiteur
    {
        $visiteur = $this->visiteurs->findOneBy(['email' => mb_strtolower(trim($email))]);
        $hash = $visiteur?->getPasswordSon();
        if (null === $visiteur || null === $hash || self::TYPE !== $visiteur->getType() || !$visiteur->isActive()) {
            $this->hasher->hash($motDePasse); // même durée de réponse qu'avec un compte existant

            return null;
        }
        if (!$this->hasher->verify($hash, $motDePasse)) {
            return null;
        }
        if ($this->hasher->needsRehash($hash)) {
            $visiteur->setPasswordSon($this->hasher->hash($motDePasse));
            $this->em->flush();
        }

        return $visiteur;
    }

    /** Nouveau mot de passe défini par l'administration (« mot de passe oublié » sans e-mail). */
    public function changerMotDePasse(Visiteur $visiteur, string $motDePasse): void
    {
        if (mb_strlen($motDePasse) < self::MOT_DE_PASSE_MIN) {
            throw new CompteRefuse('VALIDATION', \sprintf('Le mot de passe doit faire au moins %d caractères.', self::MOT_DE_PASSE_MIN));
        }
        $visiteur->setPasswordSon($this->hasher->hash($motDePasse));
    }
}
