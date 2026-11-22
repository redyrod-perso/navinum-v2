import { BaseResource } from './BaseResource.js';
import { ValidationError } from '../core/Errors.js';

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
