import {
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
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
     * Gère les erreurs HTTP
     */
    handleError(status, response) {
        switch (status) {
            case 400:
                throw new ValidationError(
                    response?.message || 'Données invalides',
                    response?.errors || {}
                );
            case 401:
                throw new UnauthorizedError(response?.message);
            case 404:
                throw new NotFoundError(response?.message);
            case 500:
            case 502:
            case 503:
                throw new ServerError(response?.message || 'Erreur serveur', status);
            default:
                throw new NavinumAPIError(
                    response?.message || `Erreur HTTP ${status}`,
                    status,
                    response
                );
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

        try {
            // Créer un AbortController pour le timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.config.timeout);

            const response = await fetch(url, {
                ...requestConfig,
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            this.config.log(`Response ${response.status}:`, response);

            // Parser la réponse
            let responseData = null;
            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
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
