<?php

namespace App\OAuth;

use App\Entity\Visiteur;
use App\Entity\VisiteurIdentite;
use App\Repository\VisiteurRepository;
use App\Service\CompteVisiteur;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Visiteur correspondant à une identité OAuth, dans cet ordre :
 * 1. compte fournisseur déjà lié → son visiteur ;
 * 2. visiteur connecté sur l'appareil au démarrage → le fournisseur lui est **ajouté** (un compte peut lier
 *    Google, Microsoft, Facebook… ; un invité devient un compte et garde XP et médailles) ;
 * 3. adresse **garantie** par le fournisseur et déjà utilisée par un visiteur → liaison à ce visiteur ;
 * 4. sinon nouveau visiteur (compte).
 * Le compte fournisseur est alors lié (VisiteurIdentite). L'adresse n'est enregistrée que si elle est libre.
 */
final class ConnexionOAuth
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VisiteurRepository $visiteurs,
    ) {
    }

    public function visiteur(string $fournisseur, IdentiteOAuth $identite, ?Visiteur $connecte = null): Visiteur
    {
        $lien = $this->em->getRepository(VisiteurIdentite::class)->findOneBy(['fournisseur' => $fournisseur, 'sujet' => $identite->sujet]);
        if (null !== $lien) {
            return $lien->getVisiteur();
        }

        $parEmail = $identite->emailVerifie && null !== $identite->email ? $this->visiteurs->findOneBy(['email' => $identite->email]) : null;
        $visiteur = $connecte ?? $parEmail ?? new Visiteur();
        if (CompteVisiteur::TYPE !== $visiteur->getType()) {
            $pseudo = VisiteurToken::pseudo($visiteur) ?: ($identite->nom ?: (null !== $identite->email ? strstr($identite->email, '@', true) : 'Visiteur'));
            $visiteur->setPseudoSon(mb_substr((string) $pseudo, 0, 40))->setIsAnonyme(false)->setType(CompteVisiteur::TYPE);
            $visiteur->setHasNewsletter($visiteur->getHasNewsletter() ?? false);
        }
        if (null === $visiteur->getEmail() && null !== $identite->email && null === $this->visiteurs->findOneBy(['email' => $identite->email])) {
            $visiteur->setEmail($identite->email);
            if ($identite->emailVerifie) {
                $visiteur->setEmailVerifieAt(new \DateTimeImmutable());
            }
        }
        $this->em->persist($visiteur);
        $this->em->persist(new VisiteurIdentite($visiteur, $fournisseur, $identite->sujet));
        $this->em->flush();

        return $visiteur;
    }
}
