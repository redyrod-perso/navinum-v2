<?php

namespace App\Gamification;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Langage d'expression des règles de gamification, volontairement borné.
 *
 * - Les variables sont uniquement les données de l'événement (scalaires et tableaux) : aucun objet,
 *   donc aucun appel de méthode possible.
 * - Les fonctions par défaut d'ExpressionLanguage (`constant()`, `enum()`) sont retirées ;
 *   seules `min`, `max`, `round`, `abs` sont disponibles.
 *
 * Exemples : `score * 10`, `score == total`, `victoire and duree < 60`, `max(0, score - erreurs)`.
 */
final class SafeExpressionLanguage extends ExpressionLanguage
{
    public const MAX_LENGTH = 200;

    protected function registerFunctions(): void
    {
        foreach (['min', 'max', 'round', 'abs'] as $function) {
            $this->addFunction(ExpressionFunction::fromPhp($function));
        }
    }
}
