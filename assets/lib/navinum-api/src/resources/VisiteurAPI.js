import { BaseResource } from './BaseResource.js';
import { ValidationError } from '../core/Errors.js';

export { VISITEUR_STORAGE_KEY } from '../modules/auth/Stockage.js';

/**
 * Profil public d'un visiteur (jamais d'email ni d'information personnelle).
 * @typedef {Object} VisiteurProfil
 * @property {string} id - UUID du visiteur
 * @property {string} pseudo - Pseudo affiché
 * @property {boolean} anonyme - true pour un visiteur invité (sans compte)
 */

/**
 * Appels HTTP de l'identification des visiteurs (ressource du serveur, sans logique de session).
 *
 * La logique (session, reprise sur l'appareil, conservation, mode borne) est dans le module
 * d'authentification : **utiliser `api.auth`** dans les interactifs.
 *
 * Endpoints :
 * - `POST /api/visiteur/invite` `{ pseudo }` → `{ visiteur, token, expiresAt, appareil }` (201)
 * - `POST /api/visiteur/reprendre` `{ pseudo, appareil }` → `{ visiteur, token, expiresAt }`
 * - `POST /api/visiteur/renouveler` (Bearer) → `{ token, expiresAt }`
 * - `POST /api/visiteur/deconnexion` (Bearer) → 204
 * - `GET  /api/visiteur/moi` (Bearer) → `{ visiteur }`
 *
 * Les opérations CRUD héritées de BaseResource (`/api/visiteurs`) sont réservées à l'admin (401 sinon).
 *
 * @class
 */
export class VisiteurAPI extends BaseResource {
    constructor(client) {
        super(client, 'visiteur', { basePath: '/api/visiteurs' });
    }

    /**
     * Crée un visiteur invité (sans compte) à partir d'un pseudo.
     *
     * @param {string} pseudo - Pseudo, 2 à 40 caractères
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil: string}>} Le profil,
     *          le jeton visiteur (30 min, renouvelable) et le secret de l'appareil (pour reprendre le profil)
     * @throws {ValidationError} Si le pseudo est vide ou invalide (400)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * const { visiteur, token } = await api.visiteurs.inviter('Alice');
     */
    async inviter(pseudo) {
        const value = typeof pseudo === 'string' ? pseudo.trim() : '';
        if (value.length < 2 || value.length > 40) {
            throw new ValidationError('Le pseudo doit faire entre 2 et 40 caractères', { pseudo: 'invalide' });
        }
        return this.client.post('/api/visiteur/invite', { pseudo: value });
    }

