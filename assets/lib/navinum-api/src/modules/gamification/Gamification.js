/**
 * Gains renvoyés par Navinum pour un événement ou une médaille demandée.
 * @typedef {Object} Gains
 * @property {Array<{typologie: string, points: number}>} xp - XP gagnés par cet événement
 * @property {Array<{libelle: string, description: (string|null), image: (string|null)}>} medailles - Nouvelles médailles
 * @property {number} totalXp - Total d'XP du joueur
 */

/**
 * Module de gamification, sans affichage : envoie les événements de la partie à Navinum, rend les XP et
 * médailles gagnés selon les règles de l'interactif (définies dans l'admin ou le manifeste) et les cumule.
 *
 * Fonctionne uniquement quand l'interactif est lancé par Navinum (`await api.launch()`) avec un joueur
 * identifié (`api.auth.connecter()`) ; sinon les appels sont sans effet et rendent `null` (mode autonome).
 * **Jamais bloquant** : une erreur est journalisée (`api.onError`) et le jeu continue.
 *
 * @class
 * @example
 * await api.launch();
 * await api.auth.connecter({ pseudo: 'Léa' });
 * api.gamification.onGains(gains => afficherBadge(gains));          // à chaque gain
 * await api.gamification.evenement('quiz.completed', { score: 8, total: 10 });
 * await api.gamification.demanderMedaille('Explorateur');           // si « attribuable » par l'interactif
 * api.gamification.bilan();   // { xp: 80, medailles: [...], totalXp: 240 } depuis l'ouverture de la page
 */
export class Gamification {
    /**
     * @param {NavinumAPI} api - Instance de la librairie
     */
    constructor(api) {
        this.api = api;
        this.ecouteurs = [];
        this.cumul = { xp: 0, medailles: [], totalXp: null };
    }

    /**
     * Envoie un événement de la partie (type déclaré dans le manifeste, ex. `quiz.completed`).
     *
     * @param {string} type - Type d'événement
     * @param {Object} [payload={}] - Données de l'événement (variables des règles : `score`, `total`…)
     * @returns {Promise<Gains|null>} Les gains, ou null (mode autonome, joueur non identifié, erreur)
     */
    async evenement(type, payload = {}) {
        const ctx = this.api.contexte;
        if (!ctx) {
            return null;
        }
        try {
            const res = await ctx.track(type, payload);
            return this.enregistrer(res && res.gamification ? res.gamification : null);
        } catch (error) {
            this.api.config.log(`Événement ${type} non envoyé`, error);
            return null;
        }
    }

    /**
     * Demande une médaille pour le joueur (seulement les médailles « attribuables » par l'interactif).
     * Contrairement à {@link Gamification#evenement}, un refus est levé (erreur décrite par le module
     * d'erreurs : `MEDAILLE_NON_ATTRIBUABLE`, `NON_IDENTIFIE`…).
     *
     * @param {string} libelle - Libellé exact de la médaille
     * @returns {Promise<{medaille: Object, nouvelle: boolean, totalXp: number}|null>} null en mode autonome
     */
    async demanderMedaille(libelle) {
        const ctx = this.api.contexte;
        if (!ctx) {
            return null;
        }
        const res = await ctx.demanderMedaille(libelle);
        this.enregistrer({ xp: [], medailles: res.nouvelle ? [res.medaille] : [], totalXp: res.totalXp });
        return res;
    }

    /**
     * Appelle `callback(gains)` à chaque gain (XP ou nouvelle médaille).
     * @param {function(Gains): void} callback
     * @returns {function(): void} Fonction de désabonnement
     */
    onGains(callback) {
        this.ecouteurs.push(callback);
        return () => { this.ecouteurs = this.ecouteurs.filter(c => c !== callback); };
    }

    /**
     * Gains cumulés depuis l'ouverture de la page.
     * @returns {{xp: number, medailles: Array<Object>, totalXp: (number|null)}}
     */
    bilan() {
        return { xp: this.cumul.xp, medailles: [...this.cumul.medailles], totalXp: this.cumul.totalXp };
    }

    /**
     * @param {Gains|null} gains
     * @returns {Gains|null}
     * @private
     */
    enregistrer(gains) {
        if (!gains) {
            return null;
        }
        const xp = (gains.xp || []).reduce((total, gain) => total + (gain.points || 0), 0);
        this.cumul.xp += xp;
        this.cumul.medailles.push(...(gains.medailles || []));
        if (typeof gains.totalXp === 'number') {
            this.cumul.totalXp = gains.totalXp;
        }
        if (xp > 0 || (gains.medailles || []).length > 0) {
            for (const ecouteur of this.ecouteurs) {
                try { ecouteur(gains); } catch (e) { /* un écouteur ne bloque pas le jeu */ }
            }
        }
        return gains;
    }
}

export default Gamification;
