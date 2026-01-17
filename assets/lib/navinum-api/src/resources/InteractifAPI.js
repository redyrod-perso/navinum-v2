import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les interactifs
 */
export class InteractifAPI extends BaseResource {
    constructor(client) {
        super(client, 'interactif', { basePath: '/api/interactifs' });
    }

    /**
     * Récupère les interactifs d'un parcours
     * @param {number} parcoursId - ID du parcours
     */
    async getByParcours(parcoursId) {
        return this.client.get(`${this.basePath}`, {
            params: { parcours: parcoursId }
        });
    }

    /**
     * Enregistre une interaction
     * @param {number} interactifId - ID de l'interactif
     * @param {Object} data - Données de l'interaction
     */
    async logInteraction(interactifId, data) {
        return this.client.post(`${this.basePath}/${interactifId}/interact`, data);
    }
}

export default InteractifAPI;
