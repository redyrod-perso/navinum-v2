<?php

namespace App\Controller\Admin;

use App\Entity\Visiteur;
use App\Form\VisiteurType;
use App\Service\CompteRefuse;
use App\Service\CompteVisiteur;
use App\Service\VisiteurToken;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/visiteurs', name: 'admin_visiteur_')]
#[IsGranted('ROLE_ADMIN')]
final class VisiteurAdminController extends AbstractCrudController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly VisiteurToken $tokens,
        private readonly CompteVisiteur $comptes,
    ) {
        parent::__construct($em);
    }

    /** Nouveau mot de passe saisi par l'admin : haché (jamais stocké ni affiché en clair). */
    protected function beforeSave(object $entity, FormInterface $form, bool $isNew): bool
    {
        $motDePasse = (string) $form->get('nouveauMotDePasse')->getData();
        if ('' === $motDePasse) {
            return true;
        }
        try {
            /** @var Visiteur $entity */
            $this->comptes->changerMotDePasse($entity, $motDePasse);
        } catch (CompteRefuse $e) {
            $form->get('nouveauMotDePasse')->addError(new FormError($e->getMessage()));

            return false;
        }
        $this->addFlash('success', 'Mot de passe du compte modifié.');

        return true;
    }

    protected function config(): array
    {
        return [
            'entity' => Visiteur::class,
            'form' => VisiteurType::class,
            'route' => 'admin_visiteur_',
            'label' => 'Visiteur',
            'labelPlural' => 'Visiteurs',
            'columns' => ['pseudoSon' => 'Pseudo', 'prenom' => 'Prénom', 'nom' => 'Nom', 'email' => 'Email', 'active' => 'Actif'],
            'search' => ['nom', 'prenom', 'email', 'pseudo_son'],
            'sort' => ['createdAt' => 'DESC'],
            'templates' => 'visiteur',
        ];
    }

    /** « Déconnecter partout » : révoque tous les appareils du visiteur (badge perdu, appareil volé). */
    #[Route('/{id}/deconnecter', name: 'deconnecter', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function deconnecter(Request $request, string $id): Response
    {
        /** @var Visiteur $visiteur */
        $visiteur = $this->findEntity($id);
        if (!$this->isCsrfTokenValid('deconnecter'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $count = $this->tokens->revoquerTout($visiteur);
        $this->em->flush();
        $this->addFlash('success', \sprintf('« %s » est déconnecté de %d appareil(s).', VisiteurToken::pseudo($visiteur), $count));

        return $this->redirectToRoute('admin_visiteur_index');
    }
}
