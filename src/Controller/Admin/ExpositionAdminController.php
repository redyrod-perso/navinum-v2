<?php

namespace App\Controller\Admin;

use App\Entity\Exposition;
use App\Form\ExpositionType;
use App\Repository\ExpositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class ExpositionAdminController extends AbstractController
{
    public function index(ExpositionRepository $repo): Response
    {
        return $this->render('admin/exposition/index.html.twig', [
            'items' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Exposition();
        $form = $this->createForm(ExpositionType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Exposition créée');
            return $this->redirectToRoute('admin_exposition_index');
        }

        return $this->render('admin/exposition/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle exposition',
        ]);
    }

    public function edit(Exposition $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ExpositionType::class, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Exposition mise à jour');
            return $this->redirectToRoute('admin_exposition_index');
        }

        return $this->render('admin/exposition/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier exposition',
        ]);
    }

    public function delete(Exposition $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Exposition supprimée');
        }
        return $this->redirectToRoute('admin_exposition_index');
    }
}
