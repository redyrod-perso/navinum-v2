import { UnauthorizedError } from '../../core/Errors.js';

/** Le jeton est prolongé quand il lui reste moins de 15 minutes (activité du joueur). */
export const MARGE_RENOUVELLEMENT = 15 * 60 * 1000;

/**
 * Mode de connexion « invité » : un pseudo suffit.
 *
 * - session encore valide → réutilisée ;
 * - session expirée (30 min d'inactivité, 12 h au plus) → **même profil** repris sur cet appareil grâce au
 *   secret d'appareil conservé (ou échange d'un ancien jeton `nvv1`) ;
 * - sinon (autre pseudo, appareil révoqué) → nouveau visiteur invité.
 *
 * Les appels HTTP passent par `api.visiteurs` ; la conservation sur l'appareil par {@link Stockage}.
 * Les futurs modes (compte en ligne, OAuth, badge RFID) suivront le même contrat :
 * `connecter(options)` et `entretenir(joueur)`.
 *
 * @class
 */
export class ModeInvite {
    /**
     * @param {VisiteurAPI} visiteurs - Appels HTTP visiteur
     * @param {Stockage} stockage - Joueur conservé sur l'appareil
     */
    constructor(visiteurs, stockage) {
        this.visiteurs = visiteurs;
        this.stockage = stockage;
    }

    /**
     * Identifie le joueur.
     *
     * @param {Object} options
     * @param {string} options.pseudo - Pseudo saisi (2 à 40 caractères)
     * @param {boolean} [options.memoriser=true] - false : rien n'est conservé sur l'appareil (borne partagée)
     * @returns {Promise<Object>} `{ id, pseudo, anonyme, token, expiresAt, appareil }`
     * @throws {ValidationError} Pseudo invalide
     * @throws {NetworkError} API injoignable et aucun joueur conservé pour ce pseudo
     */
    async connecter({ pseudo, memoriser = true } = {}) {
        const value = typeof pseudo === 'string' ? pseudo.trim() : '';
        const saved = memoriser ? this.stockage.lire() : null;

        if (saved && saved.pseudo === value) {
            try {
                // 1. Session en cours encore valide
                if (saved.token && !ModeInvite.expire(saved)) {
                    await this.visiteurs.moi(saved.token);
                    return saved;
                }
            } catch (error) {
                if (!(error instanceof UnauthorizedError)) {
                    return saved; // API injoignable : on garde le joueur connu
                }
            }
            // 2. Session expirée : reprise sur cet appareil (même profil)
            const reprise = saved.appareil
                ? { pseudo: value, appareil: saved.appareil }
                : (saved.token && saved.token.startsWith('nvv1.') ? { pseudo: value, ancienJeton: saved.token } : null);
            if (reprise) {
                try {
                    const res = await this.visiteurs.reprendre(reprise);
                    const joueur = { ...res.visiteur, token: res.token, expiresAt: res.expiresAt, appareil: res.appareil || saved.appareil };
                    this.stockage.ecrire(joueur);
                    return joueur;
                } catch (error) {
                    if (!(error instanceof UnauthorizedError)) {
                        throw error;
                    }
                }
            }
        }

        // 3. Nouveau visiteur invité
        const { visiteur, token, expiresAt, appareil } = await this.visiteurs.inviter(value);
        const joueur = { ...visiteur, token, expiresAt, appareil };
        if (memoriser) {
            this.stockage.ecrire(joueur);
        }
        return joueur;
    }

    /**
     * Prolonge la session si elle expire bientôt (activité du joueur) ; sans effet sinon.
     *
     * @param {Object} joueur - `{ token, expiresAt, … }`
     * @param {Object} [options={}]
     * @param {boolean} [options.memoriser=true]
     * @returns {Promise<Object>} Le joueur, avec son jeton prolongé le cas échéant
     */
    async entretenir(joueur, { memoriser = true } = {}) {
        if (!joueur || !joueur.token || !joueur.expiresAt || ModeInvite.expire(joueur)) {
            return joueur;
        }
        if (Date.parse(joueur.expiresAt) - Date.now() > MARGE_RENOUVELLEMENT) {
            return joueur;
        }
        const { token, expiresAt } = await this.visiteurs.renouveler(joueur.token);
        const prolonge = { ...joueur, token, expiresAt };
        if (memoriser) {
            this.stockage.ecrire(prolonge);
        }
        return prolonge;
    }

    /**
     * Le jeton du joueur a-t-il expiré (horloge locale, marge de 30 s) ?
     * @param {{expiresAt?: string}} joueur
     * @returns {boolean}
     */
    static expire(joueur) {
        if (!joueur || !joueur.expiresAt) {
            return true; // ancien jeton sans date : à reprendre
        }
        return Date.parse(joueur.expiresAt) - Date.now() < 30 * 1000;
    }
}

export default ModeInvite;
