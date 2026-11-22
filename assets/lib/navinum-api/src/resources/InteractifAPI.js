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

    /**
     * Charge le manifeste `navinum.json` de l'interactif et en extrait la configuration par défaut
     * (valeurs `default` de son `configSchema`).
     *
     * Sert en mode autonome (lancé sans `?nv=`) et comme base de fusion des configurations
     * reçues de Navinum (`ctx.config`, `ctx.onConfigChange()`).
     *
     * @param {string} url - URL du manifeste (ex. `/quiz/navinum.json`, relative à `baseURL`)
     * @returns {Promise<{manifest: Object, defaults: Object}>}
     * @throws {NotFoundError} Si le manifeste est introuvable
     *
     * @example
     * const { manifest, defaults } = await api.interactifs.chargerManifeste('/quiz/navinum.json');
     * const config = ctx ? { ...defaults, ...ctx.config } : defaults;
     */
    async chargerManifeste(url) {
        const manifest = await this.client.get(url, { headers: { Accept: 'application/json' } });
        return { manifest, defaults: InteractifAPI.defaultsFromSchema(manifest && manifest.configSchema) };
    }

    /**
     * Valeurs par défaut décrites par un JSON Schema (récursif sur les objets).
     *
     * @param {Object|null|undefined} schema
     * @returns {Object} Objet des valeurs par défaut (vide si aucun schéma)
     *
     * @example
     * InteractifAPI.defaultsFromSchema({ type: 'object', properties: { duree: { default: 20 } } }); // { duree: 20 }
     */
    static defaultsFromSchema(schema) {
        const walk = (node) => {
            if (!node || typeof node !== 'object') return undefined;
            if (Object.prototype.hasOwnProperty.call(node, 'default')) return node.default;
            if (node.type === 'object' || node.properties) {
                const out = {};
                for (const [key, sub] of Object.entries(node.properties || {})) {
                    const value = walk(sub);
                    if (value !== undefined) out[key] = value;
                }
                return out;
            }
            return undefined;
        };
        const result = walk(schema);
        return result && typeof result === 'object' && !Array.isArray(result) ? result : {};
    }
}

export default InteractifAPI;