    /**
     * Profil du visiteur correspondant à un jeton (vérifie aussi que le jeton est toujours valide).
     *
     * @param {string} token - Jeton visiteur (`nvv1.…`)
     * @returns {Promise<{visiteur: VisiteurProfil}>}
     * @throws {UnauthorizedError} Si le jeton est absent ou invalide (401)
     *
     * @example
     * const { visiteur } = await api.visiteurs.moi(saved.token);
     */
    async moi(token) {
        if (!token) {
            throw new ValidationError('Jeton visiteur manquant', { token: 'requis' });
        }
        return this.client.get('/api/visiteur/moi', { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Reprend le visiteur conservé sur cet appareil après expiration de sa session (30 min d'inactivité,
     * 12 h au plus) : même profil (XP, médailles) si le pseudo et le secret de l'appareil correspondent.
     *
     * @param {Object} data - `{ pseudo, appareil }` (secret d'appareil `nva1.…`), ou `{ pseudo, ancienJeton }`
     *        pour échanger un ancien jeton `nvv1` (renvoie alors aussi `appareil`)
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil?: string}>}
     * @throws {UnauthorizedError} code `NON_IDENTIFIE` : appareil inconnu ou révoqué, pseudo différent
     */
    async reprendre(data) {
        return this.client.post('/api/visiteur/reprendre', data);
    }

    /**
     * Prolonge une session encore valide de 30 min (12 h au plus depuis l'identification).
     *
     * @param {string} token - Jeton visiteur en cours
     * @returns {Promise<{token: string, expiresAt: string}>}
     * @throws {UnauthorizedError} code `NON_IDENTIFIE` : session expirée ou révoquée
     */
    async renouveler(token) {
        return this.client.post('/api/visiteur/renouveler', {}, { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Déconnecte cet appareil côté serveur (ses jetons deviennent invalides immédiatement). Idempotent.
     *
     * @param {string} token - Jeton visiteur en cours
     * @returns {Promise<void>}
     */
    async deconnexion(token) {
        await this.client.post('/api/visiteur/deconnexion', {}, { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Crée un compte (e-mail + mot de passe). Avec le jeton d'un **invité**, c'est lui qui devient un compte
     * (mêmes XP et médailles) ; la réponse n'inclut alors pas de nouveau secret d'appareil.
     *
     * @param {{email: string, motDePasse: string, pseudo?: string, newsletter?: boolean}} data
     * @param {string} [jetonInvite] - Jeton visiteur de l'invité à transformer
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil?: string}>}
     * @throws {ValidationError} code `VALIDATION` ; {NavinumAPIError} `EMAIL_DEJA_UTILISE` (409), `TROP_DE_TENTATIVES` (429)
     */
    async inscription(data, jetonInvite) {
        return this.client.post('/api/visiteur/inscription', data,
            jetonInvite ? { headers: { Authorization: `Bearer ${jetonInvite}` } } : {});
    }

    /**
     * Connexion à un compte (e-mail + mot de passe) : nouvel appareil, jeton de 30 min.
     *
     * @param {{email: string, motDePasse: string}} data
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil: string}>}
     * @throws {UnauthorizedError} code `IDENTIFIANTS_INVALIDES` ; {NavinumAPIError} `TROP_DE_TENTATIVES` (429)
     */
    async connexion(data) {
        return this.client.post('/api/visiteur/connexion', data);
    }

    /**
     * Renvoie le lien de vérification de l'adresse du compte connecté (3 e-mails par 24 h au plus).
     * @param {string} token - Jeton visiteur d'un compte
     * @returns {Promise<{emailVerifie: boolean, envoye: boolean}>}
     * @throws {UnauthorizedError} code `NON_IDENTIFIE` (pas un compte, ou jeton expiré)
     */
    async renvoyerVerification(token) {
        return this.client.post('/api/visiteur/email/renvoyer', {}, { headers: { Authorization: `Bearer ${token}` } });
    }

    /**
     * Confirme l'adresse avec le jeton du lien reçu par e-mail.
     * @param {string} jeton
     * @returns {Promise<{emailVerifie: true}>}
     * @throws {ValidationError} code `LIEN_COMPTE_INVALIDE`
     */
    async verifierEmail(jeton) {
        return this.client.post('/api/visiteur/email/verifier', { jeton });
    }

    /**
     * Demande un lien de réinitialisation du mot de passe. Même réponse que le compte existe ou non.
     * @param {string} email
     * @returns {Promise<{message: string}>}
     * @throws {ValidationError} code `VALIDATION` ; {NavinumAPIError} `TROP_DE_TENTATIVES` (429)
     */
    async motDePasseOublie(email) {
        return this.client.post('/api/visiteur/mot-de-passe/oublie', { email });
    }

    /**
     * Nouveau mot de passe avec le jeton du lien reçu par e-mail (1 h, une seule fois) ; toutes les sessions du
     * compte sont révoquées.
     * @param {string} jeton
     * @param {string} motDePasse - 8 caractères au moins
     * @returns {Promise<{message: string}>}
     * @throws {ValidationError} codes `LIEN_COMPTE_INVALIDE`, `VALIDATION`
     */
    async reinitialiserMotDePasse(jeton, motDePasse) {
        return this.client.post('/api/visiteur/mot-de-passe/reinitialiser', { jeton, motDePasse });
    }

    /**
     * Fournisseurs OAuth actifs sur ce Navinum (Google, Microsoft, Facebook… selon la configuration du serveur).
     * @returns {Promise<Array<{nom: string, libelle: string}>>}
     */
    async fournisseursOAuth() {
        const res = await this.client.get('/api/visiteur/oauth/fournisseurs');
        return (res && res.fournisseurs) || [];
    }

    /**
     * Démarre une connexion OAuth : rend l'adresse du fournisseur où envoyer le joueur.
     * @param {string} nom - Fournisseur (`google`, `microsoft`, `facebook`…)
     * @param {{retour: string, memoriser?: boolean}} data - Page de retour (même site)
     * @param {string} [jetonConnecte] - Joueur connecté : le fournisseur lui sera lié
     * @returns {Promise<{url: string}>}
     */
    async demarrerOAuth(nom, data, jetonConnecte) {
        return this.client.post(`/api/visiteur/oauth/${encodeURIComponent(nom)}/demarrer`, data,
            jetonConnecte ? { headers: { Authorization: `Bearer ${jetonConnecte}` } } : {});
    }

    /**
     * Échange le code à usage unique reçu au retour du fournisseur contre la session du joueur.
     * @param {string} code
     * @returns {Promise<{visiteur: VisiteurProfil, token: string, expiresAt: string, appareil: string, fournisseur: string, memoriser: boolean}>}
     */
    async echangeOAuth(code) {
        return this.client.post('/api/visiteur/oauth/echange', { code });
    }

    /**
     * @deprecated Depuis 0.9.0 : utiliser `api.auth.connecter({ pseudo, memoriser })` (module d'authentification).
     * @param {string} pseudo
     * @param {Object} [options={}]
     * @returns {Promise<Object>}
     */
    async identifier(pseudo, options = {}) {
        return this.auth.modeInvite.connecter({ pseudo, memoriser: options.memoriser });
    }

    /**
     * @deprecated Depuis 0.9.0 : utiliser `api.auth.joueur()`.
     * @returns {Object|null} Le joueur conservé sur cet appareil, sans appel réseau
     */
    courant() {
        return this.auth.stockage.lire();
    }

    /**
     * @deprecated Depuis 0.9.0 : utiliser `api.auth.oublier()`.
     */
    oublier() {
        this.auth.stockage.effacer();
    }

    /**
     * @deprecated Endpoint inexistant côté serveur : sera remplacé par l'identification par badge
     * du futur module d'authentification.
     * @param {string} rfidTag - Tag RFID du visiteur
     */
    async getByRfid(rfidTag) {
        return this.client.get(`${this.basePath}/rfid/${rfidTag}`);
    }

    /**
     * @deprecated Endpoint inexistant côté serveur : utiliser {@link VisiteurAPI#inviter} (prototype)
     * en attendant le module d'authentification.
     * @param {Object} data - Données du visiteur
     */
    async register(data) {
        return this.client.post(`${this.basePath}/register`, data);
    }
}

export default VisiteurAPI;
