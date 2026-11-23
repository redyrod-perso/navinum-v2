<?php

namespace App\Gamification;

use App\Service\ConfigSchema;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Format des règles de gamification d'un interactif (Interactif::gamification).
 *
 * {
 *   "xp": [
 *     { "evenement": "quiz.completed", "points": "score * 10", "typologie": "Joueur", "si": "score > 0" }
 *   ],
 *   "medailles": [
 *     { "medaille": "Sans faute", "evenement": "quiz.completed", "si": "score == total" }
 *   ],
 *   "attribuables": ["Explorateur"],
 *   "scoreSession": ["quiz.completed"]
 * }
 *
 * - `evenement` : type exact (`quiz.completed`) ou préfixe suivi de `*` (`quiz.*`).
 * - `points` : nombre ou expression sur les données de l'événement (résultat arrondi, borné à 0..100 000).
 * - `si` : condition facultative (expression).
 * - `typologie` : facultative, « Général » par défaut.
 * - `medaille` : libellé d'une médaille existante (créée dans l'admin ou par le manifeste).
 * - `attribuables` : médailles que l'interactif peut demander lui-même (`POST /api/launch/{jeton}/medailles`) ;
 *   aucune par défaut (anti-triche : une médaille absente de cette liste est refusée).
 * - `scoreSession` : événements de fin de partie multijoueur dont `score`, `total` et `victoire` sont repris de
 *   la session de jeu contrôlée par le serveur (et non de ce que déclare le navigateur) ; voir SessionScoreVerifier.
 *
 * Ce schéma sert à la fois au formulaire généré de l'admin et à la validation côté serveur.
 */
final class RulesSchema
{
    public static function schema(): array
    {
        $expression = ['type' => 'string', 'maxLength' => SafeExpressionLanguage::MAX_LENGTH];

        return [
            'type' => 'object',
            'title' => 'Règles de gamification',
            'properties' => [
                'xp' => [
                    'type' => 'array',
                    'title' => "Points d'expérience",
                    'items' => [
                        'type' => 'object',
                        'title' => 'Règle XP',
                        'required' => ['evenement', 'points'],
                        'properties' => [
                            'evenement' => ['type' => 'string', 'title' => 'Événement', 'minLength' => 1, 'description' => 'Ex. quiz.completed, ou quiz.* pour tous les événements du quiz'],
                            'points' => ['type' => 'string', 'title' => 'Points', 'minLength' => 1, 'maxLength' => SafeExpressionLanguage::MAX_LENGTH, 'description' => 'Nombre ou calcul sur les données de l\'événement, ex. score * 10'],
                            'typologie' => ['type' => 'string', 'title' => 'Typologie', 'description' => 'Facultative (« Général » par défaut), ex. Joueur, Chercheur'],
                            'si' => $expression + ['title' => 'Condition', 'description' => 'Facultative, ex. score > 0'],
                        ],
                    ],
                    'default' => [],
                ],
                'medailles' => [
                    'type' => 'array',
                    'title' => 'Médailles',
                    'items' => [
                        'type' => 'object',
                        'title' => 'Règle médaille',
                        'required' => ['medaille', 'evenement'],
                        'properties' => [
                            'medaille' => ['type' => 'string', 'title' => 'Médaille', 'minLength' => 1, 'description' => 'Libellé exact d\'une médaille (Admin › Médailles)'],
                            'evenement' => ['type' => 'string', 'title' => 'Événement', 'minLength' => 1],
                            'si' => $expression + ['title' => 'Condition', 'description' => 'Ex. score == total, ou victoire'],
                        ],
                    ],
                    'default' => [],
                ],
                'attribuables' => [
                    'type' => 'array',
                    'title' => "Médailles attribuables par l'interactif",
                    'description' => "Médailles que l'interactif peut demander lui-même via l'API (aucune par défaut).",
                    'items' => ['type' => 'string', 'title' => 'Médaille', 'minLength' => 1],
                    'default' => [],
                ],
                'scoreSession' => [
                    'type' => 'array',
                    'title' => 'Scores contrôlés par le serveur',
                    'description' => 'Événements de fin de partie multijoueur dont le score est repris de la session de jeu (anti-triche), ex. quiz.completed.',
                    'items' => ['type' => 'string', 'title' => 'Événement', 'minLength' => 1],
                    'default' => [],
                ],
            ],
        ];
    }

    /** Normalise des règles saisies : `points` numériques convertis en texte (ex. 50 → "50"). */
    public static function normalize(mixed $rules): mixed
    {
        if (!\is_array($rules)) {
            return $rules;
        }
        foreach ($rules['xp'] ?? [] as $i => $rule) {
            if (\is_array($rule) && (\is_int($rule['points'] ?? null) || \is_float($rule['points'] ?? null))) {
                $rules['xp'][$i]['points'] = (string) $rule['points'];
            }
        }

        return $rules;
    }

    /**
     * Valide des règles (après normalisation) : structure (JSON Schema) puis syntaxe de chaque expression.
     *
     * @return list<string> messages d'erreur (vide si valide)
     */
    public static function validate(mixed $rules, ?SafeExpressionLanguage $language = null): array
    {
        if (!\is_array($rules)) {
            return ['Les règles doivent être un objet JSON'];
        }
        $rules = self::normalize($rules);
        $errors = ConfigSchema::validate(self::schema(), $rules);
        if ([] !== $errors) {
            return $errors;
        }

        $language ??= new SafeExpressionLanguage();
        $check = function (string $path, mixed $expression) use ($language, &$errors): void {
            if (!\is_string($expression) || '' === trim($expression)) {
                return;
            }
            try {
                $language->lint($expression, [], Parser::IGNORE_UNKNOWN_VARIABLES);
            } catch (SyntaxError $e) {
                $errors[] = "$path : expression invalide ({$e->getMessage()})";
            }
        };
        foreach ($rules['xp'] ?? [] as $i => $rule) {
            $check("xp[$i].points", $rule['points'] ?? null);
            $check("xp[$i].si", $rule['si'] ?? null);
        }
        foreach ($rules['medailles'] ?? [] as $i => $rule) {
            $check("medailles[$i].si", $rule['si'] ?? null);
        }

        return $errors;
    }
}
