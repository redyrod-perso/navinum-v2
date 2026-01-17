<?php

namespace App\Controller\Admin;

use App\Entity\Rfid;
use App\Form\RfidType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class RfidAdminController extends AbstractController
{
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = new Rfid();
        $form = $this->createForm(RfidType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            $this->addFlash('success', 'RFID créé');
            return $this->redirectToRoute('admin_rfid_index');
        }
        return $this->render('admin/rfid/form.html.twig', [ 'form' => $form, 'title' => 'Nouveau RFID' ]);
    }

    public function edit(Rfid $entity, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RfidType::class, $entity);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'RFID mis à jour');
            return $this->redirectToRoute('admin_rfid_index');
        }
        return $this->render('admin/rfid/form.html.twig', [ 'form' => $form, 'title' => 'Modifier RFID' ]);
    }

    public function delete(Rfid $entity, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$entity->getId(), (string)$request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'RFID supprimé');
        }
        return $this->redirectToRoute('admin_rfid_index');
    }
}
