<?php

namespace App\Controller\Admin;

use App\Entity\Parcours;
use App\Form\ParcoursType;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParcoursAdminController extends AbstractController
{
    public function index(ParcoursRepository $repo): Response
    {
        return $this->render('admin/parcours/index.html.twig', [
            'items' => $repo->findBy([], ['ordre' => 'ASC', 'libelle' => 'ASC']),
        ]);
    }

    public function new(Request $request, EntityManagerInterface $em, ParcoursRepository $repo): Response
    {
        $entity = new Parcours();

        // Définir l'ordre automatiquement si non spécifié
        $nextOrdre = $repo->findNextAvailableOrdre();
        $entity->setOrdre($nextOrdre);

        $form = $this->createForm(ParcoursType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Parcours créé avec succès');
            return $this->redirectToRoute('admin_parcours_index');
        }

        return $this->render('admin/parcours/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau Parcours',
            'entity' => $entity,
        ]);
    }

    public function show(Parcours $entity): Response
    {
        return $this->render('admin/parcours/show.html.twig', [
            'parcours' => $entity,
        ]);
    }

    public function edit(Parcours $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ParcoursType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Parcours mis à jour avec succès');
            return $this->redirectToRoute('admin_parcours_index');
        }

        return $this->render('admin/parcours/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le Parcours',
            'entity' => $entity,
        ]);
    }

    public function delete(Parcours $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Parcours supprimé avec succès');
        }
        return $this->redirectToRoute('admin_parcours_index');
    }
}
