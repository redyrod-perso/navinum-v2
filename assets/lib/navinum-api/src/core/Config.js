/**
 * Configuration de la librairie Navinum API
 */
export class Config {
    constructor(options = {}) {
        this.baseURL = options.baseURL || window.location.origin;
        this.timeout = options.timeout || 5000;
        this.headers = options.headers || {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        this.debug = options.debug || false;
        this.retryAttempts = options.retryAttempts || 2;
        this.retryDelay = options.retryDelay || 1000;
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
