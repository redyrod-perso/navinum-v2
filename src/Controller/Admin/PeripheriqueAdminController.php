<?php

namespace App\Controller\Admin;

use App\Entity\Peripherique;
use App\Form\PeripheriqueType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class PeripheriqueAdminController extends AbstractController
{
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Peripherique();
        $form = $this->createForm(PeripheriqueType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'Périphérique créé');
            return $this->redirectToRoute('admin_peripherique_index');
        }
        return $this->render('admin/peripherique/form.html.twig', [ 'form' => $form, 'title' => 'Nouveau périphérique' ]);
    }

    public function edit(Peripherique $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PeripheriqueType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Périphérique mis à jour');
            return $this->redirectToRoute('admin_peripherique_index');
        }
        return $this->render('admin/peripherique/form.html.twig', [ 'form' => $form, 'title' => 'Modifier périphérique' ]);
    }

    public function delete(Peripherique $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Périphérique supprimé');
        }
        return $this->redirectToRoute('admin_peripherique_index');
    }
}
