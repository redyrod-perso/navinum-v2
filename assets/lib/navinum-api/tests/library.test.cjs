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
            text: async () => (typeof route.body === 'string' ? route.body : JSON.stringify(route.body)),
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

const dans = (minutes) => new Date(Date.now() + minutes * 60000).toISOString();

/** Serveur visiteur simulé : invités, reprise par appareil, renouvellement, déconnexion. */
function serveurVisiteurs() {
    const etat = { invites: 0, appareils: new Map(), revoques: new Set(), reprises: 0, renouvellements: 0 };
    const routes = {
        'POST /api/visiteur/invite': (call) => {
            const id = `v${++etat.invites}`;
            etat.appareils.set(`nva1.${id}`, { id, pseudo: call.body.pseudo });
            return { status: 201, body: { visiteur: { id, pseudo: call.body.pseudo, anonyme: true }, token: `nvv2.${id}.t0`, expiresAt: dans(30), appareil: `nva1.${id}` } };
        },
        'POST /api/visiteur/reprendre': (call) => {
            const a = etat.appareils.get(call.body.appareil);
            if (!a || a.pseudo !== call.body.pseudo || etat.revoques.has(call.body.appareil)) return { status: 401, body: { error: 'non reconnu', code: 'NON_IDENTIFIE' } };
            etat.reprises++;
            return { status: 200, body: { visiteur: { id: a.id, pseudo: a.pseudo, anonyme: true }, token: `nvv2.${a.id}.r${etat.reprises}`, expiresAt: dans(30) } };
        },
        'POST /api/visiteur/renouveler': () => ({ status: 200, body: { token: `nvv2.renouvele.${++etat.renouvellements}`, expiresAt: dans(30) } }),
        'POST /api/visiteur/deconnexion': (call) => { etat.deconnecte = call.headers.Authorization; return { status: 204, body: '', contentType: 'text/plain' }; },
        'GET /api/visiteur/moi': { status: 200, body: { visiteur: { id: 'v1' } } },
    };
    return { etat, routes };
}

test('visiteurs.identifier : session valide réutilisée ; expirée → même profil repris sur l’appareil', async () => {
    const { etat, routes } = serveurVisiteurs();
    const api = makeApi(routes);

    const zoe = await api.visiteurs.identifier('Zoé');
    assert.equal(zoe.id, 'v1');
    assert.equal(zoe.appareil, 'nva1.v1');
    assert.equal((await api.visiteurs.identifier(' Zoé ')).token, 'nvv2.v1.t0', 'session encore valide');
    assert.equal(fetch.calls.at(-1).path, '/api/visiteur/moi');

    // 30 min d'inactivité : jeton expiré → reprise, même visiteur
    api.client.config.storage.setItem('navinum.visiteur', JSON.stringify({ ...api.visiteurs.courant(), expiresAt: dans(-1) }));
    const reprise = await api.visiteurs.identifier('Zoé');
    assert.equal(reprise.id, 'v1', 'même profil (XP, médailles)');
    assert.equal(reprise.token, 'nvv2.v1.r1');
    assert.equal(reprise.appareil, 'nva1.v1', 'secret d’appareil conservé');
    assert.deepEqual(fetch.calls.at(-1).body, { pseudo: 'Zoé', appareil: 'nva1.v1' });
    assert.equal(etat.invites, 1);

    // Autre pseudo : nouvel invité
    assert.equal((await api.visiteurs.identifier('Tom')).id, 'v2');
});

