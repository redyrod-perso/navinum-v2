import { ValidationError } from '../core/Errors.js';
import { resolveHubUrl, subscribe } from '../core/Realtime.js';

/** Topic Mercure publié par Navinum à chaque création de groupe. */
export const RFID_GROUPES_TOPIC = 'rfid-groupes';

const LD_JSON = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' };

/**
 * Groupe de visiteurs (classe, famille, équipe…). Dans le quiz, le groupe est aussi
 * l'identifiant de la session de jeu commune.
 * @typedef {Object} RfidGroupe
 * @property {string} id - UUID du groupe
 * @property {string} nom - Nom du groupe (unique)
 */

/**
 * Groupes de visiteurs (`/api/rfid_groupes`, API Platform).
 *
 * Lecture et création publiques ; modification et suppression réservées à l'admin.
 *
 * @class
 * @example
 * const groupes = await api.rfidGroupes.list();
 * const groupe = await api.rfidGroupes.findByNom('Classe 4B') ?? await api.rfidGroupes.create('Classe 4B');
 * const stop = api.rfidGroupes.onCreated(g => console.log('Nouveau groupe', g.nom));
 */
export class RfidGroupeAPI {
    /**
     * @param {ApiClient} client - Client HTTP de la librairie
     */
    constructor(client) {
        this.client = client;
        this.basePath = '/api/rfid_groupes';
    }

    /**
     * Liste des groupes (première page, 30 groupes).
     *
     * @param {Object} [params={}] - Filtres API Platform (ex. `{ nom: 'Classe' }`, recherche partielle)
     * @returns {Promise<RfidGroupe[]>}
     * @throws {NetworkError} En cas d'erreur réseau
     */
    async list(params = {}) {
        const data = await this.client.get(this.basePath, { params, headers: LD_JSON });
        const members = data && (data.member || data['hydra:member']);
        return Array.isArray(members) ? members : [];
    }

    /**
     * Cherche un groupe par son nom exact.
     *
     * @param {string} nom - Nom du groupe
     * @returns {Promise<RfidGroupe|null>} Le groupe, ou null s'il n'existe pas
     */
    async findByNom(nom) {
        const value = RfidGroupeAPI.normalizeNom(nom);
        const groupes = await this.list({ nom: value });
        return groupes.find(g => g.nom === value) || null;
    }

    /**
     * Crée un groupe.
     *
     * @param {string} nom - Nom du groupe (unique)
     * @returns {Promise<RfidGroupe>} Le groupe créé
     * @throws {ValidationError} Si le nom est vide, ou s'il existe déjà (422 côté serveur)
     */
    async create(nom) {
        return this.client.post(this.basePath, { nom: RfidGroupeAPI.normalizeNom(nom) }, { headers: LD_JSON });
    }

    /**
     * Retourne le groupe portant ce nom, en le créant s'il n'existe pas.
     *
     * @param {string} nom - Nom du groupe
     * @returns {Promise<RfidGroupe>}
     */
    async findOrCreate(nom) {
        return (await this.findByNom(nom)) || this.create(nom);
    }

    /**
     * Suit en direct (Mercure) la création de nouveaux groupes.
     *
     * @param {function(RfidGroupe): void} callback - Reçoit chaque groupe créé `{ id, nom }`
     * @returns {function(): void} Fonction de désabonnement
     */
    onCreated(callback) {
        const config = this.client.config;
        return subscribe(resolveHubUrl(config.mercureHub, config.baseURL), RFID_GROUPES_TOPIC, (data) => {
            if (data && data.type === 'groupe_created' && data.groupe) {
                callback(data.groupe);
            }
        }, { log: (...args) => config.log(...args) });
    }

    /**
     * @param {string} nom
     * @returns {string} Nom sans espaces superflus
     * @throws {ValidationError} Si le nom est vide
     * @private
     */
    static normalizeNom(nom) {
        const value = typeof nom === 'string' ? nom.trim() : '';
        if (!value) {
            throw new ValidationError('Nom de groupe manquant', { nom: 'requis' });
        }
        return value;
    }
}

export default RfidGroupeAPI;
