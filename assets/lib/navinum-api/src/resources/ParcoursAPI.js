import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les parcours
 */
export class ParcoursAPI extends BaseResource {
    constructor(client) {
        super(client, 'parcours', { basePath: '/api/parcours' });
    }

    /**
     * Récupère les parcours d'une exposition
     * @param {number} expositionId - ID de l'exposition
     */
    async getByExposition(expositionId) {
        return this.client.get(`${this.basePath}`, {
            params: { exposition: expositionId }
        });
    }
}

export default ParcoursAPI;
