/**
 * Temps réel Navinum : abonnement au hub Mercure (intégré à FrankenPHP).
 *
 * Les interactifs n'ouvrent jamais d'`EventSource` eux-mêmes : ils passent par les méthodes
 * de la librairie (`api.sessions.subscribe()`, `api.rfidGroupes.onCreated()`, `ctx.onConfigChange()`),
 * qui s'appuient toutes sur ce module.
 *
 * @module core/Realtime
 */

/** Chemin du hub Mercure intégré à Navinum (même domaine que l'API). */
export const DEFAULT_MERCURE_HUB = '/.well-known/mercure';

/**
 * URL absolue du hub Mercure.
 *
 * @param {string|null|undefined} hub - Hub configuré (absolu, ou relatif à `baseURL`)
 * @param {string} baseURL - URL de base de l'API Navinum
 * @returns {string}
 */
export function resolveHubUrl(hub, baseURL) {
    return new URL(hub || DEFAULT_MERCURE_HUB, baseURL).toString();
}

/**
 * S'abonne à un ou plusieurs topics Mercure.
 *
 * Les messages sont décodés en JSON ; les messages illisibles sont ignorés. La reconnexion est
 * assurée par `EventSource` ; `onOpen` est appelé à chaque (re)connexion, ce qui permet de
 * resynchroniser un état manqué pendant une coupure.
 *
 * @param {string} hubUrl - URL absolue du hub
 * @param {string|string[]} topics - Topic(s) à écouter
 * @param {function(Object): void} onMessage - Reçoit chaque message décodé
 * @param {Object} [options={}]
 * @param {function(): void} [options.onOpen] - Appelé à chaque (re)connexion
 * @param {function(Event): void} [options.onError] - Appelé en cas d'erreur de connexion
 * @param {function(...*): void} [options.log] - Journalisation (mode debug)
 * @returns {function(): void} Fonction de désabonnement (idempotente). Fonction vide si
 *          `EventSource` n'existe pas dans l'environnement.
 */
export function subscribe(hubUrl, topics, onMessage, options = {}) {
    const noop = () => {};
    const log = options.log || noop;
    const EventSourceImpl = options.EventSource || (typeof EventSource !== 'undefined' ? EventSource : null);

    if (!EventSourceImpl) {
        log('EventSource indisponible : pas de temps réel');
        return noop;
    }

    const url = new URL(hubUrl);
    for (const topic of [].concat(topics)) {
        url.searchParams.append('topic', topic);
    }
    log('Abonnement Mercure', url.toString());

    let source = new EventSourceImpl(url.toString());
    source.onopen = () => {
        if (options.onOpen) options.onOpen();
    };
    source.onerror = (event) => {
        if (options.onError) options.onError(event);
    };
    source.onmessage = (event) => {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch (error) {
            log('Message Mercure illisible, ignoré', event.data);
            return;
        }
        onMessage(data);
    };

    return () => {
        if (source) {
            source.close();
            source = null;
        }
    };
}
