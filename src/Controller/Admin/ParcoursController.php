<?php

namespace App\Controller\Admin;

use App\Entity\Parcours;
use App\Repository\ParcoursRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParcoursController extends ResourceController
{
    /**
     * API endpoint pour récupérer les parcours actifs
     */
    public function getActiveParcoursAction(): JsonResponse
    {
        /** @var ParcoursRepository $repository */
        $repository = $this->repository;
        $parcours = $repository->findActiveParcoursForApi();

        $data = [];
        foreach ($parcours as $p) {
            $data[] = [
                'id' => $p->getId()->toString(),
                'libelle' => $p->getLibelle(),
                'ordre' => $p->getOrdre(),
                'expositions' => array_map(fn($e) => ['id' => $e->getId()->toString(), 'libelle' => $e->getLibelle()], $p->getExpositions()->toArray()),
                'interactifs' => array_map(fn($i) => ['id' => $i->getId()->toString(), 'libelle' => $i->getLibelle()], $p->getInteractifs()->toArray())
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Validation de l'unicité du libellé via AJAX
     */
    public function validateLibelleAction(Request $request): JsonResponse
    {
        $libelle = $request->query->get('libelle');
        $excludeId = $request->query->get('exclude_id');
        
        if (!$libelle) {
            return new JsonResponse(['valid' => false, 'message' => 'Libellé requis']);
        }

        /** @var ParcoursRepository $repository */
        $repository = $this->repository;
        
        $excludeParcours = null;
        if ($excludeId) {
            $excludeParcours = $repository->find($excludeId);
        }

        $isUnique = $repository->isLibelleUnique($libelle, $excludeParcours);

        return new JsonResponse([
            'valid' => $isUnique,
            'message' => $isUnique ? 'Libellé disponible' : 'Ce libellé est déjà utilisé'
        ]);
    }

    /**
     * Réorganisation des ordres de parcours
     */
    public function reorderAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['items']) || !is_array($data['items'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }

        /** @var ParcoursRepository $repository */
        $repository = $this->repository;
        $manager = $this->manager;

        try {
            foreach ($data['items'] as $item) {
                if (!isset($item['id']) || !isset($item['ordre'])) {
                    continue;
                }

                $parcours = $repository->find($item['id']);
                if ($parcours instanceof Parcours) {
                    $parcours->setOrdre((int) $item['ordre']);
                    $manager->persist($parcours);
                }
            }

            $manager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Ordre mis à jour']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    /**
     * Duplication d'un parcours
     */
    public function duplicateAction(Request $request): Response
    {
        /** @var Parcours $originalParcours */
        $originalParcours = $this->findOr404($request);
        
        // Créer une copie
        $newParcours = new Parcours();
        $newParcours->setLibelle($originalParcours->getLibelle() . ' (Copie)');
        $newParcours->setOrdre($this->repository->findNextAvailableOrdre());
        $newParcours->setIsTosync($originalParcours->isTosync());

        // Copier les relations
        foreach ($originalParcours->getExpositions() as $exposition) {
            $newParcours->addExposition($exposition);
        }

        foreach ($originalParcours->getInteractifs() as $interactif) {
            $newParcours->addInteractif($interactif);
        }

        $this->manager->persist($newParcours);
        $this->manager->flush();

        $this->addFlash('success', sprintf('Le parcours "%s" a été dupliqué avec succès.', $originalParcours->getLibelle()));

        return $this->redirectHandler->redirectTo($newParcours, 'update');
    }
}