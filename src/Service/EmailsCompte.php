<?php

namespace App\Service;

use App\Entity\Visiteur;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mails des comptes visiteurs (#71) : vérification de l'adresse et mot de passe oublié.
 *
 * Envoi par Symfony Mailer, **en arrière-plan** (message Messenger `SendEmailMessage` → worker) : la réponse de
 * l'API ne dépend ni du fournisseur (Brevo en production, Mailpit en local) ni de l'existence du compte.
 * Sans `MAILER_DSN` (null://null), rien n'est envoyé. Un échec d'envoi n'interrompt jamais le visiteur.
 */
final class EmailsCompte
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LienCompte $liens,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $expediteur,
        #[Autowire(service: 'limiter.email_verification')] private readonly RateLimiterFactory $limiteVerification,
    ) {
    }

    /**
     * Lien de vérification de l'adresse (à l'inscription, à la connexion d'un compte non vérifié, sur demande).
     * Au plus un envoi par compte et par période (rate limiter `email_verification`), sauf `$forcer`.
     *
     * @return bool vrai si un e-mail est parti
     */
    public function verification(Visiteur $visiteur, bool $forcer = false): bool
    {
        if (null === $visiteur->getEmail() || null !== $visiteur->getEmailVerifieAt()) {
            return false;
        }
        $limite = $this->limiteVerification->create($visiteur->getId()->toRfc4122());
        if (!$limite->consume()->isAccepted() && !$forcer) {
            return false;
        }

        return $this->envoyer($visiteur, 'Confirmez votre adresse e-mail', 'emails/verification', [
            'lien' => $this->urls->generate('compte_verifier_email', ['jeton' => $this->liens->creer(LienCompte::VERIFICATION, $visiteur)], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    /** Lien de réinitialisation du mot de passe (valable 1 h, une seule fois). */
    public function reinitialisation(Visiteur $visiteur): bool
    {
        return $this->envoyer($visiteur, 'Réinitialisation de votre mot de passe', 'emails/mot_de_passe', [
            'lien' => $this->urls->generate('compte_mot_de_passe', ['jeton' => $this->liens->creer(LienCompte::MOT_DE_PASSE, $visiteur)], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function envoyer(Visiteur $visiteur, string $sujet, string $modele, array $contexte): bool
    {
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(Address::create($this->expediteur))
                ->to(new Address((string) $visiteur->getEmail()))
                ->subject($sujet)
                ->htmlTemplate($modele.'.html.twig')
                ->textTemplate($modele.'.txt.twig')
                ->context($contexte + ['pseudo' => VisiteurToken::pseudo($visiteur)]));

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('E-mail de compte non envoyé', ['modele' => $modele, 'erreur' => $e->getMessage()]);

            return false;
        }
    }
}
