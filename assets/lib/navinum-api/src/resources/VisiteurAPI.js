import { BaseResource } from './BaseResource.js';

/**
 * API pour gérer les visiteurs
 */
export class VisiteurAPI extends BaseResource {
    constructor(client) {
        super(client, 'visiteur', { basePath: '/api/visiteurs' });
    }

    /**
     * Recherche un visiteur par son tag RFID
     * @param {string} rfidTag - Tag RFID du visiteur
     */
    async getByRfid(rfidTag) {
        return this.client.get(`${this.basePath}/rfid/${rfidTag}`);
    }

    /**
     * Enregistre un nouveau visiteur
     * @param {Object} data - Données du visiteur
     */
    async register(data) {
        return this.client.post(`${this.basePath}/register`, data);
    }
}

export default VisiteurAPI;
