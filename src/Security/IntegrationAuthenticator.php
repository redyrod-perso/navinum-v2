<?php

namespace App\Security;

use App\Service\ClesIntegration;
use App\Service\VisiteurToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Clé d'intégration Bearer (`nvk1.…`) sur le pare-feu `api` (#11). Strict, contrairement au jeton visiteur :
 * une clé inconnue ou révoquée est refusée immédiatement (401 `NON_IDENTIFIE`) — un outil externe doit le savoir.
 */
final class IntegrationAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ClesIntegration $cles)
    {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) VisiteurToken::bearer($request), ClesIntegration::PREFIX);
    }

    public function authenticate(Request $request): Passport
    {
        $secret = (string) VisiteurToken::bearer($request);

        return new SelfValidatingPassport(new UserBadge('integration', function () use ($secret) {
            $cle = $this->cles->verifier($secret);
            if (null === $cle) {
                throw new CustomUserMessageAuthenticationException('Clé d’intégration inconnue ou révoquée');
            }

            return new IntegrationUser($cle);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Clé d’intégration inconnue ou révoquée', 'code' => 'NON_IDENTIFIE'], 401);
    }
}
