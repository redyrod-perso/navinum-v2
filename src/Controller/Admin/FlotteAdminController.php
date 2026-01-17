<?php

namespace App\Controller\Admin;

use App\Entity\Flotte;
use App\Form\FlotteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class FlotteAdminController extends AbstractController
{
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Flotte();
        $form = $this->createForm(FlotteType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Flotte créée');
            return $this->redirectToRoute('admin_flotte_index');
        }
        return $this->render('admin/flotte/form.html.twig', [ 'form' => $form, 'title' => 'Nouvelle flotte' ]);
    }

    public function edit(Flotte $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FlotteType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Flotte mise à jour');
            return $this->redirectToRoute('admin_flotte_index');
        }
        return $this->render('admin/flotte/form.html.twig', [ 'form' => $form, 'title' => 'Modifier flotte' ]);
    }

    public function delete(Flotte $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Flotte supprimée');
        }
        return $this->redirectToRoute('admin_flotte_index');
    }
}
