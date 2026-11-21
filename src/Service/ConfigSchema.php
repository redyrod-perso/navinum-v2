<?php

namespace App\Service;

/**
 * Outils autour du JSON Schema de configuration d'un interactif (champ Interactif::variable).
 *
 * L'interactif décrit lui-même ses réglages : le schéma sert à la fois
 *  - à générer le formulaire générique de l'admin,
 *  - à fournir la configuration par défaut (mots-clés `default`),
 *  - à valider la configuration saisie pour un parcours.
 *
 * Sous-ensemble supporté (draft-07) : type, properties, required, items, enum,
 * minimum, maximum, minLength, maxLength, minItems, maxItems, default.
 */
final class ConfigSchema
{
    /** Le tableau ressemble-t-il à un JSON Schema (et non à un JSON de valeurs) ? */
    public static function isSchema(array $data): bool
    {
        return isset($data['$schema'])
            || (isset($data['type']) && \is_string($data['type']) && isset($data['properties']))
            || (isset($data['type']) && 'object' === $data['type']);
    }

    /**
     * Valeurs par défaut décrites par le schéma.
     * Un `default` explicite l'emporte ; sinon un objet est construit à partir des propriétés.
     */
    public static function defaults(array $schema): mixed
    {
        if (\array_key_exists('default', $schema)) {
            return $schema['default'];
        }
        if ('object' === ($schema['type'] ?? null) || isset($schema['properties'])) {
            $out = [];
            foreach ($schema['properties'] ?? [] as $name => $sub) {
                if (!\is_array($sub)) {
                    continue;
                }
                $value = self::defaults($sub);
                if (null !== $value) {
                    $out[$name] = $value;
                }
            }

            return $out;
        }

        return null;
    }

    /**
     * Fusion récursive : les objets sont fusionnés clé par clé, les listes et scalaires sont remplacés.
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (\is_array($value) && !array_is_list($value)
                && isset($base[$key]) && \is_array($base[$key]) && !array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Valide une donnée contre le schéma.
     *
     * @return list<string> messages d'erreur (vide si valide), préfixés par le chemin (ex. "themes[0].libelle")
     */
    public static function validate(array $schema, mixed $data, string $path = ''): array
    {
        $errors = [];
        $label = '' === $path ? 'configuration' : $path;

        if (isset($schema['type']) && !self::matchesType($schema['type'], $data)) {
            $expected = \is_array($schema['type']) ? implode('|', $schema['type']) : $schema['type'];

            return ["$label : type attendu « $expected »"];
        }

        if (isset($schema['enum']) && \is_array($schema['enum']) && !\in_array($data, $schema['enum'], true)) {
            $errors[] = "$label : valeur non autorisée";
        }

        if (\is_int($data) || \is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[] = "$label : doit être ≥ {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[] = "$label : doit être ≤ {$schema['maximum']}";
            }
        }

        if (\is_string($data)) {
            $len = mb_strlen($data);
            if (isset($schema['minLength']) && $len < $schema['minLength']) {
                $errors[] = "$label : au moins {$schema['minLength']} caractère(s)";
            }
            if (isset($schema['maxLength']) && $len > $schema['maxLength']) {
                $errors[] = "$label : au plus {$schema['maxLength']} caractère(s)";
            }
        }

        if (\is_array($data) && array_is_list($data) && ($schema['type'] ?? null) === 'array') {
            $count = \count($data);
            if (isset($schema['minItems']) && $count < $schema['minItems']) {
                $errors[] = "$label : au moins {$schema['minItems']} élément(s)";
            }
            if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
                $errors[] = "$label : au plus {$schema['maxItems']} élément(s)";
            }
            if (isset($schema['items']) && \is_array($schema['items'])) {
                foreach ($data as $i => $item) {
                    $errors = [...$errors, ...self::validate($schema['items'], $item, "{$path}[{$i}]")];
                }
            }
        }

        if (\is_array($data) && (!array_is_list($data) || [] === $data) && isset($schema['properties'])) {
            foreach ($schema['required'] ?? [] as $required) {
                if (!\array_key_exists($required, $data)) {
                    $errors[] = ('' === $path ? '' : "$path.")."$required : obligatoire";
                }
            }
            foreach ($schema['properties'] as $name => $sub) {
                if (\array_key_exists($name, $data) && \is_array($sub)) {
                    $errors = [...$errors, ...self::validate($sub, $data[$name], '' === $path ? $name : "$path.$name")];
                }
            }
        }

        return $errors;
    }

    private static function matchesType(string|array $type, mixed $data): bool
    {
        foreach ((array) $type as $t) {
            $ok = match ($t) {
                'object' => \is_array($data) && (!array_is_list($data) || [] === $data),
                'array' => \is_array($data) && array_is_list($data),
                'string' => \is_string($data),
                'integer' => \is_int($data),
                'number' => \is_int($data) || \is_float($data),
                'boolean' => \is_bool($data),
                'null' => null === $data,
                default => true,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
