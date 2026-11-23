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
     * Chemin d'une session : l'identifiant (nom du groupe) est encodé (espaces, `?`, `#`, accents…).
     * @param {string} sessionId
     * @returns {string}
     * @private
     */
    path(sessionId) {
        if (!sessionId) {
            throw new ValidationError('Identifiant de session manquant', { sessionId: 'requis' });
        }
        return `/api/session/${encodeURIComponent(sessionId)}`;
    }

    /**
     * Quitte la session quand la page est fermée ou rechargée (requête `keepalive`, envoyée même
     * si la page disparaît) : le joueur ne reste pas « fantôme » dans le lobby.
     *
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     * @returns {function(): void} Fonction de retrait (à appeler quand le joueur quitte normalement)
     *
     * @example
     * const stop = api.sessions.quitterEnPartant('Classe 4B', { playerName: 'Léa' });
     * // départ normal :
     * stop(); await api.sessions.leave('Classe 4B', { playerName: 'Léa' });
     */
    quitterEnPartant(sessionId, data) {
        if (typeof window === 'undefined' || !window.addEventListener) {
            return () => {};
        }
        const path = `${this.path(sessionId)}/leave`;
        const onPageHide = () => {
            this.client.executeRequest('POST', path, data, { keepalive: true }).catch(() => {});
        };
        window.addEventListener('pagehide', onPageHide);
        return () => window.removeEventListener('pagehide', onPageHide);
    }

    /**
     * Crée une nouvelle session ou rejoint une session existante
     * @param {Object} data - { playerName, sessionId?, rfidGroupeName?, launchToken? } — `launchToken` (jeton `?nv=`)
     *        fixe le nombre maximum de joueurs (`joueursMax` de la configuration du lancement ; 8 sans jeton)
     * @throws {NavinumAPIError} code `GROUPE_COMPLET` (409) si le groupe est plein
     */
    async create(data) {
        return this.client.post('/api/session/create', data);
    }

    /**
     * Rejoint une session existante
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName, launchToken? }
     * @throws {NavinumAPIError} code `GROUPE_COMPLET` (409) si le groupe est plein, `PARTIE_EN_COURS` (409)
     */
    async join(sessionId, data) {
        return this.client.post(`${this.path(sessionId)}/join`, data);
    }

    /**
     * Quitte une session
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName }
     */
    async leave(sessionId, data) {
        return this.client.post(`${this.path(sessionId)}/leave`, data);
    }

    /**
     * Démarre une session (le jeu)
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { theme }
     */
    async start(sessionId, data) {
        return this.client.post(`${this.path(sessionId)}/start`, data);
    }

    /**
     * Met à jour le score d'un joueur (partie en cours, +1 au plus par appel ; renvoyer le même score est sans effet)
     * @param {string} sessionId - Identifiant de la session
     * @param {Object} data - { playerName, score }
     * @throws {NavinumAPIError} code `SCORE_REFUSE` (409) si le score n'est pas plausible
     */
    async updateScore(sessionId, data) {
        return this.client.post(`${this.path(sessionId)}/score`, data);
    }

    /**
     * Réinitialise une session
     * @param {string} sessionId - Identifiant de la session
     */
    async reset(sessionId) {
        return this.client.post(`${this.path(sessionId)}/reset`);
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
        return this.client.get(this.path(sessionId));
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
