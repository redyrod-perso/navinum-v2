import {
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError,
    NavinumAPIError
} from './Errors.js';

/**
 * Client HTTP pour l'API Navinum
 */
export class ApiClient {
    constructor(config) {
        this.config = config;
        this.requestInterceptors = [];
        this.responseInterceptors = [];
        this.errorListeners = [];
    }

    /**
     * Ajoute un écouteur appelé pour chaque erreur levée par une requête (après les tentatives).
     * @param {function(Error): void} fn
     * @returns {function(): void} Fonction de retrait
     */
    addErrorListener(fn) {
        this.errorListeners.push(fn);
        return () => { this.errorListeners = this.errorListeners.filter(l => l !== fn); };
    }

    /** @private */
    notifyError(error) {
        for (const listener of this.errorListeners) {
            try { listener(error); } catch (e) { /* un écouteur ne doit jamais casser la requête */ }
        }
    }

    /**
     * Ajoute un intercepteur avant la requête
     */
    addRequestInterceptor(fn) {
        this.requestInterceptors.push(fn);
    }

    /**
     * Ajoute un intercepteur après la réponse
     */
    addResponseInterceptor(fn) {
        this.responseInterceptors.push(fn);
    }

    /**
     * Exécute les intercepteurs de requête
     */
    async runRequestInterceptors(config) {
        let modifiedConfig = { ...config };
        for (const interceptor of this.requestInterceptors) {
            modifiedConfig = await interceptor(modifiedConfig);
        }
        return modifiedConfig;
    }

    /**
     * Exécute les intercepteurs de réponse
     */
    async runResponseInterceptors(response) {
        let modifiedResponse = response;
        for (const interceptor of this.responseInterceptors) {
            modifiedResponse = await interceptor(modifiedResponse);
        }
        return modifiedResponse;
    }

    /**
     * Construit l'URL complète
     */
    buildURL(endpoint, params = {}) {
        const url = new URL(endpoint, this.config.baseURL);

        // Ajouter les paramètres query
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });

        return url.toString();
    }

    /**
     * Message d'erreur renvoyé par le serveur, quel que soit son format :
     * `{ error }` (contrôleurs Navinum), `{ detail }` / `{ description }` (API Platform),
     * `{ message }`, ou texte brut.
     * @private
     */
    static serverMessage(response) {
        if (typeof response === 'string') {
            return response.trim() && response.length <= 300 ? response.trim() : null;
        }
        if (!response || typeof response !== 'object') {
            return null;
        }
        const message = response.error || response.message || response.detail || response.description || response['hydra:description'];
        return typeof message === 'string' && message.trim() ? message.trim() : null;
    }

    /**
     * Gère les erreurs HTTP : lève l'erreur typée correspondante, avec le message du serveur.
     */
    handleError(status, response) {
        const error = ApiClient.withBody(ApiClient.errorFor(status, response), response);
        error.status = status; // ex. 422 et non 400 pour une ValidationError d'API Platform
        throw error;
    }

    /**
     * Attache le corps de la réponse et le code métier éventuel (`code`) à l'erreur.
     * @private
     */
    static withBody(error, response) {
        error.body = response ?? null;
        const code = response && typeof response === 'object' ? response.code : null;
        error.code = typeof code === 'string' && code ? code : null;
        return error;
    }

    /**
     * Erreur typée correspondant à un statut HTTP, avec le message du serveur.
     * @private
     */
    static errorFor(status, response) {
        const message = ApiClient.serverMessage(response);
        switch (status) {
            case 400:
            case 422:
                return new ValidationError(message || 'Données invalides', response?.errors || response?.violations || {});
            case 401:
                return new UnauthorizedError(message || undefined);
            case 404:
                return new NotFoundError(message || undefined);
            case 410:
                return new GoneError(message || undefined);
            case 500:
            case 502:
            case 503:
                return new ServerError(message || 'Erreur serveur', status);
            default:
                // ex. 409 (conflit : partie déjà en cours…), 403 : message du serveur et code HTTP dans error.status
                return new NavinumAPIError(message || `Erreur HTTP ${status}`, status, response);
        }
    }

    /**
     * Effectue une requête HTTP avec retry
     */
    async requestWithRetry(method, endpoint, data = null, options = {}, attempt = 1) {
        try {
            return await this.executeRequest(method, endpoint, data, options);
        } catch (error) {
            // Retry seulement pour les erreurs réseau
            if (error instanceof NetworkError && attempt < this.config.retryAttempts) {
                this.config.log(`Retry ${attempt}/${this.config.retryAttempts - 1} après ${this.config.retryDelay}ms...`);
                await new Promise(resolve => setTimeout(resolve, this.config.retryDelay));
                return this.requestWithRetry(method, endpoint, data, options, attempt + 1);
            }
            this.notifyError(error);
            throw error;
        }
    }

    /**
     * Exécute la requête HTTP
     */
    async executeRequest(method, endpoint, data = null, options = {}) {
        const { params = {}, headers = {}, ...fetchOptions } = options;

        // Configuration de la requête
        let requestConfig = {
            method,
            headers: this.config.mergeHeaders(headers),
            ...fetchOptions
        };

        // Ajouter le body pour POST/PUT
        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            requestConfig.body = JSON.stringify(data);
        }

        // Intercepteurs de requête
        requestConfig = await this.runRequestInterceptors(requestConfig);

        const url = this.buildURL(endpoint, params);

        this.config.log(`${method} ${url}`, data);

        // AbortController pour le timeout (minuteur toujours libéré, même si fetch échoue)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.config.timeout);

        try {
            const response = await fetch(url, {
                ...requestConfig,
                signal: controller.signal
            });


            this.config.log(`Response ${response.status}:`, response);

            // Parser la réponse
            let responseData = null;
            const contentType = response.headers.get('content-type');

            // application/json, mais aussi application/ld+json (API Platform) et application/problem+json
            if (contentType && /[/+]json\b/.test(contentType)) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }

            // Gérer les erreurs HTTP
            if (!response.ok) {
                this.handleError(response.status, responseData);
            }

            // Intercepteurs de réponse
            responseData = await this.runResponseInterceptors(responseData);

            return responseData;

        } catch (error) {
            if (error.name === 'AbortError') {
                throw new NetworkError(`Timeout après ${this.config.timeout}ms`, error);
            }

            if (error instanceof NavinumAPIError) {
                throw error;
            }

            throw new NetworkError('Erreur de connexion au serveur', error);
        } finally {
            clearTimeout(timeoutId);
        }
    }

    /**
     * Méthode GET
     */
    async get(endpoint, options = {}) {
        return this.requestWithRetry('GET', endpoint, null, options);
    }

    /**
     * Méthode POST
     */
    async post(endpoint, data = null, options = {}) {
        return this.requestWithRetry('POST', endpoint, data, options);
    }

    /**
     * Méthode PUT
     */
    async put(endpoint, data = null, options = {}) {
        return this.requestWithRetry('PUT', endpoint, data, options);
    }

    /**
     * Méthode PATCH
     */
    async patch(endpoint, data = null, options = {}) {
        return this.requestWithRetry('PATCH', endpoint, data, options);
    }

    /**
     * Méthode DELETE
     */
    async delete(endpoint, options = {}) {
        return this.requestWithRetry('DELETE', endpoint, null, options);
    }
}

export default ApiClient;
