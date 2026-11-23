<?php

namespace App\Security;

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
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authentificateur du pare-feu `api` (sans session) : un jeton visiteur Bearer valide (`nvv2.…`, session de
 * 30 min / 12 h, appareil non révoqué) authentifie la requête en ROLE_VISITEUR (`$this->getUser()` →
 * VisiteurUser). Tous les modes de connexion (invité, compte, OAuth, badge) délivrent ce même jeton.
 *
 * Tolérant : un jeton absent, expiré ou invalide n'interrompt pas la requête (elle continue sans
 * authentification) ; chaque route décide (401 `NON_IDENTIFIE` si elle exige un visiteur).
 */
final class VisiteurAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly VisiteurToken $tokens)
    {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) VisiteurToken::bearer($request), 'nvv2.');
    }

    public function authenticate(Request $request): Passport
    {
        $jeton = (string) VisiteurToken::bearer($request);

        return new SelfValidatingPassport(new UserBadge($jeton, function () use ($jeton) {
            $visiteur = $this->tokens->resolve($jeton);
            if (null === $visiteur) {
                throw new CustomUserMessageAuthenticationException('Jeton visiteur absent ou invalide');
            }

            return new VisiteurUser($visiteur);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null; // la requête continue sans visiteur authentifié
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(['error' => 'Authentification requise', 'code' => 'NON_IDENTIFIE'], 401);
    }
}
