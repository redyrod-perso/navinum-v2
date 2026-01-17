import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les expositions
 */
export class ExpositionAPI extends BaseResource {
    constructor(client) {
        super(client, 'exposition', { basePath: '/api/expositions' });
    }

    // Méthodes spécifiques aux expositions peuvent être ajoutées ici
    // Par exemple :

    /**
     * Récupère les expositions actives
     */
    async getActive() {
        return this.client.get(`${this.basePath}/active`);
    }

    /**
     * Recherche des expositions par critères
     * @param {Object} criteria - Critères de recherche
     */
    async search(criteria) {
        return this.client.get(this.basePath, { params: criteria });
    }
}

export default ExpositionAPI;
