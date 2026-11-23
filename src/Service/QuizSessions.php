<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stockage des sessions de jeu multijoueur (une par groupe) : fichiers JSON de `var/cache/sessions/`, nommés
 * par un hachage de l'identifiant (le nom du groupe saisi par les joueurs) pour que n'importe quel nom reste
 * un simple fichier de ce dossier.
 *
 * Partagé par l'API des sessions (SessionController) et le contrôle des événements de fin de partie
 * (QuizCompletedVerifier).
 */
final class QuizSessions
{
    private const DIR = '/var/cache/sessions';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function get(string $sessionId): ?array
    {
        $file = $this->file($sessionId);
        if (!is_file($file)) {
            return null;
        }
        $session = json_decode((string) file_get_contents($file), true);

        return \is_array($session) ? $session : null;
    }

    public function save(string $sessionId, array $session): void
    {
        $session['lastUpdate'] = time();
        file_put_contents($this->file($sessionId), json_encode($session));
    }

    public function delete(string $sessionId): void
    {
        $file = $this->file($sessionId);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /** Supprime toutes les sessions ; renvoie leur nombre. */
    public function clear(): int
    {
        $count = 0;
        foreach (glob($this->dir().'/*.json') ?: [] as $file) {
            if (is_file($file) && unlink($file)) {
                ++$count;
            }
        }

        return $count;
    }

    private function file(string $sessionId): string
    {
        return $this->dir().'/'.hash('sha256', $sessionId).'.json';
    }

    private function dir(): string
    {
        $path = $this->projectDir.self::DIR;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }
}
