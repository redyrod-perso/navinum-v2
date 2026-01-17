import { Config } from './core/Config.js';
import { ApiClient } from './core/ApiClient.js';
import {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    ServerError
} from './core/Errors.js';

import { SessionAPI } from './resources/SessionAPI.js';
import { ExpositionAPI } from './resources/ExpositionAPI.js';
import { ParcoursAPI } from './resources/ParcoursAPI.js';
import { InteractifAPI } from './resources/InteractifAPI.js';
import { VisiteurAPI } from './resources/VisiteurAPI.js';
import { RfidAPI } from './resources/RfidAPI.js';
import { LogVisiteAPI } from './resources/LogVisiteAPI.js';

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
        this.logVisites = new LogVisiteAPI(this.client);
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
NavinumAPI.ServerError = ServerError;

// Export des erreurs pour utilisation externe
export {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    ServerError
};

// Export par défaut
export default NavinumAPI;
