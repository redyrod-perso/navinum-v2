import {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError
} from '../../core/Errors.js';
import { ApiClient } from '../../core/ApiClient.js';

/**
 * Actions conseillées à l'interactif (il choisit comment les présenter).
 * @readonly
 * @enum {string}
 */
export const ACTIONS = Object.freeze({
    REESSAYER: 'REESSAYER',
    RECHARGER: 'RECHARGER',
    RELANCER: 'RELANCER',
    CORRIGER: 'CORRIGER',
    SE_RECONNECTER: 'SE_RECONNECTER',
    CHOISIR_AUTRE_GROUPE: 'CHOISIR_AUTRE_GROUPE',
    ATTENDRE: 'ATTENDRE',
    AUCUNE: 'AUCUNE'
});

/**
 * Catalogue des codes d'erreur stables, indépendants des statuts HTTP.
 *
 * Chaque entrée : `titre` et `message` (français, pour le joueur), `reessayable`, `action` conseillée.
 * Les codes envoyés par le serveur (`{ "error": "…", "code": "…" }`) sont prioritaires sur le statut HTTP.
 *
 * @readonly
 * @type {Object<string, {titre: string, message: string, reessayable: boolean, action: string}>}
 */
export const CODES = Object.freeze({
    RESEAU: { titre: 'Connexion impossible', message: 'Le serveur Navinum ne répond pas. Vérifiez le réseau puis réessayez.', reessayable: true, action: ACTIONS.REESSAYER },
    SERVEUR: { titre: 'Erreur du serveur', message: 'Le serveur a rencontré un problème. Réessayez dans un instant.', reessayable: true, action: ACTIONS.REESSAYER },
    VALIDATION: { titre: 'Données invalides', message: 'Certaines informations sont invalides.', reessayable: false, action: ACTIONS.CORRIGER },
    NON_IDENTIFIE: { titre: 'Identification requise', message: 'Votre identification a expiré ou est invalide. Identifiez-vous à nouveau.', reessayable: false, action: ACTIONS.SE_RECONNECTER },
    NON_AUTORISE: { titre: 'Action non autorisée', message: 'Vous n’avez pas le droit de faire cette action.', reessayable: false, action: ACTIONS.AUCUNE },
    INTROUVABLE: { titre: 'Introuvable', message: 'L’élément demandé n’existe pas ou plus.', reessayable: false, action: ACTIONS.RECHARGER },
    CONFLIT: { titre: 'Action impossible', message: 'Cette action n’est pas possible pour le moment.', reessayable: true, action: ACTIONS.ATTENDRE },
    LIEN_INVALIDE: { titre: 'Lien invalide', message: 'Ce lien de lancement est invalide. Relancez l’interactif depuis Navinum.', reessayable: false, action: ACTIONS.RELANCER },
    LIEN_EXPIRE: { titre: 'Lien expiré', message: 'Ce lien a expiré. Relancez l’interactif depuis Navinum.', reessayable: false, action: ACTIONS.RELANCER },
    DEJA_RATTACHE: { titre: 'Partie déjà attribuée', message: 'Cette partie est déjà rattachée à un autre visiteur.', reessayable: false, action: ACTIONS.RELANCER },
    SESSION_INTROUVABLE: { titre: 'Groupe introuvable', message: 'Ce groupe n’existe pas ou plus.', reessayable: false, action: ACTIONS.CHOISIR_AUTRE_GROUPE },
    SESSION_INVALIDE: { titre: 'Nom de groupe invalide', message: 'Ce nom de groupe contient des caractères non autorisés.', reessayable: false, action: ACTIONS.CORRIGER },
    PARTIE_EN_COURS: { titre: 'Partie en cours', message: 'Une partie est en cours dans ce groupe : attendez la fin ou choisissez un autre groupe.', reessayable: true, action: ACTIONS.CHOISIR_AUTRE_GROUPE },
    PARTIE_DEMARREE: { titre: 'Partie déjà commencée', message: 'La partie a déjà commencé.', reessayable: false, action: ACTIONS.AUCUNE },
    GROUPE_COMPLET: { titre: 'Groupe complet', message: 'Ce groupe est complet : choisissez-en un autre.', reessayable: false, action: ACTIONS.CHOISIR_AUTRE_GROUPE },
    NON_LEADER: { titre: 'Réservé au leader', message: 'Seul le leader du groupe peut faire cette action.', reessayable: false, action: ACTIONS.AUCUNE },
    SCORE_REFUSE: { titre: 'Score refusé', message: 'Ce score n’a pas été accepté par le serveur.', reessayable: false, action: ACTIONS.AUCUNE },
    MEDAILLE_NON_ATTRIBUABLE: { titre: 'Médaille non attribuable', message: 'Cette médaille ne peut pas être attribuée par cet interactif.', reessayable: false, action: ACTIONS.AUCUNE },
    MEDAILLE_INCONNUE: { titre: 'Médaille inconnue', message: 'Cette médaille n’existe pas.', reessayable: false, action: ACTIONS.AUCUNE },
    MODE_INDISPONIBLE: { titre: 'Connexion indisponible', message: 'Ce mode de connexion n’est pas encore disponible.', reessayable: false, action: ACTIONS.AUCUNE },
    INCONNU: { titre: 'Erreur inattendue', message: 'Une erreur inattendue est survenue.', reessayable: true, action: ACTIONS.REESSAYER }
});

