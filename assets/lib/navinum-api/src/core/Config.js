/**
 * Configuration de la librairie Navinum API
 */
export class Config {
    constructor(options = {}) {
        this.baseURL = options.baseURL || window.location.origin;
        this.timeout = options.timeout || 30000; // 30 secondes pour la production
        this.headers = options.headers || {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        this.debug = options.debug || false;
        this.retryAttempts = options.retryAttempts || 3; // 3 tentatives
        this.retryDelay = options.retryDelay || 2000; // 2 secondes entre les tentatives
    }

    /**
     * Log en mode debug
     */
    log(...args) {
        if (this.debug) {
            console.log('[Navinum API]', ...args);
        }
    }

    /**
     * Merge des headers
     */
    mergeHeaders(customHeaders = {}) {
        return {
            ...this.headers,
            ...customHeaders
        };
    }
}

export default Config;
