<?php

namespace App\Controller\Admin;

use App\Entity\Interactif;
use App\Entity\Parcours;
use App\Entity\ParcoursInteractif;
use App\Form\ParcoursType;
use App\Service\ConfigSchema;
use App\Service\LaunchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Parcours = liste ordonnée des interactifs proposés dans un musée.
 * Chaque interactif placé reçoit sa propre configuration, saisie dans un formulaire
 * généré à partir du JSON Schema que l'interactif déclare (Interactif::variable).
 */
#[Route('/admin/parcours', name: 'admin_parcours_')]
#[IsGranted('ROLE_ORGANISATEUR')]
final class ParcoursAdminController extends AbstractCrudController
{
    use OrganisateurScopeTrait;

    private const ID = '[0-9a-f-]{36}';

    public function __construct(EntityManagerInterface $em, private readonly LaunchService $launch)
    {
        parent::__construct($em);
    }

    protected function config(): array
    {
        return [
            'entity' => Parcours::class,
            'form' => ParcoursType::class,
            'route' => 'admin_parcours_',
            'label' => 'Parcours',
            'labelPlural' => 'Parcours',
            'columns' => ['libelle' => 'Libellé', 'expositions' => 'Expositions', 'parcoursInteractifs' => 'Interactifs', 'ordre' => 'Ordre'],
            'search' => ['libelle'],
            'sort' => ['ordre' => 'ASC', 'libelle' => 'ASC'],
            'templates' => 'parcours',
        ];
    }

    protected function listQueryBuilder(): QueryBuilder
    {
        $qb = parent::listQueryBuilder();
        if ($this->isFullAdmin()) {
            return $qb;
        }

        return $this->scopeExpositions($qb->innerJoin('e.expositions', 'x'), 'x');
    }

    protected function denyUnlessAllowed(object $entity): void
    {
        /** @var Parcours $entity */
        if (!$this->canManageParcours($entity)) {
            throw $this->createAccessDeniedException();
        }
    }

    protected function formOptions(object $entity): array
    {
        return ['organisateur' => $this->isFullAdmin() ? null : $this->currentOrganisateur()];
    }

    protected function redirectAfterSave(object $entity): Response
    {
        return $this->redirectToRoute('admin_parcours_show', ['id' => $entity->getId()]);
    }

    /** Page du parcours : ses interactifs, leur ordre, leur configuration, le lancement. */
    #[Route('/{id}', name: 'show', requirements: ['id' => self::ID], methods: ['GET'])]
    public function show(string $id): Response
    {
        /** @var Parcours $parcours */
        $parcours = $this->findEntity($id);
        $catalogue = $this->em->getRepository(Interactif::class)->findBy([], ['libelle' => 'ASC']);

        return $this->render('admin/parcours/show.html.twig', [
            'parcours' => $parcours,
            'catalogue' => $catalogue,
            'events_topic' => LaunchService::parcoursEventsTopic((string) $parcours->getId()),
        ]);
    }

    #[Route('/{id}/interactifs', name: 'add_interactif', requirements: ['id' => self::ID], methods: ['POST'])]
    public function addInteractif(Request $request, string $id): Response
    {
        /** @var Parcours $parcours */
        $parcours = $this->findEntity($id);
        $this->checkCsrf($request, 'placement'.$id);
        $interactifId = (string) $request->request->get('interactif');
        $interactif = Uuid::isValid($interactifId) ? $this->em->find(Interactif::class, Uuid::fromString($interactifId)) : null;
        if (null === $interactif) {
            $this->addFlash('danger', 'Interactif introuvable.');
        } else {
            $parcours->addInteractif($interactif);
            $this->em->flush();
            $this->addFlash('success', \sprintf('« %s » ajouté au parcours.', $interactif->getLibelle()));
        }

        return $this->redirectToRoute('admin_parcours_show', ['id' => $id]);
    }

