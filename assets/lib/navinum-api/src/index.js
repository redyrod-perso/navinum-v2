import { Config } from './core/Config.js';
import { ApiClient } from './core/ApiClient.js';
import {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError
} from './core/Errors.js';

import { SessionAPI } from './resources/SessionAPI.js';
import { ExpositionAPI } from './resources/ExpositionAPI.js';
import { ParcoursAPI } from './resources/ParcoursAPI.js';
import { InteractifAPI } from './resources/InteractifAPI.js';
import { VisiteurAPI } from './resources/VisiteurAPI.js';
import { RfidAPI } from './resources/RfidAPI.js';
import { RfidGroupeAPI } from './resources/RfidGroupeAPI.js';
import { LogVisiteAPI } from './resources/LogVisiteAPI.js';
import { LaunchAPI, LAUNCH_TOKEN_PARAM } from './resources/LaunchAPI.js';

/**
 * Navinum API Client
 * Librairie JavaScript pour interagir avec les APIs Symfony Navinum
 *
 * @example
 * // Initialisation
 * const api = new NavinumAPI({
 *   baseURL: 'http://localhost:8002',
 *   debug: true,
 *   timeout: 10000
 * });
 *
 * // Utilisation
 * const session = await api.sessions.create({ playerName: 'Alice' });
 * const expositions = await api.expositions.getAll();
 */
export class NavinumAPI {
    /**
     * @param {Object} options - Configuration options
     * @param {string} options.baseURL - Base URL de l'API (défaut: window.location.origin)
     * @param {number} options.timeout - Timeout en ms (défaut: 5000)
     * @param {Object} options.headers - Headers HTTP personnalisés
     * @param {boolean} options.debug - Mode debug (défaut: false)
     * @param {number} options.retryAttempts - Nombre de tentatives (défaut: 2)
     * @param {number} options.retryDelay - Délai entre tentatives en ms (défaut: 1000)
     * @param {string} options.mercureHub - Hub Mercure, relatif à baseURL ou absolu (défaut: /.well-known/mercure)
     * @param {Storage|null} options.storage - Stockage du visiteur identifié (défaut: localStorage ; null : rien conserver)
     */
    constructor(options = {}) {
        this.config = new Config(options);
        this.client = new ApiClient(this.config);

        // Initialisation de toutes les ressources
        this.sessions = new SessionAPI(this.client);
        this.expositions = new ExpositionAPI(this.client);
        this.parcours = new ParcoursAPI(this.client);
        this.interactifs = new InteractifAPI(this.client);
        this.visiteurs = new VisiteurAPI(this.client);
        this.rfid = new RfidAPI(this.client);
        this.rfidGroupes = new RfidGroupeAPI(this.client);
        this.logVisites = new LogVisiteAPI(this.client);
        this.launches = new LaunchAPI(this.client);
    }

    /**
     * Récupère le contexte de lancement de l'interactif (jeton `?nv=`).
     *
     * Navinum ouvre un interactif via `<url>?nv=<token>`. Cette méthode lit le jeton
     * (ou utilise celui fourni), appelle `GET /api/launch/{token}` et retourne
     * la configuration fusionnée du parcours, une fonction `track()` pour envoyer
     * des événements (`POST /api/launch/{token}/events`) et `onConfigChange()` pour
     * suivre les modifications de configuration publiées sur Mercure.
     *
     * @param {string|null} [token] - Jeton explicite ; à défaut, lu dans `window.location.search` (`nv`)
     * @returns {Promise<LaunchContext|null>} `{ token, interactif, parcours, exposition, config, visiteur, expiresAt, mercure, track(type, payload), onConfigChange(callback) }`,
     *          ou null si aucun jeton n'est présent (mode autonome / développement)
     * @throws {NotFoundError} Si le jeton est inconnu (404)
     * @throws {GoneError} Si le jeton a expiré (410)
     * @throws {NetworkError} En cas d'erreur réseau
     *
     * @example
     * const ctx = await api.launch();
     * if (ctx) {
     *     console.log('Parcours :', ctx.parcours?.libelle, 'config :', ctx.config);
     *     await ctx.track('quiz.completed', { score: 8, total: 10 });
     *     // Mises à jour de configuration en direct (Mercure) ; no-op si ctx.mercure est null
     *     const unsubscribe = ctx.onConfigChange(config => console.log('Nouvelle config', config));
     * } else {
     *     // Pas de ?nv= : utiliser la configuration par défaut
     * }
     */
    async launch(token) {
        return this.launches.resolve(token);
    }

    /**
     * Indique si la page courante a été lancée avec un jeton `?nv=`.
     *
     * @param {string} [search=window.location.search] - Query string à analyser
     * @returns {string|null} Le jeton, ou null
     *
     * @example
     * if (NavinumAPI.getLaunchToken()) { ... }
     */
    static getLaunchToken(search) {
        return LaunchAPI.getTokenFromUrl(search);
    }

    /**
     * Ajoute un intercepteur de requête
     * @param {Function} callback - Fonction appelée avant chaque requête
     */
    addRequestInterceptor(callback) {
        this.client.addRequestInterceptor(callback);
    }

    /**
     * Ajoute un intercepteur de réponse
     * @param {Function} callback - Fonction appelée après chaque réponse
     */
    addResponseInterceptor(callback) {
        this.client.addResponseInterceptor(callback);
    }

    /**
     * Active/désactive le mode debug
     * @param {boolean} enabled
     */
    setDebug(enabled) {
        this.config.debug = enabled;
    }

    /**
     * Modifie le baseURL
     * @param {string} url
     */
    setBaseURL(url) {
        this.config.baseURL = url;
    }
}

// Attacher les erreurs à la classe NavinumAPI pour accès facile
NavinumAPI.NavinumAPIError = NavinumAPIError;
NavinumAPI.NetworkError = NetworkError;
NavinumAPI.ValidationError = ValidationError;
NavinumAPI.UnauthorizedError = UnauthorizedError;
NavinumAPI.NotFoundError = NotFoundError;
NavinumAPI.GoneError = GoneError;
NavinumAPI.LAUNCH_TOKEN_PARAM = LAUNCH_TOKEN_PARAM;
NavinumAPI.ServerError = ServerError;

// Export des erreurs pour utilisation externe
export {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError,
    LaunchAPI,
    RfidGroupeAPI
};

// Export par défaut
export default NavinumAPI;
