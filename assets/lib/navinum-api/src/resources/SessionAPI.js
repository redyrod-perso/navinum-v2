import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les sessions multi-joueurs
 */
export class SessionAPI extends BaseResource {
    constructor(client) {
        super(client, 'session', { basePath: '/api/session' });
    }

    /**
     * Crée une nouvelle session ou rejoint une session existante
     * @param {Object} data - { playerName, sessionId? }
     */
    async create(data) {
        return this.client.post('/api/session/create', data);
    }

    /**
     * Rejoint une session existante
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     */
    async join(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/join`, data);
    }

    /**
     * Quitte une session
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     */
    async leave(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/leave`, data);
    }

    /**
     * Démarre une session (le jeu)
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { theme }
     */
    async start(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/start`, data);
    }

    /**
     * Met à jour le score d'un joueur
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName, score }
     */
    async updateScore(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/score`, data);
    }

    /**
     * Réinitialise une session
     * @param {string} sessionId - Identifiant de la session
     */
    async reset(sessionId) {
        return this.client.post(`/api/session/${sessionId}/reset`);
    }

    /**
     * Supprime toutes les sessions
     */
    async clearAll() {
        return this.client.post('/api/sessions/clear');
    }

    /**
     * Récupère les informations d'une session
     * @param {string} sessionId - Identifiant de la session
     */
    async get(sessionId) {
        return this.client.get(`/api/session/${sessionId}`);
    }
}

export default SessionAPI;
