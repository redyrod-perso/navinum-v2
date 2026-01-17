<?php

namespace App\Controller\Admin;

use App\Entity\Visiteur;
use App\Form\VisiteurType;
use App\Repository\VisiteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class VisiteurAdminController extends AbstractController
{
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Visiteur();
        $form = $this->createForm(VisiteurType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Visiteur créé');
            return $this->redirectToRoute('admin_visiteur_index');
        }
        return $this->render('admin/visiteur/form.html.twig', [ 'form' => $form, 'title' => 'Nouveau visiteur' ]);
    }

    public function edit(Visiteur $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(VisiteurType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Visiteur mis à jour');
            return $this->redirectToRoute('admin_visiteur_index');
        }
        return $this->render('admin/visiteur/form.html.twig', [ 'form' => $form, 'title' => 'Modifier visiteur' ]);
    }

    public function delete(Visiteur $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Visiteur supprimé');
        }
        return $this->redirectToRoute('admin_visiteur_index');
    }
}
