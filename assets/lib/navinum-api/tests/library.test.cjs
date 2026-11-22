/**
 * Tests de la librairie @navinum/api, exécutés sur le bundle construit (dist/navinum-api.js),
 * c'est-à-dire le fichier réellement chargé par les interactifs.
 *
 *   npm run build:dev && npm test
 *
 * fetch, EventSource et le stockage sont simulés : aucun serveur n'est nécessaire.
 */
const { test, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const NavinumAPI = require(path.join(__dirname, '..', 'dist', 'navinum-api.js'));

// --- Doubles de test ---------------------------------------------------------------------------

/** fetch simulé : routes "METHODE chemin" → { status, body, contentType } ou fonction(requête). */
function fakeFetch(routes) {
    const calls = [];
    const fn = async (url, init = {}) => {
        const u = new URL(url);
        const method = init.method || 'GET';
        const call = { method, path: decodeURIComponent(u.pathname), search: u.search, headers: init.headers || {}, body: init.body ? JSON.parse(init.body) : null };
        calls.push(call);
        let route = routes[`${method} ${call.path}`];
        if (typeof route === 'function') route = route(call);
        if (!route) route = { status: 404, body: { error: 'introuvable' } };
        if (route.throw) throw route.throw;
        const contentType = route.contentType || 'application/json';
        return {
            ok: route.status >= 200 && route.status < 300,
            status: route.status,
            headers: { get: (h) => (h.toLowerCase() === 'content-type' ? contentType : null) },
            json: async () => route.body,
            text: async () => JSON.stringify(route.body),
        };
    };
    fn.calls = calls;
    return fn;
}

/** EventSource simulé : garde les instances ouvertes pour pouvoir y « publier ». */
class FakeEventSource {
    constructor(url) {
        this.url = url;
        this.closed = false;
        FakeEventSource.instances.push(this);
    }
    close() { this.closed = true; }
    open() { if (this.onopen) this.onopen({}); }
    emit(data) { if (this.onmessage) this.onmessage({ data: JSON.stringify(data) }); }
    get topics() { return new URL(this.url).searchParams.getAll('topic'); }
}
FakeEventSource.instances = [];

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const flush = () => new Promise((r) => setTimeout(r, 0));

function makeApi(routes, extra = {}) {
    global.fetch = fakeFetch(routes);
    return new NavinumAPI({ baseURL: 'http://navinum.test', retryAttempts: 1, storage: memoryStorage(), ...extra });
}

beforeEach(() => {
    FakeEventSource.instances = [];
    global.EventSource = FakeEventSource;
});

// --- ApiClient ---------------------------------------------------------------------------------

test('les réponses application/ld+json (API Platform) sont décodées en objet', async () => {
    const api = makeApi({ 'GET /api/rfid_groupes': { status: 200, contentType: 'application/ld+json; charset=utf-8', body: { member: [{ id: '1', nom: 'A' }] } } });
    const groupes = await api.rfidGroupes.list();
    assert.deepEqual(groupes, [{ id: '1', nom: 'A' }]);
});

// --- Groupes -----------------------------------------------------------------------------------

test('rfidGroupes : recherche exacte par nom et création en JSON-LD', async () => {
    const api = makeApi({
        'GET /api/rfid_groupes': { status: 200, contentType: 'application/ld+json', body: { member: [{ id: '1', nom: 'Classe 4B bis' }, { id: '2', nom: 'Classe 4B' }] } },
        'POST /api/rfid_groupes': (call) => ({ status: 201, contentType: 'application/ld+json', body: { id: '3', nom: call.body.nom } }),
    });
    assert.equal((await api.rfidGroupes.findByNom('  Classe 4B ')).id, '2');
    assert.equal(fetch.calls[0].search, '?nom=Classe+4B');

    const cree = await api.rfidGroupes.create(' Duo ');
    assert.deepEqual(cree, { id: '3', nom: 'Duo' });
    const post = fetch.calls.find((c) => c.method === 'POST');
    assert.equal(post.headers['Content-Type'], 'application/ld+json');

    await assert.rejects(() => api.rfidGroupes.create('  '), NavinumAPI.ValidationError);
});

test('rfidGroupes.onCreated : abonnement Mercure au topic rfid-groupes', () => {
    const api = makeApi({});
    const recus = [];
    const stop = api.rfidGroupes.onCreated((g) => recus.push(g.nom));
    const es = FakeEventSource.instances[0];
    assert.equal(new URL(es.url).origin + new URL(es.url).pathname, 'http://navinum.test/.well-known/mercure');
    assert.deepEqual(es.topics, ['rfid-groupes']);
    es.emit({ type: 'groupe_created', groupe: { id: '9', nom: 'Nouveau' } });
    es.emit({ type: 'autre' });
    assert.deepEqual(recus, ['Nouveau']);
    stop();
    assert.equal(es.closed, true);
});

// --- Sessions en temps réel --------------------------------------------------------------------

test('sessions.subscribe : relit l’état à la connexion puis suit les mises à jour', async () => {
    const api = makeApi({ 'GET /api/session/Classe 4B': { status: 200, body: { session: { id: 'Classe 4B', status: 'lobby', players: [{ name: 'Léa' }] } } } });
    const etats = [];
    const stop = api.sessions.subscribe('Classe 4B', (s) => etats.push(`${s.status}:${s.players.length}`));
    const es = FakeEventSource.instances[0];
    assert.deepEqual(es.topics, ['/navinum/sessions/Classe 4B']);

    es.open(); // (re)connexion → relecture
    await flush(); await flush();
    es.emit({ type: 'session', session: { status: 'lobby', players: [{ name: 'Léa' }, { name: 'Tom' }] } });
    es.emit({ type: 'session', session: { status: 'playing', players: [{ name: 'Léa' }, { name: 'Tom' }] } });
    assert.deepEqual(etats, ['lobby:1', 'lobby:2', 'playing:2']);

    stop();
    es.emit({ type: 'session', session: { status: 'finished', players: [] } });
    assert.equal(es.closed, true);
    assert.equal(etats.length, 3, 'plus aucun appel après désabonnement');
});

// --- Identification ----------------------------------------------------------------------------

test('visiteurs.identifier : crée un invité, puis le réutilise pour le même pseudo', async () => {
    let invites = 0;
    const api = makeApi({
        'POST /api/visiteur/invite': (call) => ({ status: 201, body: { visiteur: { id: `v${++invites}`, pseudo: call.body.pseudo, anonyme: true }, token: `nvv1.v${invites}.sig` } }),
        'GET /api/visiteur/moi': { status: 200, body: { visiteur: { id: 'v1' } } },
    });

    const zoe = await api.visiteurs.identifier('Zoé');
    assert.equal(zoe.id, 'v1');
    assert.equal(api.visiteurs.courant().token, 'nvv1.v1.sig');

    const encore = await api.visiteurs.identifier(' Zoé ');
    assert.equal(encore.id, 'v1', 'même visiteur réutilisé');
    assert.equal(invites, 1);
    assert.equal(fetch.calls.at(-1).headers.Authorization, 'Bearer nvv1.v1.sig');

    const autre = await api.visiteurs.identifier('Tom');
    assert.equal(autre.id, 'v2', 'autre pseudo : nouvel invité');

    api.visiteurs.oublier();
    assert.equal(api.visiteurs.courant(), null);
});

test('visiteurs.identifier : jeton invalide → nouvel invité ; API injoignable → visiteur conservé', async () => {
    let invites = 0;
    let moi = { status: 401, body: { error: 'Jeton visiteur absent ou invalide' } };
    const api = makeApi({
        'POST /api/visiteur/invite': (call) => ({ status: 201, body: { visiteur: { id: `v${++invites}`, pseudo: call.body.pseudo, anonyme: true }, token: `t${invites}` } }),
        'GET /api/visiteur/moi': () => moi,
    });
    await api.visiteurs.identifier('Léa');
    assert.equal((await api.visiteurs.identifier('Léa')).id, 'v2', 'jeton refusé (401) : réinvité');

    moi = { throw: new TypeError('Failed to fetch') };
    assert.equal((await api.visiteurs.identifier('Léa')).id, 'v2', 'hors ligne : on garde le visiteur connu');
});

test('stockage désactivé (storage: null) : rien n’est conservé', async () => {
    const api = makeApi({ 'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'v1', pseudo: 'Ana' }, token: 't' } } }, { storage: null });
    await api.visiteurs.identifier('Ana');
    assert.equal(api.visiteurs.courant(), null);
});

// --- Lancement ---------------------------------------------------------------------------------

test('launch : contexte, identify et configuration à chaud', async () => {
    const token = 'a'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: { duree: 20 }, visiteur: null, mercure: { hub: 'http://navinum.test/.well-known/mercure', topics: { config: '/navinum/parcours-interactif/p/config' } } } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: { visiteur: { id: 'v1', pseudo: 'Léa', anonyme: true } } },
    });
    const ctx = await api.launch(token);
    assert.equal(ctx.config.duree, 20);

    assert.equal((await ctx.identify('nvv1.v1.sig')).pseudo, 'Léa');
    assert.equal(ctx.visiteur.id, 'v1');
    assert.equal(fetch.calls.at(-1).headers.Authorization, 'Bearer nvv1.v1.sig');

    const configs = [];
    const stop = ctx.onConfigChange((c) => configs.push(c.duree));
    const es = FakeEventSource.instances[0];
    assert.deepEqual(es.topics, ['/navinum/parcours-interactif/p/config']);
    es.emit({ config: { duree: 45 } });
    assert.deepEqual(configs, [45]);
    stop();
    assert.equal(es.closed, true);
});

