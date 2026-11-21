<?php

namespace App\Controller\Admin;

use App\Entity\Exposition;
use App\Form\ExpositionType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/expositions', name: 'admin_exposition_')]
#[IsGranted('ROLE_ORGANISATEUR')]
final class ExpositionAdminController extends AbstractCrudController
{
    use OrganisateurScopeTrait;

    protected function config(): array
    {
        return [
            'entity' => Exposition::class,
            'form' => ExpositionType::class,
            'route' => 'admin_exposition_',
            'label' => 'Exposition',
            'labelPlural' => 'Expositions',
            'columns' => [
                'libelle' => 'Libellé',
                'organisateurEditeur' => 'Éditeur',
                'organisateurDiffuseur' => 'Diffuseur',
                'startAt' => 'Début',
                'endAt' => 'Fin',
                'parcours' => 'Parcours',
            ],
            'search' => ['libelle'],
            'sort' => ['createdAt' => 'DESC'],
        ];
    }

    protected function listQueryBuilder(): QueryBuilder
    {
        return $this->scopeExpositions(parent::listQueryBuilder(), 'e');
    }

    protected function denyUnlessAllowed(object $entity): void
    {
        /** @var Exposition $entity */
        if (!$this->canManageExposition($entity)) {
            throw $this->createAccessDeniedException();
        }
    }

    protected function beforeSave(object $entity, FormInterface $form, bool $isNew): bool
    {
        /** @var Exposition $entity */
        // Un organisateur ne peut pas « perdre » son exposition : il en reste au moins l'éditeur.
        if (!$this->isFullAdmin() && !$this->canManageExposition($entity)) {
            $entity->setOrganisateurEditeur($this->currentOrganisateur());
        }

        return true;
    }
}
