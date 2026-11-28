<?php

namespace App\Controller;

use App\Service\CompteRefuse;
use App\Service\CompteVisiteur;
use App\Service\LienCompte;
use App\Service\VisiteurToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages publiques ouvertes depuis les e-mails des comptes visiteurs (#71). Le jeton du lien suffit (signé, à
 * durée limitée) : aucune session n'est ouverte ici, le visiteur se reconnecte ensuite dans l'interactif.
 */
#[Route('/compte', name: 'compte_')]
final class CompteController extends AbstractController
{
    public function __construct(
        private readonly LienCompte $liens,
        private readonly CompteVisiteur $comptes,
        private readonly VisiteurToken $tokens,
    ) {
    }

    #[Route('/email/{jeton}', name: 'verifier_email', methods: ['GET'])]
    public function verifierEmail(string $jeton): Response
    {
        $visiteur = $this->liens->verifier(LienCompte::VERIFICATION, $jeton);
        if (null !== $visiteur) {
            $this->comptes->confirmerEmail($visiteur);
        }

        return $this->render('compte/email.html.twig', ['ok' => null !== $visiteur], new Response(status: null !== $visiteur ? 200 : 400));
    }

    #[Route('/mot-de-passe/{jeton}', name: 'mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(Request $request, string $jeton): Response
    {
        $visiteur = $this->liens->verifier(LienCompte::MOT_DE_PASSE, $jeton);
        if (null === $visiteur) {
            return $this->render('compte/mot_de_passe.html.twig', ['etat' => 'invalide'], new Response(status: 400));
        }

        $erreur = null;
        if ($request->isMethod('POST')) {
            $motDePasse = (string) $request->request->get('motDePasse', '');
            if ($motDePasse !== (string) $request->request->get('confirmation', '')) {
                $erreur = 'Les deux mots de passe ne correspondent pas.';
            } else {
                try {
                    $this->comptes->reinitialiserMotDePasse($visiteur, $motDePasse, $this->tokens);

                    return $this->render('compte/mot_de_passe.html.twig', ['etat' => 'change']);
                } catch (CompteRefuse $e) {
                    $erreur = $e->getMessage();
                }
            }
        }

        return $this->render('compte/mot_de_passe.html.twig', [
            'etat' => 'formulaire',
            'erreur' => $erreur,
            'minimum' => CompteVisiteur::MOT_DE_PASSE_MIN,
        ], new Response(status: null !== $erreur ? 422 : 200));
    }
}
