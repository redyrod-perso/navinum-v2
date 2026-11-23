<?php

namespace App\Gamification;

use App\Entity\LaunchToken;
use App\Service\QuizSessions;

/**
 * Contrôle anti-triche des événements de fin de partie multijoueur.
 *
 * Pour les événements listés dans `scoreSession` des règles de l'interactif (ex. `quiz.completed`), le score
 * déclaré par le navigateur est remplacé par celui de la session de jeu, que le serveur contrôle déjà point par
 * point (+1 au plus par bonne réponse, pendant la partie) :
 * - `score` : score du joueur dans la session (`payload.groupe`, `payload.joueur`) ;
 * - `total` : nombre de questions du thème joué, d'après la configuration du lancement ;
 * - `victoire` : score >= total ; `joueurs` : nombre de joueurs de la session ;
 * - `verifie` : true. Session ou joueur introuvable : score 0, pas de victoire, `verifie` false.
 *
 * Les autres événements sont transmis tels quels.
 */
final class SessionScoreVerifier
{
    public function __construct(private readonly QuizSessions $sessions)
    {
    }

    public function verifier(LaunchToken $token, string $type, array $payload): array
    {
        $types = $token->getInteractif()->getGamification()['scoreSession'] ?? [];
        $concerne = false;
        foreach (\is_array($types) ? $types : [] as $pattern) {
            $concerne = $concerne || (\is_string($pattern) && GamificationEngine::matches($pattern, $type));
        }
        if (!$concerne) {
            return $payload;
        }

        $groupe = \is_string($payload['groupe'] ?? null) ? $payload['groupe'] : null;
        $nom = \is_string($payload['joueur'] ?? null) ? $payload['joueur'] : null;
        $session = null !== $groupe && '' !== $groupe ? $this->sessions->get($groupe) : null;
        $joueur = null;
        foreach ($session['players'] ?? [] as $player) {
            if (null !== $nom && ($player['name'] ?? null) === $nom) {
                $joueur = $player;
            }
        }
        if (null === $joueur) {
            return ['score' => 0, 'victoire' => false, 'verifie' => false] + $payload;
        }

        $score = max(0, (int) ($joueur['score'] ?? 0));
        $total = $this->total($token, $session);

        return [
            'score' => $score,
            'total' => $total ?? ($payload['total'] ?? null),
            'victoire' => null !== $total && $total > 0 && $score >= $total,
            'joueurs' => \count($session['players'] ?? []),
            'verifie' => null !== $total,
        ] + $payload;
    }

    /** Nombre de questions de la partie : questions du thème joué, limitées à `nbQuestions` de la configuration. */
    private function total(LaunchToken $token, array $session): ?int
    {
        $config = $token->getConfig();
        foreach (\is_array($config['themes'] ?? null) ? $config['themes'] : [] as $theme) {
            if (\is_array($theme) && (string) ($theme['id'] ?? '') === (string) ($session['theme'] ?? '') && \is_array($theme['questions'] ?? null)) {
                $n = \count($theme['questions']);
                $max = is_numeric($config['nbQuestions'] ?? null) ? (int) $config['nbQuestions'] : $n;

                return min($n, max(1, $max));
            }
        }

        return null;
    }
}
