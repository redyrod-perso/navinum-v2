import { ValidationError } from '../../core/Errors.js';

/**
 * Groupes de joueurs pour les jeux multijoueur : lister, suivre en direct, retrouver un groupe par son nom
 * ou le créer. La logique est ici ; les appels HTTP restent dans la ressource `api.rfidGroupes`
 * (les groupes RFID — classes, familles, équipes — servent aussi en dehors des jeux).
 *
 * @class
 * @example
 * const groupes = await api.multijoueur.groupes.lister();
 * const stop = api.multijoueur.groupes.onCree(g => ajouterALaListe(g.nom));
 * const groupe = await api.multijoueur.groupes.trouverOuCreer('Classe 4B');
 */
export class Groupes {
    /**
     * @param {RfidGroupeAPI} rfidGroupes - Appels HTTP des groupes
     */
    constructor(rfidGroupes) {
        this.rfidGroupes = rfidGroupes;
    }

    /**
     * Tous les groupes.
     * @returns {Promise<Array<{id: string, nom: string}>>}
     */
    async lister() {
        return this.rfidGroupes.list();
    }

    /**
     * Groupe portant exactement ce nom (espaces superflus ignorés), ou null.
     * @param {string} nom
     * @returns {Promise<{id: string, nom: string}|null>}
     * @throws {ValidationError} Nom vide
     */
    async trouver(nom) {
        const value = Groupes.nom(nom);
        const groupes = await this.rfidGroupes.list({ nom: value }); // recherche partielle côté serveur
        return groupes.find(g => g.nom === value) || null;
    }

    /**
     * Groupe portant ce nom, créé s'il n'existe pas.
     * @param {string} nom
     * @returns {Promise<{id: string, nom: string}>}
     * @throws {ValidationError} Nom vide
     */
    async trouverOuCreer(nom) {
        return (await this.trouver(nom)) || this.rfidGroupes.create(Groupes.nom(nom));
    }

    /**
     * Groupes créés en direct (Mercure).
     * @param {function({id: string, nom: string}): void} callback
     * @returns {function(): void} Fonction de désabonnement
     */
    onCree(callback) {
        return this.rfidGroupes.onCreated(callback);
    }

    /**
     * Nom de groupe sans espaces superflus.
     * @param {string} nom
     * @returns {string}
     * @throws {ValidationError} Nom vide
     */
    static nom(nom) {
        const value = typeof nom === 'string' ? nom.trim() : '';
        if (!value) {
            throw new ValidationError('Nom de groupe manquant', { nom: 'requis' });
        }
        return value;
    }
}

export default Groupes;
