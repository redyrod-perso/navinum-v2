import { ValidationError } from '../core/Errors.js';
import { subscribe } from '../core/Realtime.js';

/**
 * Nom du paramètre d'URL portant le jeton de lancement.
 * Navinum ouvre un interactif via `<url de l'interactif>?nv=<token>`.
 * @type {string}
 */
export const LAUNCH_TOKEN_PARAM = 'nv';

/**
 * @typedef {Object} LaunchRef
 * @property {string} id - UUID de l'entité
 * @property {string} libelle - Libellé affichable
 */

/**
 * @typedef {Object} LaunchInteractif
 * @property {string} id - UUID de l'interactif
 * @property {string} libelle - Libellé de l'interactif
 * @property {string} version - Version déclarée de l'interactif
 */

/**
 * @typedef {Object} LaunchVisiteur
 * @property {string} id - UUID du visiteur
 * @property {string} pseudo - Pseudo du visiteur
 * @property {boolean} anonyme - true si le visiteur est anonyme
 */

/**
 * @typedef {Object} LaunchMercure
 * @property {string} hub - URL du hub Mercure (ex: `https://…/.well-known/mercure`)
 * @property {Object} topics - Topics disponibles
 * @property {string} topics.config - Topic publié à chaque enregistrement de la configuration
 *           dans l'admin ; message : `{"config": { …config effective complète… }}`
 */

/**
 * Contexte de lancement d'un interactif, retourné par `api.launch()`.
 *
 * @typedef {Object} LaunchContext
 * @property {string} token - Jeton de lancement utilisé
 * @property {LaunchInteractif} interactif - Interactif lancé
 * @property {LaunchRef|null} parcours - Parcours dans lequel l'interactif est lancé
 * @property {LaunchRef|null} exposition - Exposition du parcours
 * @property {Object} config - Configuration fusionnée (valeurs du parcours par-dessus les `default` du configSchema)
 * @property {LaunchVisiteur|null} visiteur - Visiteur identifié, ou null
 * @property {string} expiresAt - Date d'expiration du jeton (ISO-8601)
 * @property {LaunchMercure|null} mercure - Hub et topics Mercure, ou null (aperçu hors parcours)
 * @property {function(string, Object=): Promise<Object>} track - Envoie un événement
 *           (`type`, `payload`) rattaché à ce lancement. Voir {@link LaunchAPI#sendEvent}.
 * @property {function(function(Object): void): function(): void} onConfigChange - S'abonne aux
 *           mises à jour de configuration (Mercure) ; retourne la fonction de désabonnement.
 *           Voir {@link LaunchAPI.subscribeConfig}.
 * @property {function(string): Promise<LaunchVisiteur|null>} identify - Rattache le visiteur
 *           (jeton `nvv1.…`) à ce lancement et met à jour `visiteur`. Voir {@link LaunchAPI#identify}.
 */

/**
 * API de lancement des interactifs (jeton `?nv=`).
 *
 * Endpoints :
 * - `GET  /api/launch/{token}` : contexte de lancement (404 inconnu, 410 expiré)
 * - `POST /api/launch/{token}/visiteur` : rattachement du visiteur (Authorization: Bearer)
 * - `POST /api/launch/{token}/events` : envoi d’un événement (202)
 *
 * En pratique on utilise le raccourci {@link NavinumAPI#launch}.
 *
 * @class
 * @example
 * const ctx = await api.launches.get('abc123');
 * await api.launches.sendEvent('abc123', 'quiz.completed', { score: 8 });
 */
export class LaunchAPI {
    /**
     * @param {ApiClient} client - Client HTTP de la librairie
     */
    constructor(client) {
        this.client = client;
        this.basePath = '/api/launch';
    }

    /**
     * Lit le jeton de lancement dans une query string.
     *
     * @param {string} [search=window.location.search] - Query string à analyser (ex: `?nv=abc`)
     * @returns {string|null} Le jeton, ou null s'il est absent ou vide
     *
     * @example
     * LaunchAPI.getTokenFromUrl('?nv=abc123'); // 'abc123'
     * LaunchAPI.getTokenFromUrl('');           // null
     */
    static getTokenFromUrl(search) {
        const query = search !== undefined
            ? search
            : (typeof window !== 'undefined' ? window.location.search : '');
        const token = new URLSearchParams(query || '').get(LAUNCH_TOKEN_PARAM);
        return token && token.trim() ? token.trim() : null;
    }

    /**
     * S'abonne aux mises à jour de configuration publiées par Navinum sur Mercure.
     *
     * Ouvre un `EventSource` sur `<hub>?topic=<topics.config>` et appelle `callback(config)`
     * à chaque message `{"config": {...}}` (configuration effective complète). Les messages
     * illisibles ou sans objet `config` sont ignorés. La reconnexion est gérée par `EventSource`.
     *
     * @param {LaunchMercure|null} mercure - Bloc `mercure` du contexte de lancement
     * @param {function(Object): void} callback - Reçoit la nouvelle configuration
     * @param {Object} [options={}]
     * @param {function(...*): void} [options.log] - Fonction de log (mode debug)
     * @returns {function(): void} Fonction de désabonnement (ferme l'EventSource). Fonction vide
     *          si `mercure` (ou son topic config) est absent ou si `EventSource` n'existe pas.
     *
     * @example
     * const unsubscribe = LaunchAPI.subscribeConfig(ctx.mercure, config => console.log(config));
     * // plus tard
     * unsubscribe();
     */
    static subscribeConfig(mercure, callback, options = {}) {
        const noop = () => {};
        const topic = mercure && mercure.topics && mercure.topics.config;
        if (!mercure || !mercure.hub || !topic || typeof callback !== 'function') {
            return noop;
        }
        return subscribe(mercure.hub, topic, (data) => {
            if (data && data.config && typeof data.config === 'object') {
                callback(data.config);
            }
        }, { log: options.log, EventSource: options.EventSource });
    }

