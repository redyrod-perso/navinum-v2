<?php

namespace App\Controller\Api;

use App\Entity\Visiteur;
use App\Service\VisiteurToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Identification des visiteurs par les interactifs (prototype).
 *
 * - POST /api/visiteur/invite  {"pseudo": "Alice"} → crée un visiteur invité et renvoie son jeton
 * - GET  /api/visiteur/moi     (Authorization: Bearer <jeton>) → profil du visiteur
 *
 * Le jeton s'utilise ensuite avec POST /api/launch/{jeton}/visiteur pour relier une partie au visiteur.
 * Plus tard, le module d'authentification (compte en ligne, badge RFID) délivrera le même jeton.
 */
#[Route('/api/visiteur', name: 'api_visiteur_')]
final class VisiteurController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VisiteurToken $tokens,
    ) {
    }

    #[Route('/invite', name: 'invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $pseudo = \is_array($data) ? trim((string) ($data['pseudo'] ?? '')) : '';
        if (mb_strlen($pseudo) < 2 || mb_strlen($pseudo) > 40) {
            return $this->json(['error' => 'Le pseudo doit faire entre 2 et 40 caractères'], 400);
        }

        $visiteur = (new Visiteur())
            ->setPseudoSon($pseudo)
            ->setIsAnonyme(true)
            ->setType('invite');
        $this->em->persist($visiteur);
        $this->em->flush();

        return $this->json(['visiteur' => self::profile($visiteur), 'token' => $this->tokens->issue($visiteur)], 201);
    }

    #[Route('/moi', name: 'moi', methods: ['GET'])]
    public function moi(Request $request): JsonResponse
    {
        $visiteur = $this->tokens->fromRequest($request);
        if (null === $visiteur) {
            return $this->json(['error' => 'Jeton visiteur absent ou invalide'], 401);
        }

        return $this->json(['visiteur' => self::profile($visiteur)]);
    }

    /** Données publiques d'un visiteur : jamais d'email ni d'informations personnelles. */
    public static function profile(Visiteur $visiteur): array
    {
        return [
            'id' => (string) $visiteur->getId(),
            'pseudo' => $visiteur->getPseudoSon() ?? $visiteur->getPrenom(),
            'anonyme' => (bool) $visiteur->isAnonyme(),
        ];
    }
}
