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
     * @returns {Promise<{visiteur: VisiteurProfil, token: string}>} Le profil et le jeton visiteur
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
     * Identifie le joueur : réutilise le visiteur conservé sur cet appareil s'il porte le même pseudo
     * et que son jeton est toujours valide, sinon crée un visiteur invité et le conserve.
     *
     * C'est le point d'entrée des interactifs pour l'identification ; le futur module
     * d'authentification (compte en ligne, OAuth, badge RFID) s'y branchera sans changement
     * pour les interactifs. Le stockage se règle avec l'option `storage` de NavinumAPI
     * (`localStorage` par défaut, `null` pour ne rien conserver).
     *
     * @param {string} pseudo - Pseudo saisi par le joueur (2 à 40 caractères)
     * @returns {Promise<VisiteurProfil & {token: string}>} Le visiteur et son jeton (`nvv1.…`)
     * @throws {ValidationError} Si le pseudo est invalide
     * @throws {NetworkError} Si l'API est injoignable et qu'aucun visiteur n'est conservé pour ce pseudo
     *
     * @example
     * const joueur = await api.visiteurs.identifier('Alice');
     * const ctx = await api.launch();
     * if (ctx) await ctx.identify(joueur.token);
     */
    async identifier(pseudo) {
        const value = typeof pseudo === 'string' ? pseudo.trim() : '';
        const saved = this.courant();

        if (saved && saved.token && saved.pseudo === value) {
            try {
                await this.moi(saved.token);
                return saved;
            } catch (error) {
                if (!(error instanceof UnauthorizedError)) {
                    return saved; // API injoignable : on garde le visiteur connu
                }
            }
        }

        const { visiteur, token } = await this.inviter(value);
        const joueur = { ...visiteur, token };
        this.store(joueur);
        return joueur;
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

export default VisiteurAPI;
