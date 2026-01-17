<?php

namespace App\Controller\Admin;

use App\Entity\Interactif;
use App\Form\InteractifType;
use App\Repository\InteractifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class InteractifAdminController extends AbstractController
{
    public function index(InteractifRepository $repo): Response
    {
        return $this->render('admin/interactif/index.html.twig', [
            'items' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Interactif();
        $form = $this->createForm(InteractifType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Interactif créé');
            return $this->redirectToRoute('admin_interactif_index');
        }

        return $this->render('admin/interactif/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel interactif',
        ]);
    }

    public function edit(Interactif $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(InteractifType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Interactif mis à jour');
            return $this->redirectToRoute('admin_interactif_index');
        }

        return $this->render('admin/interactif/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier interactif',
        ]);
    }

    public function delete(Interactif $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Interactif supprimé');
        }
        return $this->redirectToRoute('admin_interactif_index');
    }
}
