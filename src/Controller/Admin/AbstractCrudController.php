<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * CRUD générique de l'admin (liste paginée + recherche, création, édition, suppression).
 *
 * Une sous-classe déclare son préfixe de route au niveau de la classe :
 *   #[Route('/admin/expositions', name: 'admin_exposition_')]
 * et décrit l'entité via config(). Les routes ci-dessous sont héritées :
 *   {prefix}index, {prefix}new, {prefix}edit, {prefix}delete
 */
abstract class AbstractCrudController extends AbstractController
{
    protected const PER_PAGE = 30;

    public function __construct(protected readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array{
     *   entity: class-string,
     *   form: class-string,
     *   route: string,
     *   label: string,
     *   labelPlural: string,
     *   columns: array<string, string>,
     *   search?: list<string>,
     *   sort?: array<string, 'ASC'|'DESC'>,
     *   readonly?: bool,
     *   templates?: string,
     * }
     */
    abstract protected function config(): array;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $config = $this->config();
        $qb = $this->listQueryBuilder();
        $q = trim((string) $request->query->get('q', ''));
        if ('' !== $q && !empty($config['search'])) {
            $or = $qb->expr()->orX();
            foreach ($config['search'] as $field) {
                $or->add("LOWER(e.$field) LIKE :q");
            }
            $qb->andWhere($or)->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        foreach ($config['sort'] ?? ['createdAt' => 'DESC'] as $field => $dir) {
            $qb->addOrderBy("e.$field", $dir);
        }

        $total = (int) (clone $qb)->select('COUNT(DISTINCT e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $page = max(1, $request->query->getInt('page', 1));
        $items = $qb->setFirstResult(($page - 1) * static::PER_PAGE)->setMaxResults(static::PER_PAGE)->getQuery()->getResult();

        return $this->render($this->template('index'), [
            'crud' => $config,
            'items' => $items,
            'q' => $q,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / static::PER_PAGE)),
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyIfReadonly();
        $entity = $this->createEntity();
        $form = $this->createForm($this->config()['form'], $entity, $this->formOptions($entity));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->beforeSave($entity, $form, true)) {
            $this->em->persist($entity);
            $this->em->flush();
            $this->addFlash('success', $this->config()['label'].' créé(e).');

            return $this->redirectAfterSave($entity);
        }

        return $this->render($this->template('form'), ['crud' => $this->config(), 'form' => $form, 'entity' => null]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->denyIfReadonly();
        $entity = $this->findEntity($id);
        $form = $this->createForm($this->config()['form'], $entity, $this->formOptions($entity));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->beforeSave($entity, $form, false)) {
            $this->em->flush();
            $this->addFlash('success', $this->config()['label'].' enregistré(e).');

            return $this->redirectAfterSave($entity);
        }

        return $this->render($this->template('form'), ['crud' => $this->config(), 'form' => $form, 'entity' => $entity]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        $this->denyIfReadonly();
        $entity = $this->findEntity($id);
        if (!$this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, suppression annulée.');
        } else {
            $this->em->remove($entity);
            $this->em->flush();
            $this->addFlash('success', $this->config()['label'].' supprimé(e).');
        }

        return $this->redirectToRoute($this->config()['route'].'index');
    }

    // --- Points d'extension -------------------------------------------------

    /** Requête de liste ; à surcharger pour restreindre (ex. organisateur). Alias obligatoire : e. */
    protected function listQueryBuilder(): QueryBuilder
    {
        return $this->em->getRepository($this->config()['entity'])->createQueryBuilder('e');
    }

    /** Vérifie que l'utilisateur peut accéder à cette entité (lève une 403 sinon). */
    protected function denyUnlessAllowed(object $entity): void
    {
    }

    protected function createEntity(): object
    {
        $class = $this->config()['entity'];

        return new $class();
    }

    protected function formOptions(object $entity): array
    {
        return [];
    }

    /** Appelé avant persist/flush ; retourner false pour ré-afficher le formulaire. */
    protected function beforeSave(object $entity, FormInterface $form, bool $isNew): bool
    {
        return true;
    }

    protected function redirectAfterSave(object $entity): Response
    {
        return $this->redirectToRoute($this->config()['route'].'index');
    }

    /** Gabarit spécifique admin/{templates}/{name}.html.twig s'il existe, sinon le gabarit générique. */
    protected function template(string $name): string
    {
        $dir = $this->config()['templates'] ?? null;
        $specific = "admin/$dir/$name.html.twig";

        return $dir && $this->container->get('twig')->getLoader()->exists($specific) ? $specific : "admin/crud/$name.html.twig";
    }

    protected function findEntity(string $id): object
    {
        if (!Uuid::isValid($id)) {
            throw new NotFoundHttpException();
        }
        $entity = $this->em->find($this->config()['entity'], Uuid::fromString($id));
        if (null === $entity) {
            throw $this->createNotFoundException();
        }
        $this->denyUnlessAllowed($entity);

        return $entity;
    }

    private function denyIfReadonly(): void
    {
        if ($this->config()['readonly'] ?? false) {
            throw $this->createAccessDeniedException();
        }
    }
}
