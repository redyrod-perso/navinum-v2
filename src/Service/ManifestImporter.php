<?php

namespace App\Service;

use App\Entity\Interactif;
use App\Entity\Medaille;
use App\Gamification\RulesSchema;
use App\Repository\InteractifRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre un interactif à partir de son manifeste `navinum.json`.
 *
 * Format attendu :
 * {
 *   "libelle": "Quiz", "version": "1.0.0", "editeur": "…", "description": "…",
 *   "source_type": "html5", "url": "/quiz/index.html",
 *   "events": [{"type": "quiz.completed", "description": "…"}],
 *   "configSchema": { JSON Schema avec valeurs default },
 *   "gamification": {
 *     "regles": { "xp": […], "medailles": […] },            // règles par défaut (voir RulesSchema)
 *     "medailles": [{ "libelle": "Sans faute", "description": "…", "condition": "…", "unique": true }]
 *   }
 * }
 * L'interactif est retrouvé par son libellé (créé s'il n'existe pas).
 */
final class ManifestImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InteractifRepository $interactifs,
    ) {
    }

    /**
     * @return array{0: Interactif, 1: bool} l'interactif et true s'il vient d'être créé
     *
     * @throws \InvalidArgumentException si le manifeste est invalide
     */
    public function import(string $json, ?Interactif $target = null): array
    {
        $manifest = json_decode($json, true);
        if (!\is_array($manifest)) {
            throw new \InvalidArgumentException('Le manifeste n\'est pas un JSON valide : '.json_last_error_msg());
        }
        $libelle = trim((string) ($manifest['libelle'] ?? ''));
        if ('' === $libelle) {
            throw new \InvalidArgumentException('Le manifeste doit contenir "libelle".');
        }
        $schema = $manifest['configSchema'] ?? null;
        if (null !== $schema && (!\is_array($schema) || !ConfigSchema::isSchema($schema))) {
            throw new \InvalidArgumentException('"configSchema" doit être un JSON Schema (objet avec "type": "object").');
        }

        $gamification = $manifest['gamification'] ?? null;
        if (null !== $gamification) {
            if (!\is_array($gamification)) {
                throw new \InvalidArgumentException('"gamification" doit être un objet { regles, medailles }.');
            }
            $errors = RulesSchema::validate($gamification['regles'] ?? ['xp' => [], 'medailles' => []]);
            if ([] !== $errors) {
                throw new \InvalidArgumentException('Règles de gamification invalides : '.implode(' ; ', $errors));
            }
        }

        $interactif = $target ?? $this->interactifs->findOneBy(['libelle' => $libelle]);
        $created = null === $interactif;
        $interactif ??= new Interactif();

        $interactif->setLibelle($libelle);
        foreach (['version' => 'setVersion', 'editeur' => 'setEditeur', 'description' => 'setDescription',
            'synopsis' => 'setSynopsis', 'source_type' => 'setSourceType', 'url' => 'setUrlFichierInteractif',
            'logo' => 'setLogo', 'categorie' => 'setCategorie'] as $key => $setter) {
            if (isset($manifest[$key]) && \is_string($manifest[$key])) {
                $interactif->$setter($manifest[$key]);
            }
        }
        if (null !== $schema) {
            $interactif->setVariable(json_encode($schema, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        }
        if (isset($manifest['events']) && \is_array($manifest['events'])) {
            // Le champ V1 "explications_resultats" décrit comment lire les résultats : on y documente les événements.
            $lines = array_map(
                static fn ($e) => \is_array($e) ? '- '.($e['type'] ?? '?').' : '.($e['description'] ?? '') : '- '.$e,
                $manifest['events'],
            );
            $interactif->setExplicationsResultats("Événements émis :\n".implode("\n", $lines));
        }

        $this->em->persist($interactif);

        if (null !== $gamification) {
            // Règles par défaut : uniquement si l'admin n'en a pas encore défini (jamais d'écrasement)
            if (null === $interactif->getGamification() && isset($gamification['regles'])) {
                $interactif->setGamification(RulesSchema::normalize($gamification['regles']));
            }
            // Médailles déclarées : créées si absentes (rattachées à l'interactif)
            foreach ($gamification['medailles'] ?? [] as $definition) {
                $nom = \is_array($definition) ? trim((string) ($definition['libelle'] ?? '')) : '';
                if ('' === $nom || null !== $this->em->getRepository(Medaille::class)->findOneBy(['libelle' => $nom])) {
                    continue;
                }
                $this->em->persist((new Medaille())
                    ->setLibelle($nom)
                    ->setDescription(isset($definition['description']) ? (string) $definition['description'] : null)
                    ->setConditionObtention(isset($definition['condition']) ? (string) $definition['condition'] : null)
                    ->setIsUnique((bool) ($definition['unique'] ?? true))
                    ->setInteractif($interactif));
            }
        }

        $this->em->flush();

        return [$interactif, $created];
    }
}
