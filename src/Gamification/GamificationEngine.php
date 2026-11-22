<?php

namespace App\Gamification;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Évalue les règles de gamification d'un interactif pour un événement donné.
 *
 * Logique pure (aucun accès base) : renvoie ce qui doit être attribué ; GamificationService
 * se charge de l'enregistrer. Une règle qui ne s'applique pas (événement différent, condition
 * fausse, variable absente, expression en erreur) est simplement ignorée : elle ne bloque jamais
 * l'interactif.
 */
final class GamificationEngine
{
    public const TYPOLOGIE_PAR_DEFAUT = 'Général';
    public const POINTS_MAX = 100000;

    private readonly SafeExpressionLanguage $language;

    public function __construct(private readonly LoggerInterface $logger = new NullLogger())
    {
        $this->language = new SafeExpressionLanguage();
    }

    /**
     * @param array|null $rules   règles de l'interactif (format RulesSchema)
     * @param string     $type    type d'événement (ex. quiz.completed)
     * @param array      $payload données de l'événement
     *
     * @return array{xp: list<array{typologie: string, points: int}>, medailles: list<string>}
     */
    public function evaluate(?array $rules, string $type, array $payload): array
    {
        $result = ['xp' => [], 'medailles' => []];
        if (null === $rules) {
            return $result;
        }
        $variables = $this->variables($type, $payload);

        foreach ($rules['xp'] ?? [] as $rule) {
            if (!\is_array($rule) || !$this->applies($rule, $type, $variables)) {
                continue;
            }
            $points = $this->points($rule['points'] ?? null, $variables);
            if ($points > 0) {
                $typologie = trim((string) ($rule['typologie'] ?? '')) ?: self::TYPOLOGIE_PAR_DEFAUT;
                $result['xp'][] = ['typologie' => $typologie, 'points' => $points];
            }
        }

        foreach ($rules['medailles'] ?? [] as $rule) {
            $libelle = \is_array($rule) ? trim((string) ($rule['medaille'] ?? '')) : '';
            if ('' !== $libelle && $this->applies($rule, $type, $variables) && !\in_array($libelle, $result['medailles'], true)) {
                $result['medailles'][] = $libelle;
            }
        }

        return $result;
    }

    /** `quiz.completed` correspond à `quiz.completed` et à `quiz.*`. */
    public static function matches(string $pattern, string $type): bool
    {
        $pattern = trim($pattern);
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($type, substr($pattern, 0, -1));
        }

        return $pattern === $type;
    }

    private function applies(array $rule, string $type, array $variables): bool
    {
        if (!self::matches((string) ($rule['evenement'] ?? ''), $type)) {
            return false;
        }
        $condition = $rule['si'] ?? null;
        if (null === $condition || '' === trim((string) $condition)) {
            return true;
        }

        return (bool) $this->expression((string) $condition, $variables);
    }

    private function points(mixed $points, array $variables): int
    {
        if (\is_int($points) || \is_float($points)) {
            $value = $points;
        } elseif (\is_string($points) && is_numeric(trim($points))) {
            $value = (float) $points;
        } else {
            $value = $this->expression((string) $points, $variables);
        }
        if (!\is_int($value) && !\is_float($value)) {
            return 0;
        }

        return (int) max(0, min(self::POINTS_MAX, round($value)));
    }

    /** Évalue une expression ; null si elle est trop longue, invalide ou utilise une variable absente. */
    private function expression(string $expression, array $variables): mixed
    {
        if (mb_strlen($expression) > SafeExpressionLanguage::MAX_LENGTH) {
            return null;
        }
        try {
            return $this->language->evaluate($expression, $variables);
        } catch (\Throwable $e) {
            $this->logger->info('Règle de gamification ignorée', ['expression' => $expression, 'erreur' => $e->getMessage()]);

            return null;
        }
    }

    /** Variables disponibles : `evenement` et les données de premier niveau de l'événement (scalaires et tableaux). */
    private function variables(string $type, array $payload): array
    {
        $variables = ['evenement' => $type];
        foreach ($payload as $name => $value) {
            if (\is_string($name) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) && (null === $value || \is_scalar($value) || \is_array($value))) {
                $variables[$name] = $value;
            }
        }

        return $variables;
    }
}
