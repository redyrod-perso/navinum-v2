import { BaseResource } from './BaseResource.js';
import { ValidationError } from '../core/Errors.js';
import { resolveHubUrl, subscribe } from '../core/Realtime.js';

/**
 * API pour gérer les sessions multi-joueurs
 */
export class SessionAPI extends BaseResource {
    constructor(client) {
        super(client, 'session', { basePath: '/api/session' });
    }

    /**
     * Crée une nouvelle session ou rejoint une session existante
     * @param {Object} data - { playerName, sessionId? }
     */
    async create(data) {
        return this.client.post('/api/session/create', data);
    }

    /**
     * Rejoint une session existante
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     */
    async join(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/join`, data);
    }

    /**
     * Quitte une session
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     */
    async leave(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/leave`, data);
    }

    /**
     * Démarre une session (le jeu)
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { theme }
     */
    async start(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/start`, data);
    }

    /**
     * Met à jour le score d'un joueur
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName, score }
     */
    async updateScore(sessionId, data) {
        return this.client.post(`/api/session/${sessionId}/score`, data);
    }

    /**
     * Réinitialise une session
     * @param {string} sessionId - Identifiant de la session
     */
    async reset(sessionId) {
        return this.client.post(`/api/session/${sessionId}/reset`);
    }

    /**
     * Supprime toutes les sessions
     */
    async clearAll() {
        return this.client.post('/api/sessions/clear');
    }

    /**
     * Récupère les informations d'une session
     * @param {string} sessionId - Identifiant de la session
     */
    async get(sessionId) {
        return this.client.get(`/api/session/${sessionId}`);
    }

    /**
     * Suit une session en temps réel (hub Mercure, topic `/navinum/sessions/{id}`).
     *
     * À chaque (re)connexion, l'état courant est relu une fois (`get`) pour ne rien manquer
     * (arrivée dans le lobby, coupure réseau) ; ensuite chaque changement côté serveur
     * (joueur qui rejoint, démarrage, scores, départ) est transmis au callback.
     *
     * @param {string} sessionId - Identifiant de la session (dans le quiz : le nom du groupe)
     * @param {function(Object): void} callback - Reçoit l'état complet de la session
     *        `{ id, status, theme, leader, players: [{ name, score, isLeader }], … }`
     * @param {Object} [options={}]
     * @param {function(Error): void} [options.onError] - Erreur de relecture de l'état
     * @returns {function(): void} Fonction de désabonnement
     *
     * @example
     * const stop = api.sessions.subscribe('Classe 4B', session => {
     *     afficherJoueurs(session.players);
     *     if (session.status === 'playing') demarrer(session.theme);
     * });
     * // en quittant la partie
     * stop();
     */
    subscribe(sessionId, callback, options = {}) {
        if (!sessionId) {
            throw new ValidationError('Identifiant de session manquant', { sessionId: 'requis' });
        }
        const config = this.client.config;
        let active = true;

        const resync = () => {
            this.get(sessionId)
                .then(res => { if (active && res && res.session) callback(res.session); })
                .catch(error => { if (options.onError) options.onError(error); });
        };

        const unsubscribe = subscribe(
            resolveHubUrl(config.mercureHub, config.baseURL),
            SESSION_TOPIC_PREFIX + sessionId,
            (data) => { if (active && data && data.type === 'session' && data.session) callback(data.session); },
            { onOpen: resync, log: (...args) => config.log(...args) }
        );

        return () => {
            active = false;
            unsubscribe();
        };
    }
}

/** Préfixe des topics Mercure de session publiés par Navinum. */
export const SESSION_TOPIC_PREFIX = '/navinum/sessions/';

export default SessionAPI;
