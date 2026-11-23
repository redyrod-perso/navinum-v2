import { BaseResource } from './BaseResource.js';
import { ValidationError, UnauthorizedError } from '../core/Errors.js';

/** Clé de stockage du visiteur identifié sur l'appareil. */
export const VISITEUR_STORAGE_KEY = 'navinum.visiteur';

/**
 * Profil public d'un visiteur (jamais d'email ni d'information personnelle).
 * @typedef {Object} VisiteurProfil
 * @property {string} id - UUID du visiteur
 * @property {string} pseudo - Pseudo affiché
 * @property {boolean} anonyme - true pour un visiteur invité (sans compte)
 */

/**
 * Identification des visiteurs par les interactifs.
 *
 * Endpoints :
 * - `POST /api/visiteur/invite` `{ pseudo }` → `{ visiteur, token }` (201)
 * - `GET  /api/visiteur/moi` (Authorization: Bearer <jeton>) → `{ visiteur }`
 *
 * Le jeton visiteur (`nvv1.…`) se transmet ensuite à {@link LaunchAPI#identify} (ou `ctx.identify()`)
 * pour rattacher une partie au visiteur. Le futur module d'authentification (compte en ligne,
 * badge RFID) délivrera le même type de jeton : les interactifs n'auront rien à changer.
 *
 * Les opérations CRUD héritées de BaseResource (`/api/visiteurs`) sont réservées à l'admin (401 sinon).
 *
 * @class
 * @example
 * const { visiteur, token } = await api.visiteurs.inviter('Alice');
 * localStorage.setItem('navinum.visiteur', JSON.stringify({ ...visiteur, token }));
 */
export class VisiteurAPI extends BaseResource {
    constructor(client) {
        super(client, 'visiteur', { basePath: '/api/visiteurs' });
    }

    /**
     * Crée un visiteur invité (sans compte) à partir d'un pseudo.
     *
     * @param {string} pseudo - Pseudo, 2 à 40 caractères
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil: string}>} Le profil,
     *          le jeton visiteur (30 min, renouvelable) et le secret de l'appareil (pour reprendre le profil)
     * @throws {ValidationError} Si le pseudo est vide ou invalide (400)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * const { visiteur, token } = await api.visiteurs.inviter('Alice');
     */
    async inviter(pseudo) {
        const value = typeof pseudo === 'string' ? pseudo.trim() : '';
        if (value.length < 2 || value.length > 40) {
            throw new ValidationError('Le pseudo doit faire entre 2 et 40 caractères', { pseudo: 'invalide' });
        }
        return this.client.post('/api/visiteur/invite', { pseudo: value });
    }