test('visiteurs.identifier : appareil révoqué → nouvel invité ; ancien jeton nvv1 échangé ; hors ligne → conservé', async () => {
    const { etat, routes } = serveurVisiteurs();
    const api = makeApi(routes);
    await api.visiteurs.identifier('Léa');
    etat.revoques.add('nva1.v1');
    api.client.config.storage.setItem('navinum.visiteur', JSON.stringify({ ...api.visiteurs.courant(), expiresAt: dans(-1) }));
    assert.equal((await api.visiteurs.identifier('Léa')).id, 'v2', 'déconnecté partout : nouveau visiteur');

    routes['POST /api/visiteur/reprendre'] = (call) => ({ status: 200, body: { visiteur: { id: 'ancien', pseudo: call.body.pseudo }, token: 'nvv2.ancien.x', expiresAt: dans(30), appareil: 'nva1.ancien' } });
    api.client.config.storage.setItem('navinum.visiteur', JSON.stringify({ id: 'ancien', pseudo: 'Léa', token: 'nvv1.ancien.sig' }));
    const migre = await api.visiteurs.identifier('Léa');
    assert.deepEqual(fetch.calls.at(-1).body, { pseudo: 'Léa', ancienJeton: 'nvv1.ancien.sig' });
    assert.equal(migre.appareil, 'nva1.ancien');

    routes['GET /api/visiteur/moi'] = { throw: new TypeError('Failed to fetch') };
    assert.equal((await api.visiteurs.identifier('Léa')).id, 'ancien', 'hors ligne : on garde le visiteur connu');
});

test('auth : la session est prolongée par l’activité de la partie (ctx.track)', async () => {
    const { etat, routes } = serveurVisiteurs();
    const token = 'd'.repeat(48);
    routes[`GET /api/launch/${token}`] = { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } };
    routes[`POST /api/launch/${token}/visiteur`] = { status: 200, body: { visiteur: { id: 'v1' } } };
    routes[`POST /api/launch/${token}/events`] = { status: 202, body: { accepted: true } };
    const api = makeApi(routes);
    const ctx = await api.launch(token);
    await api.auth.connecter({ pseudo: 'Léa' });

    await ctx.track('quiz.started', {});
    await flush();
    assert.equal(etat.renouvellements, 0, 'jeton récent : pas de renouvellement');

    api.auth.joueurCourant.expiresAt = dans(10); // il reste 10 min
    await ctx.track('quiz.answered', {});
    await flush(); await flush();
    assert.equal(etat.renouvellements, 1);
    assert.equal(api.auth.joueur().token, 'nvv2.renouvele.1');
    assert.equal(api.visiteurs.courant().token, 'nvv2.renouvele.1', 'conservé sur l’appareil');
});

