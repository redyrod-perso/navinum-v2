import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les tags RFID
 */
export class RfidAPI extends BaseResource {
    constructor(client) {
        super(client, 'rfid', { basePath: '/api/rfid' });
    }

    /**
     * Scanne un tag RFID
     * @param {string} tagId - Identifiant du tag
     */
    async scan(tagId) {
        return this.client.post(`${this.basePath}/scan`, { tagId });
    }

    /**
     * Associe un tag à une ressource
     * @param {string} tagId - Identifiant du tag
     * @param {Object} data - { resourceType, resourceId }
     */
    async associate(tagId, data) {
        return this.client.post(`${this.basePath}/${tagId}/associate`, data);
    }
}

export default RfidAPI;
