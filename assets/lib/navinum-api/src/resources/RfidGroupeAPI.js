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
 * Appels HTTP des groupes de visiteurs (`/api/rfid_groupes`, API Platform), sans logique de jeu.
 *
 * Lecture et création publiques ; modification et suppression réservées à l'admin. Pour les jeux,
 * utiliser le module multijoueur : `api.multijoueur.groupes` (retrouver un groupe par son nom ou le créer).
 *
 * @class
 * @example
 * const groupes = await api.rfidGroupes.list();
 * const cree = await api.rfidGroupes.create('Classe 4B');
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
     * Liste de **tous** les groupes : les pages de l'API (30 groupes chacune) sont toutes lues.
     *
     * @param {Object} [params={}] - Filtres API Platform (ex. `{ nom: 'Classe' }`, recherche partielle)
     * @returns {Promise<RfidGroupe[]>}
     * @throws {NetworkError} En cas d'erreur réseau
     */
    async list(params = {}) {
        const groupes = [];
        let data = await this.client.get(this.basePath, { params, headers: LD_JSON });
        for (let page = 1; ; page++) {
            const members = data && (data.member || data['hydra:member']);
            if (Array.isArray(members)) groupes.push(...members);
            const view = data && (data.view || data['hydra:view']);
            const suivante = view && (view.next || view['hydra:next']);
            if (!suivante || page >= RfidGroupeAPI.PAGES_MAX) break;
            data = await this.client.get(suivante, { headers: LD_JSON });
        }
        return groupes;
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
     * @deprecated Depuis 0.10.0 : utiliser `api.multijoueur.groupes.trouver(nom)`.
     * @param {string} nom
     * @returns {Promise<RfidGroupe|null>}
     */
    async findByNom(nom) {
        return this.groupes.trouver(nom);
    }

    /**
     * @deprecated Depuis 0.10.0 : utiliser `api.multijoueur.groupes.trouverOuCreer(nom)`.
     * @param {string} nom
     * @returns {Promise<RfidGroupe>}
     */
    async findOrCreate(nom) {
        return this.groupes.trouverOuCreer(nom);
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

/** Garde-fou : nombre maximum de pages lues (30 groupes par page). */
RfidGroupeAPI.PAGES_MAX = 50;

export default RfidGroupeAPI;
