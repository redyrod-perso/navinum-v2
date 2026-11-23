/** Clé de stockage du joueur conservé sur l'appareil. */
export const VISITEUR_STORAGE_KEY = 'navinum.visiteur';

/**
 * Joueur conservé sur l'appareil (profil public, jeton, secret d'appareil).
 *
 * Seul endroit de la librairie qui touche au stockage du navigateur : les interactifs n'y accèdent jamais
 * directement. Le stockage se règle avec l'option `storage` de NavinumAPI (`localStorage` par défaut,
 * `null` pour ne rien conserver). Toutes les opérations tolèrent un stockage indisponible ou plein.
 *
 * @class
 */
export class Stockage {
    /**
     * @param {Config} config - Configuration de la librairie (`config.storage`)
     */
    constructor(config) {
        this.config = config;
    }

    /**
     * Joueur conservé, ou null (aucun, stockage indisponible ou illisible).
     * @returns {Object|null}
     */
    lire() {
        const storage = this.config.storage;
        if (!storage) {
            return null;
        }
        try {
            return JSON.parse(storage.getItem(VISITEUR_STORAGE_KEY) || 'null');
        } catch (error) {
            return null;
        }
    }

    /**
     * @param {Object} joueur
     */
    ecrire(joueur) {
        try {
            this.config.storage?.setItem(VISITEUR_STORAGE_KEY, JSON.stringify(joueur));
        } catch (error) { /* stockage indisponible ou plein : le joueur ne sera pas retrouvé */ }
    }

    effacer() {
        try {
            this.config.storage?.removeItem(VISITEUR_STORAGE_KEY);
        } catch (error) { /* stockage indisponible */ }
    }
}

export default Stockage;
