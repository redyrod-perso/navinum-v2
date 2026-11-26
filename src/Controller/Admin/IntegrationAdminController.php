<?php

namespace App\Controller\Admin;

use App\Entity\CleIntegration;
use App\Service\ClesIntegration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Admin › Intégrations : clés d'intégration des outils externes (n8n…, #11). Le secret n'est affiché qu'une fois,
 * à la création ; seule son empreinte est conservée.
 */
#[Route('/admin/integrations', name: 'admin_integration_')]
#[IsGranted('ROLE_ADMIN')]
final class IntegrationAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClesIntegration $cles,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $secret = null;
        $erreur = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cle_integration', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $libelle = trim((string) $request->request->get('libelle', ''));
            $droits = array_values(array_filter((array) $request->request->all('droits'), 'is_string'));
            if ('' === $libelle || [] === array_intersect($droits, array_keys(CleIntegration::DROITS))) {
                $erreur = 'Indiquez un nom et au moins un droit.';
            } else {
                [$cle, $secret] = $this->cles->creer($libelle, $droits);
                $this->addFlash('success', \sprintf('Clé « %s » créée. Copiez son secret maintenant : il ne sera plus affiché.', $cle->getLibelle()));
            }
        }

        return $this->render('admin/integration/index.html.twig', [
            'cles' => $this->em->getRepository(CleIntegration::class)->findBy([], ['createdAt' => 'DESC']),
            'droits' => CleIntegration::DROITS,
            'secret' => $secret,
            'erreur' => $erreur,
        ]);
    }

    #[Route('/{id}/revoquer', name: 'revoquer', requirements: ['id' => '[0-9a-f-]{36}'], methods: ['POST'])]
    public function revoquer(Request $request, string $id): Response
    {
        $cle = $this->em->getRepository(CleIntegration::class)->find(Uuid::fromString($id)) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('revoquer'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->cles->revoquer($cle);
        $this->addFlash('success', \sprintf('Clé « %s » révoquée : elle ne donne plus accès à l’API.', $cle->getLibelle()));

        return $this->redirectToRoute('admin_integration_index');
    }
}