    /**
     * Construit le chemin d'un jeton (encodé).
     * @param {string} token
     * @returns {string}
     * @private
     */
    tokenPath(token) {
        if (!token || typeof token !== 'string') {
            throw new ValidationError('Jeton de lancement manquant', { token: 'requis' });
        }
        return `${this.basePath}/${encodeURIComponent(token)}`;
    }

    /**
     * Récupère le contexte brut d'un jeton de lancement.
     *
     * @param {string} token - Jeton de lancement
     * @returns {Promise<Object>} `{ interactif, parcours, exposition, config, visiteur, expiresAt }`
     * @throws {ValidationError} Si le jeton est vide
     * @throws {NotFoundError} Si le jeton est inconnu (404)
     * @throws {GoneError} Si le jeton a expiré (410)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * const data = await api.launches.get('abc123');
     * console.log(data.config);
     */
    async get(token) {
        return this.client.get(this.tokenPath(token));
    }

    /**
     * Envoie un événement émis par l'interactif pour ce lancement.
     *
     * @param {string} token - Jeton de lancement
     * @param {string} type - Type d'événement (ex: `quiz.completed`), déclaré dans le manifeste de l'interactif
     * @param {Object} [payload={}] - Données libres de l'événement
     * @returns {Promise<Object>} `{ accepted: true }`
     * @throws {ValidationError} Si le jeton ou le type est vide
     * @throws {NotFoundError} Si le jeton est inconnu (404)
     * @throws {GoneError} Si le jeton a expiré (410)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * await api.launches.sendEvent('abc123', 'quiz.completed', { score: 8, total: 10 });
     */
    async sendEvent(token, type, payload = {}) {
        if (!type || typeof type !== 'string') {
            throw new ValidationError("Type d'événement manquant", { type: 'requis' });
        }
        return this.client.post(`${this.tokenPath(token)}/events`, {
            type,
            payload: payload || {}
        });
    }

    /**
     * Rattache un visiteur identifié à ce lancement : ses événements alimenteront ensuite
     * son `LogVisite` (et sa gamification).
     *
     * @param {string} token - Jeton de lancement
     * @param {string} visiteurToken - Jeton visiteur (`nvv1.…`), obtenu par {@link VisiteurAPI#inviter}
     *        ou, plus tard, par le module d'authentification (compte en ligne, badge RFID)
     * @returns {Promise<Object>} Le contexte de lancement à jour (avec `visiteur`)
     * @throws {ValidationError} Si le jeton visiteur est vide
     * @throws {UnauthorizedError} Si le jeton visiteur est invalide (401)
     * @throws {NotFoundError} Si le jeton de lancement est inconnu (404)
     * @throws {GoneError} Si le jeton de lancement a expiré (410)
     * @throws {NavinumAPIError} 409 si le lancement appartient déjà à un autre visiteur
     *
     * @example
     * await api.launches.identify('abc123', 'nvv1.…');
     */
    async identify(token, visiteurToken) {
        if (!visiteurToken || typeof visiteurToken !== 'string') {
            throw new ValidationError('Jeton visiteur manquant', { visiteurToken: 'requis' });
        }
        return this.client.post(`${this.tokenPath(token)}/visiteur`, {}, {
            headers: { Authorization: `Bearer ${visiteurToken}` }
        });
    }

    /**
     * Résout un jeton et retourne le contexte de lancement enrichi d'une méthode `track()`.
     *
     * @param {string|null} [token] - Jeton explicite ; à défaut, lu dans `?nv=` de l'URL courante
     * @returns {Promise<LaunchContext|null>} Le contexte (avec `mercure`, `track()` et `onConfigChange()`), ou null si aucun jeton n'est disponible
     *          (interactif lancé hors Navinum : mode autonome / développement)
     * @throws {NotFoundError} Si le jeton est inconnu (404)
     * @throws {GoneError} Si le jeton a expiré (410)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * const ctx = await api.launches.resolve();
     * if (ctx) await ctx.track('quiz.started', {});
     */
    async resolve(token) {
        const effectiveToken = token || LaunchAPI.getTokenFromUrl();
        if (!effectiveToken) {
            return null;
        }

        const data = await this.get(effectiveToken);

        const ctx = {
            token: effectiveToken,
            interactif: data?.interactif ?? null,
            parcours: data?.parcours ?? null,
            exposition: data?.exposition ?? null,
            config: data?.config ?? {},
            visiteur: data?.visiteur ?? null,
            expiresAt: data?.expiresAt ?? null,
            mercure: data?.mercure ?? null,
            track: (type, payload = {}) => this.sendEvent(effectiveToken, type, payload),
            onConfigChange: (callback) => LaunchAPI.subscribeConfig(
                data?.mercure ?? null,
                callback,
                { log: (...args) => this.client.config.log(...args) }
            ),
            identify: async (visiteurToken) => {
                const updated = await this.identify(effectiveToken, visiteurToken);
                ctx.visiteur = updated?.visiteur ?? null;
                return ctx.visiteur;
            }
        };

        return ctx;
    }
}

export default LaunchAPI;