test('launch sans jeton : null (mode autonome)', async () => {
    const api = makeApi({});
    assert.equal(await api.launches.resolve(null), null);
});

// --- Manifeste ---------------------------------------------------------------------------------

test('interactifs.chargerManifeste : valeurs par défaut du configSchema', async () => {
    const api = makeApi({
        'GET /quiz/navinum.json': { status: 200, body: { libelle: 'Quiz', configSchema: { type: 'object', properties: {
            duree: { type: 'integer', default: 20 },
            options: { type: 'object', properties: { son: { type: 'boolean', default: true } } },
            sansDefaut: { type: 'string' },
        } } } },
    });
    const { manifest, defaults } = await api.interactifs.chargerManifeste('/quiz/navinum.json');
    assert.equal(manifest.libelle, 'Quiz');
    assert.deepEqual(defaults, { duree: 20, options: { son: true } });
});

// --- Messages d'erreur ---------------------------------------------------------------------------

test('le message d’erreur du serveur est transmis (et non remplacé par « Données invalides »)', async () => {
    const api = makeApi({
        'POST /api/session/G/join': { status: 409, body: { error: 'Une partie est en cours dans ce groupe : attendez la fin ou choisissez un autre groupe.' } },
        'POST /api/session/H/join': { status: 400, body: { error: 'Nom du joueur manquant' } },
        'POST /api/rfid_groupes': { status: 422, contentType: 'application/problem+json', body: { detail: 'nom: Un groupe avec ce nom existe déjà.' } },
    });

    const conflit = await api.sessions.join('G', { playerName: 'Ana' }).catch((e) => e);
    assert.ok(conflit instanceof NavinumAPI.NavinumAPIError);
    assert.equal(conflit.status, 409);
    assert.match(conflit.message, /partie est en cours/);

    const invalide = await api.sessions.join('H', {}).catch((e) => e);
    assert.ok(invalide instanceof NavinumAPI.ValidationError);
    assert.equal(invalide.message, 'Nom du joueur manquant');

    const doublon = await api.rfidGroupes.create('Classe').catch((e) => e);
    assert.ok(doublon instanceof NavinumAPI.ValidationError);
    assert.match(doublon.message, /existe déjà/);
});
