/**
 * Configuration de la librairie Navinum API
 */
export class Config {
    constructor(options = {}) {
        this.baseURL = options.baseURL || (typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
        this.timeout = options.timeout || 30000; // 30 secondes pour la production
        this.headers = options.headers || {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        this.debug = options.debug || false;
        this.retryAttempts = options.retryAttempts || 3; // 3 tentatives
        this.retryDelay = options.retryDelay || 2000; // 2 secondes entre les tentatives
        // Hub Mercure (temps réel) : relatif à baseURL par défaut, ou URL absolue d'un autre hub
        this.mercureHub = options.mercureHub || '/.well-known/mercure';
        // Stockage du visiteur identifié (localStorage par défaut ; null pour ne rien conserver)
        this.storage = options.storage !== undefined
            ? options.storage
            : (typeof localStorage !== 'undefined' ? localStorage : null);
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