test('auth : mode borne (memoriser: false) et déconnexion côté serveur', async () => {
    const { etat, routes } = serveurVisiteurs();
    const api = makeApi(routes);
    const joueur = await api.auth.connecter({ pseudo: 'Borne', memoriser: false });
    assert.equal(joueur.pseudo, 'Borne');
    assert.equal(api.visiteurs.courant(), null, 'rien conservé sur la borne');

    await api.auth.deconnecter();
    assert.equal(etat.deconnecte, `Bearer ${joueur.token}`, 'session révoquée côté serveur');
    assert.equal(api.auth.joueur(), null);

    await api.auth.connecter({ pseudo: 'Moi' });
    api.auth.oublier();
    assert.equal(etat.deconnecte, `Bearer ${joueur.token}`, 'oublier() ne révoque pas');
    assert.equal(api.visiteurs.courant(), null);
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

// --- Module d'erreurs --------------------------------------------------------------------------

test('erreurs.decrire : un code stable par cas (réseau, 401, 404, 409, 410, 422, 500, inconnu)', async () => {
    const api = makeApi({
        'GET /api/r401': { status: 401, body: { error: 'Jeton visiteur absent ou invalide' } },
        'GET /api/r404': { status: 404, body: 'introuvable', contentType: 'text/html' },
        'GET /api/r409': { status: 409, body: { error: 'Conflit quelconque' } },
        'GET /api/r410': { status: 410, body: { error: 'Jeton de lancement expiré' } },
        'GET /api/r422': { status: 422, contentType: 'application/problem+json', body: { detail: 'nom: obligatoire', violations: [{ propertyPath: 'nom' }] } },
        'GET /api/r500': { status: 500, body: '' , contentType: 'text/plain' },
        'GET /api/reseau': { throw: new TypeError('Failed to fetch') },
    });
    const decrire = async (p) => NavinumAPI.erreurs.decrire(await api.client.get(p).catch((e) => e));

    const reseau = await decrire('/api/reseau');
    assert.equal(reseau.code, 'RESEAU');
    assert.equal(reseau.reessayable, true);
    assert.equal(reseau.action, 'REESSAYER');
    assert.match(reseau.message, /ne répond pas/);

    assert.equal((await decrire('/api/r401')).code, 'NON_IDENTIFIE');
    assert.equal((await decrire('/api/r401')).action, 'SE_RECONNECTER');
    const introuvable = await decrire('/api/r404');
    assert.equal(introuvable.code, 'INTROUVABLE');
    assert.equal(introuvable.status, 404);
    assert.equal(introuvable.message, 'introuvable', 'texte court du serveur conservé');
    assert.equal((await decrire('/api/r409')).code, 'CONFLIT');
    assert.equal((await decrire('/api/r409')).message, 'Conflit quelconque');
    const expire = await decrire('/api/r410');
    assert.equal(expire.code, 'LIEN_EXPIRE');
    assert.equal(expire.action, 'RELANCER');
    const invalide = await decrire('/api/r422');
    assert.equal(invalide.code, 'VALIDATION');
    assert.equal(invalide.status, 422);
    assert.equal(invalide.message, 'nom: obligatoire');
    assert.deepEqual(invalide.details, [{ propertyPath: 'nom' }]);
    const serveur = await decrire('/api/r500');
    assert.equal(serveur.code, 'SERVEUR');
    assert.match(serveur.message, /problème/);

    const inconnu = NavinumAPI.erreurs.decrire(new Error('boum'));
    assert.equal(inconnu.code, 'INCONNU');
    assert.equal(NavinumAPI.erreurs.decrire('texte').code, 'INCONNU');
});

test('erreurs : le code métier du serveur est prioritaire ; creer() et est()', async () => {
    const api = makeApi({
        'POST /api/session/G/join': { status: 409, body: { error: 'Une partie est en cours dans ce groupe.', code: 'PARTIE_EN_COURS' } },
        'GET /api/launch/x': { status: 404, body: { error: 'Jeton de lancement inconnu', code: 'LIEN_INVALIDE' } },
    });
    const err = await api.sessions.join('G', { playerName: 'Ana' }).catch((e) => e);
    assert.equal(err.code, 'PARTIE_EN_COURS');
    const info = NavinumAPI.erreurs.decrire(err);
    assert.equal(info.titre, 'Partie en cours');
    assert.equal(info.action, 'CHOISIR_AUTRE_GROUPE');
    assert.equal(NavinumAPI.erreurs.decrire(await api.launches.get('x').catch((e) => e)).code, 'LIEN_INVALIDE');

    const complet = NavinumAPI.erreurs.creer('GROUPE_COMPLET', 'Ce groupe est complet (8 joueurs maximum).');
    assert.ok(NavinumAPI.erreurs.est(complet, 'GROUPE_COMPLET', 'PARTIE_EN_COURS'));
    assert.equal(NavinumAPI.erreurs.decrire(complet).message, 'Ce groupe est complet (8 joueurs maximum).');
    assert.equal(NavinumAPI.erreurs.decrire(NavinumAPI.erreurs.creer('GROUPE_COMPLET')).message, NavinumAPI.erreurs.codes.GROUPE_COMPLET.message);
    assert.equal(NavinumAPI.erreurs.decrire({ code: 'PAS_UNE_ERREUR' }).code, 'INCONNU');
});

test('api.onError : appelé une fois par erreur (après les tentatives), jamais bloquant', async () => {
    const api = makeApi({ 'GET /api/x': { status: 500, body: { error: 'oups' } } }, { retryAttempts: 2, retryDelay: 1 });
    const vus = [];
    const stop = api.onError((info) => vus.push(info.code));
    api.onError(() => { throw new Error('écouteur cassé'); });
    await assert.rejects(() => api.client.get('/api/x'), NavinumAPI.ServerError);
    assert.deepEqual(vus, ['SERVEUR']);
    stop();
    await api.client.get('/api/x').catch(() => {});
    assert.deepEqual(vus, ['SERVEUR']);
});

// --- Sessions : identifiants encodés -------------------------------------------------------------

test('sessions : le nom du groupe est encodé dans l’URL (?, #, /)', async () => {
    const api = makeApi({ 'POST /api/session/Salle ?#1/join': { status: 200, body: { session: { players: [] } } } });
    await api.sessions.join('Salle ?#1', { playerName: 'Ana' });
    assert.equal(fetch.calls[0].path, '/api/session/Salle ?#1/join');
    assert.equal(fetch.calls[0].search, '');
});

// --- Module d'authentification -----------------------------------------------------------------

test('auth.connecter (invité) : jeton visiteur, rattachement au lancement, onChange, oublier', async () => {
    const token = 'b'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: { visiteur: { id: 'v1', pseudo: 'Léa', anonyme: true } } },
        'POST /api/visiteur/invite': (call) => ({ status: 201, body: { visiteur: { id: 'v1', pseudo: call.body.pseudo, anonyme: true }, token: 'nvv1.v1.sig' } }),
    });
    assert.deepEqual(api.auth.modes(), ['invite']);
    const ctx = await api.launch(token);
    const changements = [];
    api.auth.onChange((j) => changements.push(j && j.pseudo));

    const joueur = await api.auth.connecter({ pseudo: 'Léa' });
    assert.equal(joueur.token, 'nvv1.v1.sig');
    assert.equal(joueur.mode, 'invite');
    assert.equal(joueur.rattache, true);
    assert.equal(ctx.visiteur.id, 'v1', 'partie rattachée au joueur');
    assert.equal(fetch.calls.at(-1).headers.Authorization, 'Bearer nvv1.v1.sig');
    assert.equal(api.auth.joueur().pseudo, 'Léa');

    api.auth.oublier();
    assert.equal(api.auth.joueur(), null);
    assert.deepEqual(changements, ['Léa', null]);
});