    /**
     * Profil du visiteur correspondant à un jeton (vérifie aussi que le jeton est toujours valide).
     *
     * @param {string} token - Jeton visiteur (`nvv1.…`)
     * @returns {Promise<{visiteur: VisiteurProfil}>}
     * @throws {UnauthorizedError} Si le jeton est absent ou invalide (401)
     *
     * @example
     * const { visiteur } = await api.visiteurs.moi(saved.token);
     */
    async moi(token) {
        if (!token) {
            throw new ValidationError('Jeton visiteur manquant', { token: 'requis' });
        }
        return this.client.get('/api/visiteur/moi', { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Reprend le visiteur conservé sur cet appareil après expiration de sa session (30 min d'inactivité,
     * 12 h au plus) : même profil (XP, médailles) si le pseudo et le secret de l'appareil correspondent.
     *
     * @param {Object} data - `{ pseudo, appareil }` (secret d'appareil `nva1.…`), ou `{ pseudo, ancienJeton }`
     *        pour échanger un ancien jeton `nvv1` (renvoie alors aussi `appareil`)
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil?: string}>}
     * @throws {UnauthorizedError} code `NON_IDENTIFIE` : appareil inconnu ou révoqué, pseudo différent
     */
    async reprendre(data) {
        return this.client.post('/api/visiteur/reprendre', data);
    }

    /**
     * Prolonge une session encore valide de 30 min (12 h au plus depuis l'identification).
     *
     * @param {string} token - Jeton visiteur en cours
     * @returns {Promise<{token: string, expiresAt: string}>}
     * @throws {UnauthorizedError} code `NON_IDENTIFIE` : session expirée ou révoquée
     */
    async renouveler(token) {
        return this.client.post('/api/visiteur/renouveler', {}, { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Déconnecte cet appareil côté serveur (ses jetons deviennent invalides immédiatement). Idempotent.
     *
     * @param {string} token - Jeton visiteur en cours
     * @returns {Promise<void>}
     */
    async deconnexion(token) {
        await this.client.post('/api/visiteur/deconnexion', {}, { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Identifie le joueur : session en cours si elle est valide, sinon reprise du visiteur conservé sur cet
     * appareil (même pseudo : même profil), sinon nouveau visiteur invité.
     *
     * C'est la brique du module d'authentification {@link Auth} (`api.auth.connecter()`), à utiliser de
     * préférence. Le stockage se règle avec l'option `storage` de NavinumAPI (`localStorage` par défaut).
     *
     * @param {string} pseudo - Pseudo saisi par le joueur (2 à 40 caractères)
     * @param {Object} [options={}]
     * @param {boolean} [options.memoriser=true] - false : rien n'est conservé sur l'appareil (borne partagée)
     * @returns {Promise<VisiteurProfil & {token: string, expiresAt: string, appareil?: string}>}
     * @throws {ValidationError} Si le pseudo est invalide
     * @throws {NetworkError} Si l'API est injoignable et qu'aucun visiteur n'est conservé pour ce pseudo
     *
     * @example
     * const joueur = await api.visiteurs.identifier('Alice');
     */
    async identifier(pseudo, options = {}) {
        const memoriser = options.memoriser !== false;
        const value = typeof pseudo === 'string' ? pseudo.trim() : '';
        const saved = memoriser ? this.courant() : null;

        if (saved && saved.pseudo === value) {
            try {
                // 1. Session en cours encore valide
                if (saved.token && !VisiteurAPI.expire(saved)) {
                    await this.moi(saved.token);
                    return saved;
                }
            } catch (error) {
                if (!(error instanceof UnauthorizedError)) {
                    return saved; // API injoignable : on garde le visiteur connu
                }
            }
            // 2. Session expirée : reprise sur cet appareil (même profil)
            const reprise = saved.appareil
                ? { pseudo: value, appareil: saved.appareil }
                : (saved.token && saved.token.startsWith('nvv1.') ? { pseudo: value, ancienJeton: saved.token } : null);
            if (reprise) {
                try {
                    const res = await this.reprendre(reprise);
                    const joueur = { ...res.visiteur, token: res.token, expiresAt: res.expiresAt, appareil: res.appareil || saved.appareil };
                    this.store(joueur);
                    return joueur;
                } catch (error) {
                    if (!(error instanceof UnauthorizedError)) {
                        throw error;
                    }
                }
            }
        }

        // 3. Nouveau visiteur invité
        const { visiteur, token, expiresAt, appareil } = await this.inviter(value);
        const joueur = { ...visiteur, token, expiresAt, appareil };
        if (memoriser) {
            this.store(joueur);
        }
        return joueur;
    }

    /**
     * Prolonge la session conservée si elle expire bientôt (activité du joueur) ; sans effet sinon.
     *
     * @param {Object} joueur - Joueur `{ token, expiresAt, … }`
     * @param {Object} [options={}]
     * @param {boolean} [options.memoriser=true]
     * @returns {Promise<Object>} Le joueur, avec son jeton prolongé le cas échéant
     * @private
     */
    async entretenir(joueur, options = {}) {
        if (!joueur || !joueur.token || !joueur.expiresAt || VisiteurAPI.expire(joueur)) {
            return joueur;
        }
        if (Date.parse(joueur.expiresAt) - Date.now() > VisiteurAPI.MARGE_RENOUVELLEMENT) {
            return joueur;
        }
        const { token, expiresAt } = await this.renouveler(joueur.token);
        const prolonge = { ...joueur, token, expiresAt };
        if (options.memoriser !== false) {
            this.store(prolonge);
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

    /**
     * Visiteur conservé sur cet appareil, sans appel réseau.
     *
     * @returns {(VisiteurProfil & {token: string})|null} Le visiteur, ou null (aucun, ou stockage indisponible)
     *
     * @example
     * const pseudo = api.visiteurs.courant()?.pseudo ?? '';
     */
    courant() {
        const storage = this.client.config.storage;
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
     * Oublie le visiteur conservé sur cet appareil (« ce n'est pas moi », déconnexion).
     */
    oublier() {
        try {
            this.client.config.storage?.removeItem(VISITEUR_STORAGE_KEY);
        } catch (error) { /* stockage indisponible */ }
    }

    /**
     * @param {Object} joueur
     * @private
     */
    store(joueur) {
        try {
            this.client.config.storage?.setItem(VISITEUR_STORAGE_KEY, JSON.stringify(joueur));
        } catch (error) { /* stockage indisponible ou plein : le visiteur ne sera pas retrouvé */ }
    }

    /**
     * @deprecated Endpoint inexistant côté serveur : sera remplacé par l'identification par badge
     * du futur module d'authentification.
     * @param {string} rfidTag - Tag RFID du visiteur
     */
    async getByRfid(rfidTag) {
        return this.client.get(`${this.basePath}/rfid/${rfidTag}`);
    }

    /**
     * @deprecated Endpoint inexistant côté serveur : utiliser {@link VisiteurAPI#inviter} (prototype)
     * en attendant le module d'authentification.
     * @param {Object} data - Données du visiteur
     */
    async register(data) {
        return this.client.post(`${this.basePath}/register`, data);
    }
}

/** Le jeton est prolongé quand il lui reste moins de 15 minutes (activité du joueur). */
VisiteurAPI.MARGE_RENOUVELLEMENT = 15 * 60 * 1000;

export default VisiteurAPI;
