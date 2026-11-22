<?php

namespace App\Controller\Admin;

use App\Entity\Interactif;
use App\Entity\Medaille;
use App\Gamification\RulesSchema;
use App\Form\InteractifType;
use App\Service\LaunchService;
use App\Service\ManifestImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catalogue des interactifs (modules publiés). Leur configuration par parcours
 * se fait depuis la page du parcours (ParcoursAdminController).
 */
#[Route('/admin/interactifs', name: 'admin_interactif_')]
#[IsGranted('ROLE_ADMIN')]
final class InteractifAdminController extends AbstractCrudController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly ManifestImporter $importer,
        private readonly LaunchService $launch,
    ) {
        parent::__construct($em);
    }

    protected function config(): array
    {
        return [
            'entity' => Interactif::class,
            'form' => InteractifType::class,
            'route' => 'admin_interactif_',
            'label' => 'Interactif',
            'labelPlural' => 'Interactifs',
            'columns' => [
                'libelle' => 'Libellé',
                'version' => 'Version',
                'editeur' => 'Éditeur',
                'sourceType' => 'Type',
                'configurable' => 'Configurable',
                'parcoursInteractifs' => 'Parcours',
            ],
            'search' => ['libelle', 'editeur', 'categorie'],
            'sort' => ['libelle' => 'ASC'],
            'templates' => 'interactif',
        ];
    }

    /** Enregistre (ou met à jour) un interactif en collant son manifeste navinum.json. */
    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $json = (string) $request->request->get('manifest', '');
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import_manifest', (string) $request->request->get('_token'))) {
                $error = 'Jeton de sécurité invalide.';
            } else {
                try {
                    [$interactif, $created] = $this->importer->import($json);
                    $this->addFlash('success', \sprintf('Interactif « %s » %s depuis son manifeste.', $interactif->getLibelle(), $created ? 'créé' : 'mis à jour'));

                    return $this->redirectToRoute('admin_interactif_edit', ['id' => $interactif->getId()]);
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/interactif/import.html.twig', ['manifest' => $json, 'error' => $error]);
    }

    /**
     * Règles de gamification de l'interactif (XP, médailles) : formulaire généré depuis
     * RulesSchema, validé côté serveur (structure + syntaxe des expressions).
     */
    #[Route('/{id}/gamification', name: 'gamification', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['GET', 'POST'])]
    public function gamification(Request $request, string $id): Response
    {
        /** @var Interactif $interactif */
        $interactif = $this->findEntity($id);
        $errors = [];
        $submitted = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('gamification'.$id, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $submitted = (string) $request->request->get('regles', '');
            $regles = json_decode($submitted, true);
            $errors = RulesSchema::validate($regles);
            if ([] === $errors) {
                $regles = RulesSchema::normalize($regles);
                $vide = [] === ($regles['xp'] ?? []) && [] === ($regles['medailles'] ?? []);
                $interactif->setGamification($vide ? null : $regles);
                $this->em->flush();
                $this->addFlash('success', $vide ? 'Règles de gamification supprimées.' : 'Règles de gamification enregistrées.');

                return $this->redirectToRoute('admin_interactif_index');
            }
        }

        $medailles = array_map(
            static fn (Medaille $m) => $m->getLibelle(),
            $this->em->getRepository(Medaille::class)->findBy([], ['libelle' => 'ASC']),
        );

        return $this->render('admin/interactif/gamification.html.twig', [
            'interactif' => $interactif,
            'schema' => RulesSchema::schema(),
            'regles' => $submitted ?? json_encode($interactif->getGamification() ?? ['xp' => [], 'medailles' => []], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            'errors' => $errors,
            'medailles' => $medailles,
        ]);
    }

    /** Lance l'interactif avec sa configuration par défaut (aperçu, sans parcours ni visiteur). */
    #[Route('/{id}/apercu', name: 'preview', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function preview(Request $request, string $id): Response
    {
        /** @var Interactif $interactif */
        $interactif = $this->findEntity($id);
        if (!$this->isCsrfTokenValid('preview'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $url = $this->launch->launchUrl($this->launch->createToken($interactif));
        if (null === $url) {
            $this->addFlash('warning', 'Cet interactif n\'a pas d\'URL de lancement (champ « URL fichier interactif »).');

            return $this->redirectToRoute('admin_interactif_index');
        }

        return $this->redirect($url);
    }
}