test('auth : rattachement refusé non bloquant ; modes à venir et personnalisés', async () => {
    const api = makeApi({
        'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'v1', pseudo: 'Tom' }, token: 't' } },
    });
    const lancement = { identify: async () => { throw new Error('409'); } };
    const joueur = await api.auth.connecter({ pseudo: 'Tom', lancement });
    assert.equal(joueur.rattache, false);

    const rfid = await api.auth.connecter({ mode: 'rfid' }).catch((e) => e);
    assert.equal(NavinumAPI.erreurs.decrire(rfid).code, 'MODE_INDISPONIBLE');
    assert.match(rfid.message, /pas encore disponible/);

    api.auth.ajouterMode('borne', async () => ({ id: 'b', pseudo: 'Borne', anonyme: true, token: 'tb' }));
    assert.equal((await api.auth.connecter({ mode: 'borne' })).pseudo, 'Borne');
    api.auth.ajouterMode('casse', async () => ({ id: 'c', pseudo: 'C' }));
    await assert.rejects(() => api.auth.connecter({ mode: 'casse' }), NavinumAPI.ValidationError);
});

// --- Médaille demandée par l'interactif ----------------------------------------------------------

test('ctx.demanderMedaille : POST /medailles, refus décrits par le module d’erreurs', async () => {
    const token = 'c'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: { id: 'v1' }, mercure: null } },
        [`POST /api/launch/${token}/medailles`]: (call) => (call.body.medaille === 'Explorateur'
            ? { status: 200, body: { medaille: { libelle: 'Explorateur' }, nouvelle: true, totalXp: 10 } }
            : { status: 403, body: { error: 'La médaille « Or » ne peut pas être attribuée par cet interactif.', code: 'MEDAILLE_NON_ATTRIBUABLE' } }),
    });
    const ctx = await api.launch(token);
    const ok = await ctx.demanderMedaille(' Explorateur ');
    assert.equal(ok.nouvelle, true);
    assert.deepEqual(fetch.calls.at(-1).body, { medaille: 'Explorateur' });

    const refus = await ctx.demanderMedaille('Or').catch((e) => e);
    const info = NavinumAPI.erreurs.decrire(refus);
    assert.equal(info.code, 'MEDAILLE_NON_ATTRIBUABLE');
    assert.match(info.message, /Or/);
    await assert.rejects(() => ctx.demanderMedaille(''), NavinumAPI.ValidationError);
    assert.equal(NavinumAPI.erreurs.decrire(NavinumAPI.erreurs.creer('SCORE_REFUSE')).titre, 'Score refusé');
});
