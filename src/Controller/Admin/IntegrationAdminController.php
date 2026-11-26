<?php

namespace App\Controller\Admin;

use App\Entity\CleIntegration;
use App\Entity\Webhook;
use App\Entity\WebhookEnvoi;
use App\Webhook\Webhooks;
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
        private readonly Webhooks $webhooks,
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
            'webhooks' => $this->em->getRepository(Webhook::class)->findBy([], ['createdAt' => 'DESC']),
            'evenements' => Webhook::EVENEMENTS,
            'envois' => $this->em->getRepository(WebhookEnvoi::class)->findBy([], ['createdAt' => 'DESC'], 20),
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

    /** Nouveau webhook sortant (#10) : adresse http(s), événements cochés, secret de signature généré. */
    #[Route('/webhooks', name: 'webhook_creer', methods: ['POST'])]
    public function creerWebhook(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('webhook', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $libelle = trim((string) $request->request->get('libelle', ''));
        $url = trim((string) $request->request->get('url', ''));
        $evenements = array_values(array_intersect(array_keys(Webhook::EVENEMENTS), (array) $request->request->all('evenements')));
        $schema = parse_url($url, \PHP_URL_SCHEME);
        if ('' === $libelle || !\in_array($schema, ['http', 'https'], true) || false === filter_var($url, \FILTER_VALIDATE_URL) || [] === $evenements) {
            $this->addFlash('danger', 'Webhook : indiquez un nom, une adresse http(s) valide et au moins un événement.');

            return $this->redirectToRoute('admin_integration_index');
        }
        $webhook = new Webhook(mb_substr($libelle, 0, 120), $url, $evenements, 'whsec_'.bin2hex(random_bytes(24)));
        $this->em->persist($webhook);
        $this->em->flush();
        $this->addFlash('success', \sprintf('Webhook « %s » créé. Son secret de signature est visible dans la liste.', $webhook->getLibelle()));

        return $this->redirectToRoute('admin_integration_index');
    }

    #[Route('/webhooks/{id}/{action}', name: 'webhook_action', requirements: ['id' => '[0-9a-f-]{36}', 'action' => 'tester|activer|desactiver|supprimer'], methods: ['POST'])]
    public function actionWebhook(Request $request, string $id, string $action): Response
    {
        $webhook = $this->em->getRepository(Webhook::class)->find(Uuid::fromString($id)) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('webhook'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        match ($action) {
            'tester' => $this->webhooks->tester($webhook),
            'activer' => $webhook->setActif(true),
            'desactiver' => $webhook->setActif(false),
            'supprimer' => $this->em->remove($webhook),
        };
        $this->em->flush();
        $this->addFlash('success', match ($action) {
            'tester' => 'Envoi de test programmé : voir le journal ci-dessous.',
            'activer' => 'Webhook activé.',
            'desactiver' => 'Webhook désactivé : plus aucun envoi.',
            'supprimer' => 'Webhook supprimé.',
        });

        return $this->redirectToRoute('admin_integration_index');
    }
}
