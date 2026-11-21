<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/comptes', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractCrudController
{
    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct($em);
    }

    protected function config(): array
    {
        return [
            'entity' => User::class,
            'form' => UserType::class,
            'route' => 'admin_user_',
            'label' => 'Compte',
            'labelPlural' => 'Comptes',
            'columns' => ['email' => 'Email', 'nom' => 'Nom', 'storedRoles' => 'Rôles', 'organisateur' => 'Organisateur', 'active' => 'Actif'],
            'search' => ['email', 'nom'],
            'sort' => ['email' => 'ASC'],
        ];
    }

    protected function formOptions(object $entity): array
    {
        /** @var User $entity */
        return ['require_password' => '' === $entity->getPassword()];
    }

    protected function beforeSave(object $entity, FormInterface $form, bool $isNew): bool
    {
        /** @var User $entity */
        if ([] === $entity->getStoredRoles()) {
            $form->get('storedRoles')->addError(new FormError('Choisissez au moins un rôle.'));

            return false;
        }
        if (!$entity->isAdmin() && null === $entity->getOrganisateur()) {
            $form->get('organisateur')->addError(new FormError('Un compte organisateur doit être rattaché à un organisateur.'));

            return false;
        }
        $plain = $form->get('plainPassword')->getData();
        if (\is_string($plain) && '' !== $plain) {
            $entity->setPassword($this->hasher->hashPassword($entity, $plain));
        }

        return true;
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $user = $this->findEntity($id);
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('admin_user_index');
        }

        return parent::delete($request, $id);
    }
}