/**
 * Description exploitable d'une erreur, sans HTML ni design.
 * @typedef {Object} ErreurDecrite
 * @property {string} code - Code stable (voir {@link CODES})
 * @property {string} titre - Titre court pour le joueur
 * @property {string} message - Message pour le joueur (celui du serveur s'il est fourni, plus précis)
 * @property {boolean} reessayable - Réessayer plus tard peut fonctionner
 * @property {string} action - Action conseillée (voir {@link ACTIONS})
 * @property {number|null} status - Statut HTTP, ou null (réseau, erreur locale)
 * @property {Object} details - Détails (violations de validation, corps de la réponse…)
 * @property {Error|*} erreur - Erreur d'origine
 */

/**
 * Module de gestion des erreurs pour les interactifs : transforme n'importe quelle erreur
 * (erreur typée de la librairie, erreur JavaScript, chaîne…) en {@link ErreurDecrite}.
 *
 * Aucun affichage : l'interactif choisit sa présentation (bandeau, popup, écran dédié…).
 *
 * @class
 * @example
 * try {
 *     await api.sessions.join('Classe 4B', { playerName: 'Léa' });
 * } catch (err) {
 *     const info = NavinumAPI.erreurs.decrire(err);
 *     afficherBandeau(info.titre, info.message);
 *     if (info.action === 'CHOISIR_AUTRE_GROUPE') revenirAuChoixDuGroupe();
 * }
 */
export class Erreurs {
    /** Codes disponibles (voir {@link CODES}). */
    get codes() {
        return CODES;
    }

    /** Actions conseillées (voir {@link ACTIONS}). */
    get actions() {
        return ACTIONS;
    }

    /**
     * Décrit une erreur.
     *
     * @param {Error|*} erreur - N'importe quelle valeur levée
     * @returns {ErreurDecrite}
     *
     * @example
     * NavinumAPI.erreurs.decrire(err).code; // 'RESEAU', 'LIEN_EXPIRE', 'PARTIE_EN_COURS'…
     */
    decrire(erreur) {
        const code = Erreurs.codeDe(erreur);
        const entree = CODES[code] || CODES.INCONNU;
        const status = erreur instanceof NavinumAPIError ? erreur.status : null;
        // Message du serveur : en français et plus précis que celui du catalogue
        const messageServeur = erreur instanceof NavinumAPIError ? ApiClient.serverMessage(erreur.body) : null;
        // Erreur levée par la librairie elle-même, sans réponse du serveur (ex. pseudo trop court vérifié avant
        // l'appel) : son message est précis. Les erreurs réseau gardent le message du catalogue.
        const messageLocal = erreur instanceof NavinumAPIError && !(erreur instanceof NetworkError)
            && (erreur.local || erreur.body === null) && erreur.message ? erreur.message : null;

        return {
            code,
            titre: entree.titre,
            message: messageServeur || messageLocal || entree.message,
            reessayable: entree.reessayable,
            action: entree.action,
            status,
            details: Erreurs.detailsDe(erreur),
            erreur
        };
    }

    /**
     * Crée une erreur de l'interactif lui-même avec un code du catalogue (ex. groupe complet
     * détecté côté client), pour la traiter comme les erreurs de l'API.
     *
     * @param {string} code - Code du catalogue (voir {@link CODES})
     * @param {string} [message] - Message précis ; à défaut, celui du catalogue
     * @param {Object} [details={}] - Détails libres
     * @returns {NavinumAPIError}
     *
     * @example
     * throw NavinumAPI.erreurs.creer('GROUPE_COMPLET', 'Ce groupe est complet (8 joueurs maximum).');
     */
    creer(code, message, details = {}) {
        const entree = CODES[code] || CODES.INCONNU;
        const erreur = new NavinumAPIError(message || entree.message, null, details);
        erreur.code = CODES[code] ? code : 'INCONNU';
        erreur.local = Boolean(message);
        erreur.details = details;
        return erreur;
    }

    /**
     * Indique si une erreur porte un code donné.
     *
     * @param {Error|*} erreur
     * @param {...string} codes
     * @returns {boolean}
     *
     * @example
     * if (NavinumAPI.erreurs.est(err, 'LIEN_EXPIRE', 'LIEN_INVALIDE')) { … }
     */
    est(erreur, ...codes) {
        return codes.includes(Erreurs.codeDe(erreur));
    }

    /**
     * Code stable d'une erreur : code métier du serveur s'il est connu, sinon déduit du type / statut.
     * @param {Error|*} erreur
     * @returns {string}
     * @private
     */
    static codeDe(erreur) {
        if (erreur instanceof NavinumAPIError && erreur.code && CODES[erreur.code]) {
            return erreur.code;
        }
        if (erreur instanceof NetworkError) return 'RESEAU';
        if (erreur instanceof GoneError) return 'LIEN_EXPIRE';
        if (erreur instanceof UnauthorizedError) return 'NON_IDENTIFIE';
        if (erreur instanceof NotFoundError) return 'INTROUVABLE';
        if (erreur instanceof ValidationError) return 'VALIDATION';
        if (erreur instanceof ServerError) return 'SERVEUR';
        if (erreur instanceof NavinumAPIError) {
            if (erreur.status === 403) return 'NON_AUTORISE';
            if (erreur.status === 409) return 'CONFLIT';
            if (erreur.status >= 500) return 'SERVEUR';
        }
        // fetch hors librairie / navigateur hors ligne
        if (erreur instanceof TypeError && /fetch|network/i.test(erreur.message || '')) return 'RESEAU';
        return 'INCONNU';
    }

    /** @private */
    static detailsDe(erreur) {
        if (!(erreur instanceof NavinumAPIError)) return {};
        if (erreur.details) return erreur.details;
        if (erreur instanceof ValidationError) return erreur.errors || {};
        return erreur.body && typeof erreur.body === 'object' ? erreur.body : {};
    }
}

/** Instance partagée (le module n'a pas d'état). */
export const erreurs = new Erreurs();

export default Erreurs;
