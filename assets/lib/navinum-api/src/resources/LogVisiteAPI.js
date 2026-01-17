import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les logs de visite
 */
export class LogVisiteAPI extends BaseResource {
    constructor(client) {
        super(client, 'log_visite', { basePath: '/api/log-visites' });
    }

    /**
     * Enregistre une action de visite
     * @param {Object} data - { visiteurId, action, data?, timestamp? }
     */
    async track(data) {
        return this.client.post(`${this.basePath}/track`, {
            ...data,
            timestamp: data.timestamp || new Date().toISOString()
        });
    }

    /**
     * Récupère les logs d'un visiteur
     * @param {number} visiteurId - ID du visiteur
     * @param {Object} params - Paramètres de filtrage
     */
    async getByVisiteur(visiteurId, params = {}) {
        return this.client.get(`${this.basePath}/visiteur/${visiteurId}`, { params });
    }

    /**
     * Récupère les statistiques de visite
     * @param {Object} params - Paramètres de filtrage (dateDebut, dateFin, etc.)
     */
    async getStats(params = {}) {
        return this.client.get(`${this.basePath}/stats`, { params });
    }
}

export default LogVisiteAPI;
