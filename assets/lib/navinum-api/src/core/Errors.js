/**
 * Erreur de base pour l'API Navinum
 */
export class NavinumAPIError extends Error {
    constructor(message, status = null, response = null) {
        super(message);
        this.name = 'NavinumAPIError';
        this.status = status;
        this.response = response;
        this.timestamp = new Date().toISOString();
    }

    /**
     * Retourne une représentation JSON de l'erreur
     */
    toJSON() {
        return {
            name: this.name,
            message: this.message,
            status: this.status,
            timestamp: this.timestamp
        };
    }
}

/**
 * Erreur réseau (timeout, connexion impossible, etc.)
 */
export class NetworkError extends NavinumAPIError {
    constructor(message, originalError = null) {
        super(message, null, null);
        this.name = 'NetworkError';
        this.originalError = originalError;
    }
}

/**
 * Erreur de validation (400)
 */
export class ValidationError extends NavinumAPIError {
    constructor(message, errors = {}) {
        super(message, 400, errors);
        this.name = 'ValidationError';
        this.errors = errors;
    }
}

/**
 * Erreur non autorisé (401)
 */
export class UnauthorizedError extends NavinumAPIError {
    constructor(message = 'Non autorisé') {
        super(message, 401, null);
        this.name = 'UnauthorizedError';
    }
}

/**
 * Erreur non trouvé (404)
 */
export class NotFoundError extends NavinumAPIError {
    constructor(message = 'Ressource non trouvée') {
        super(message, 404, null);
        this.name = 'NotFoundError';
    }
}

/**
 * Erreur ressource expirée (410)
 *
 * Levée notamment quand un jeton de lancement (`?nv=`) a expiré.
 *
 * @class
 * @example
 * try {
 *     const ctx = await api.launch();
 * } catch (error) {
 *     if (error instanceof NavinumAPI.GoneError) {
 *         alert('Ce lien a expiré, relancez l\'interactif depuis la borne.');
 *     }
 * }
 */
export class GoneError extends NavinumAPIError {
    constructor(message = 'Ressource expirée') {
        super(message, 410, null);
        this.name = 'GoneError';
    }
}

/**
 * Erreur serveur (500+)
 */
export class ServerError extends NavinumAPIError {
    constructor(message = 'Erreur serveur', status = 500) {
        super(message, status, null);
        this.name = 'ServerError';
    }
}

export default {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError
};