    /** Formulaire générique de configuration d'un interactif placé dans le parcours. */
    #[Route('/{id}/interactifs/{placement}/config', name: 'config', requirements: ['id' => self::ID, 'placement' => self::ID], methods: ['GET', 'POST'])]
    public function configure(Request $request, string $id, string $placement): Response
    {
        $pi = $this->findPlacement($id, $placement);
        $schema = $pi->getInteractif()->getConfigSchema();
        $isSchema = null !== $schema && ConfigSchema::isSchema($schema);
        $errors = [];
        $submitted = null;

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'config'.$placement);
            if ($request->request->has('reset')) {
                $pi->setConfig(null);
                $this->em->flush();
                $this->launch->publishConfig($pi);
                $this->addFlash('success', 'Configuration réinitialisée aux valeurs par défaut de l\'interactif.');

                return $this->redirectToRoute('admin_parcours_config', ['id' => $id, 'placement' => $placement]);
            }

            $submitted = (string) $request->request->get('config', '');
            $config = json_decode($submitted, true);
            if (!\is_array($config) || ([] !== $config && array_is_list($config))) {
                $errors[] = 'La configuration doit être un objet JSON valide.';
            } elseif ($isSchema) {
                $errors = ConfigSchema::validate($schema, $config);
            }

            if ([] === $errors) {
                $pi->setConfig($config);
                $this->em->flush();
                $this->launch->publishConfig($pi);
                $this->addFlash('success', 'Configuration enregistrée.');

                return $this->redirectToRoute('admin_parcours_show', ['id' => $id]);
            }
        }

        return $this->render('admin/parcours/config.html.twig', [
            'placement' => $pi,
            'schema' => $isSchema ? $schema : null,
            'config' => $submitted ?? json_encode((object) $pi->getEffectiveConfig(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/interactifs/{placement}/deplacer/{direction}', name: 'move', requirements: ['id' => self::ID, 'placement' => self::ID, 'direction' => 'up|down'], methods: ['POST'])]
    public function move(Request $request, string $id, string $placement, string $direction): Response
    {
        $pi = $this->findPlacement($id, $placement);
        $this->checkCsrf($request, 'placement'.$id);

        // Renumérote 0..n puis échange avec le voisin.
        $list = array_values($pi->getParcours()->getParcoursInteractifs()->toArray());
        usort($list, static fn (ParcoursInteractif $a, ParcoursInteractif $b) => $a->getOrdre() <=> $b->getOrdre());
        $index = array_search($pi, $list, true);
        $target = 'up' === $direction ? $index - 1 : $index + 1;
        if (isset($list[$target])) {
            [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
        }
        foreach ($list as $i => $item) {
            $item->setOrdre($i);
        }
        $this->em->flush();

        return $this->redirectToRoute('admin_parcours_show', ['id' => $id]);
    }

    #[Route('/{id}/interactifs/{placement}/retirer', name: 'remove_interactif', requirements: ['id' => self::ID, 'placement' => self::ID], methods: ['POST'])]
    public function removeInteractif(Request $request, string $id, string $placement): Response
    {
        $pi = $this->findPlacement($id, $placement);
        $this->checkCsrf($request, 'placement'.$id);
        $pi->getParcours()->removeParcoursInteractif($pi);
        $this->em->flush();
        $this->addFlash('success', \sprintf('« %s » retiré du parcours (sa configuration est supprimée).', $pi->getInteractif()->getLibelle()));

        return $this->redirectToRoute('admin_parcours_show', ['id' => $id]);
    }

    /** Lance l'interactif tel qu'un visiteur le verrait dans ce parcours (visiteur anonyme). */
    #[Route('/{id}/interactifs/{placement}/lancer', name: 'launch', requirements: ['id' => self::ID, 'placement' => self::ID], methods: ['POST'])]
    public function launch(Request $request, string $id, string $placement): Response
    {
        $pi = $this->findPlacement($id, $placement);
        $this->checkCsrf($request, 'placement'.$id);
        $url = $this->launch->launchUrl($this->launch->createToken($pi->getInteractif(), $pi));
        if (null === $url) {
            $this->addFlash('warning', 'Cet interactif n\'a pas d\'URL de lancement.');

            return $this->redirectToRoute('admin_parcours_show', ['id' => $id]);
        }

        return $this->redirect($url);
    }

    private function findPlacement(string $parcoursId, string $placementId): ParcoursInteractif
    {
        /** @var Parcours $parcours */
        $parcours = $this->findEntity($parcoursId);
        foreach ($parcours->getParcoursInteractifs() as $pi) {
            if ((string) $pi->getId() === $placementId) {
                return $pi;
            }
        }
        throw $this->createNotFoundException();
    }

    private function checkCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }
}
