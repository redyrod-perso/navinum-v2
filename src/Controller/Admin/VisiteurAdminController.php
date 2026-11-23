<?php

namespace App\Controller\Admin;

use App\Entity\Visiteur;
use App\Form\VisiteurType;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/visiteurs', name: 'admin_visiteur_')]
#[IsGranted('ROLE_ADMIN')]
final class VisiteurAdminController extends AbstractCrudController
{
    public function __construct(EntityManagerInterface $em, private readonly VisiteurToken $tokens)
    {
        parent::__construct($em);
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
