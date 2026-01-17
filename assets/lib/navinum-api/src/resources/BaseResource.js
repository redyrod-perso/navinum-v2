/**
 * Classe de base pour toutes les ressources API
 * Fournit les méthodes CRUD standards
 */
export class BaseResource {
    constructor(client, resourceName, options = {}) {
        this.client = client;
        this.resourceName = resourceName;
        this.basePath = options.basePath || `/api/${resourceName}`;
    }

    /**
     * Récupère toutes les ressources
     * @param {Object} params - Paramètres de requête (pagination, filtres, etc.)
     */
    async getAll(params = {}) {
        return this.client.get(this.basePath, { params });
    }

    /**
     * Récupère une ressource par ID
     * @param {string|number} id - Identifiant de la ressource
     */
    async getById(id) {
        return this.client.get(`${this.basePath}/${id}`);
    }

    /**
     * Crée une nouvelle ressource
     * @param {Object} data - Données de la ressource à créer
     */
    async create(data) {
        return this.client.post(this.basePath, data);
    }

    /**
     * Met à jour une ressource existante
     * @param {string|number} id - Identifiant de la ressource
     * @param {Object} data - Nouvelles données
     */
    async update(id, data) {
        return this.client.put(`${this.basePath}/${id}`, data);
    }

    /**
     * Met à jour partiellement une ressource
     * @param {string|number} id - Identifiant de la ressource
     * @param {Object} data - Données à mettre à jour
     */
    async patch(id, data) {
        return this.client.patch(`${this.basePath}/${id}`, data);
    }

    /**
     * Supprime une ressource
     * @param {string|number} id - Identifiant de la ressource
     */
    async delete(id) {
        return this.client.delete(`${this.basePath}/${id}`);
    }
}

export default BaseResource;
