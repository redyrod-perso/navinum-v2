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
    assert.deepEqual(api.auth.modes(), ['invite', 'compte', 'oauth']);
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

// --- Module gamification -----------------------------------------------------------------------

test('gamification : événements → gains notifiés et cumulés ; mode autonome sans effet', async () => {
    const token = 'e'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } },
        [`POST /api/launch/${token}/events`]: (call) => ({ status: 202, body: { accepted: true, gamification: call.body.type === 'quiz.completed'
            ? { xp: [{ typologie: 'Joueur', points: 80 }, { typologie: 'Général', points: 20 }], medailles: [{ libelle: 'Sans faute' }], totalXp: 300 }
            : { xp: [], medailles: [], totalXp: 200 } } }),
        [`POST /api/launch/${token}/medailles`]: { status: 200, body: { medaille: { libelle: 'Explorateur' }, nouvelle: true, totalXp: 300 } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: {} },
    });
    assert.equal(await api.gamification.evenement('quiz.started'), null, 'mode autonome : rien');

    await api.launch(token);
    const notifies = [];
    api.gamification.onGains((g) => notifies.push(g.medailles.map((m) => m.libelle).join(',') || g.xp.length));

    assert.deepEqual((await api.gamification.evenement('quiz.started', {})).xp, []);
    const gains = await api.gamification.evenement('quiz.completed', { score: 8 });
    assert.equal(gains.totalXp, 300);
    await api.gamification.demanderMedaille('Explorateur');
    assert.deepEqual(notifies, ['Sans faute', 'Explorateur'], 'événement sans gain : pas de notification');
    assert.deepEqual(api.gamification.bilan(), { xp: 100, medailles: [{ libelle: 'Sans faute' }, { libelle: 'Explorateur' }], totalXp: 300 });

    // Jamais bloquant : une erreur réseau rend null
    fetch.calls.length = 0;
    global.fetch = fakeFetch({ [`POST /api/launch/${token}/events`]: { throw: new TypeError('Failed to fetch') } });
    assert.equal(await api.gamification.evenement('quiz.answered', {}), null);
});

test('auth : la logique de session est dans le module (api.visiteurs ne garde que des alias)', async () => {
    const api = makeApi({ 'POST /api/visiteur/invite': (call) => ({ status: 201, body: { visiteur: { id: 'v1', pseudo: call.body.pseudo }, token: 't', expiresAt: new Date(Date.now() + 1800e3).toISOString(), appareil: 'nva1.x' } }) });
    const joueur = await api.auth.connecter({ pseudo: 'Ana' });
    assert.equal(api.auth.stockage.lire().appareil, 'nva1.x');
    assert.equal(api.visiteurs.courant().id, joueur.id, 'alias obsolète toujours fonctionnel');
    assert.equal(typeof api.visiteurs.entretenir, 'undefined', 'plus de logique de session dans la ressource');
    api.visiteurs.oublier();
    assert.equal(api.auth.joueur(), joueur, 'joueur de la page conservé ; seul le stockage est effacé');
});

test('points d’entrée par module (package.json exports) : chargeables seuls', async () => {
    const pkg = require(path.join(__dirname, '..', 'package.json'));
    for (const [nom, cible] of Object.entries(pkg.exports)) {
        if (nom.includes('*') || nom === './package.json' || typeof cible !== 'string') continue;
        const mod = await import(path.join(__dirname, '..', cible));
        assert.ok(mod.default, `${nom} exporte un défaut`);
    }
    const { erreurs } = await import(path.join(__dirname, '..', pkg.exports['./erreurs']));
    assert.equal(erreurs.decrire(new TypeError('Failed to fetch')).code, 'RESEAU');
});

// --- Module multijoueur ------------------------------------------------------------------------

/** Serveur de sessions simulé (un groupe), qui publie chaque changement sur Mercure. */
function serveurPartie(groupe) {
    const etat = { session: null, departs: [], refuserDemarrage: false };
    const publier = () => FakeEventSource.instances
        .filter((es) => !es.closed && es.topics.includes(`/navinum/sessions/${groupe}`))
        .forEach((es) => es.emit({ type: 'session', session: JSON.parse(JSON.stringify(etat.session)) }));
    const p = `/api/session/${groupe}`;
    const routes = {
        'GET /api/rfid_groupes': { status: 200, contentType: 'application/ld+json', body: { member: [] } },
        'POST /api/rfid_groupes': (call) => ({ status: 201, contentType: 'application/ld+json', body: { id: 'g', nom: call.body.nom } }),
        'POST /api/session/create': (call) => {
            etat.session = { id: groupe, status: 'lobby', theme: null, maxPlayers: 8, players: [{ name: call.body.playerName, score: 0, isLeader: true }] };
            publier();
            return { status: 200, body: { session: etat.session } };
        },
        [`POST ${p}/join`]: (call) => {
            if (!etat.session) return { status: 404, body: { error: 'Session non trouvée', code: 'SESSION_INTROUVABLE' } };
            etat.session.players.push({ name: call.body.playerName, score: 0, isLeader: false });
            publier();
            return { status: 200, body: { session: etat.session } };
        },
        [`GET ${p}`]: () => (etat.session ? { status: 200, body: { session: etat.session } } : { status: 404, body: {} }),
        [`POST ${p}/start`]: (call) => {
            if (etat.refuserDemarrage) return { status: 403, body: { error: 'Seul le leader du groupe peut démarrer le quiz', code: 'NON_LEADER' } };
            Object.assign(etat.session, { status: 'playing', theme: call.body.theme });
            publier();
            return { status: 200, body: { status: 'ok' } };
        },
        [`POST ${p}/score`]: (call) => {
            etat.session.players.find((j) => j.name === call.body.playerName).score = call.body.score;
            publier();
            return { status: 200, body: { status: 'ok' } };
        },
        [`POST ${p}/leave`]: (call) => { etat.departs.push(call.body.playerName); return { status: 200, body: { status: 'ok' } }; },
    };
    return { etat, routes };
}

test('multijoueur : lobby, démarrage synchronisé, premier à l’objectif gagne pour tout le groupe', async () => {
    const { etat, routes } = serveurPartie('Salle');
    const apiA = makeApi(routes);
    const apiB = new NavinumAPI({ baseURL: 'http://navinum.test', retryAttempts: 1, storage: null });
    const objectif = (theme) => (theme === 'histoire' ? 3 : 10);
    const alice = apiA.multijoueur.partie({ objectif });
    const bob = apiB.multijoueur.partie({ objectif });
    const etapesAlice = [];
    alice.onChange((e, avant) => { if (e.etape !== avant.etape) etapesAlice.push(e.etape); });

    await alice.rejoindre(' Salle ', 'Alice');
    await bob.rejoindre('Salle', 'Bob');
    assert.equal(fetch.calls.find((c) => c.method === 'POST' && c.path === '/api/rfid_groupes').body.nom, 'Salle', 'groupe créé');
    assert.deepEqual(alice.etat().joueurs.map((j) => `${j.nom}${j.leader ? '*' : ''}`), ['Alice*', 'Bob'], 'Alice voit Bob arriver');
    assert.equal(alice.etat().leader, true);
    assert.equal(bob.etat().leader, false);
    assert.equal(alice.etat().joueursMax, 8);

    await assert.rejects(() => bob.demarrer('histoire'), (e) => NavinumAPI.erreurs.est(e, 'NON_LEADER'));
    await alice.demarrer('histoire');
    assert.equal(bob.etat().etape, 'en_cours', 'Bob démarre avec Alice');
    assert.equal(bob.etat().theme, 'histoire');
    assert.equal(bob.etat().objectif, 3);

    await alice.marquerPoint();
    for (let i = 0; i < 3; i++) await bob.marquerPoint();
    assert.equal(bob.etat().victoire, true);
    assert.equal(alice.etat().etape, 'terminee', 'la partie se termine pour Alice');
    assert.equal(alice.etat().gagnant, 'Bob');
    assert.equal(alice.etat().victoire, false);
    assert.deepEqual(alice.etat().joueurs.map((j) => j.score), [1, 3]);
    assert.deepEqual(etapesAlice, ['lobby', 'en_cours', 'terminee']);

    await alice.quitter();
    assert.equal(alice.etat().etape, 'hors');
    assert.deepEqual(etat.departs, ['Alice']);
    assert.ok(FakeEventSource.instances.filter((es) => es.closed).length >= 1, 'abonnement fermé');
});

test('multijoueur : démarrage refusé → retour au lobby ; notre score jamais écrasé par un état plus ancien', async () => {
    const { etat, routes } = serveurPartie('Duo');
    const api = makeApi(routes);
    const partie = api.multijoueur.partie({ objectif: 5 });
    await partie.rejoindre('Duo', 'Léa');

    etat.refuserDemarrage = true;
    await assert.rejects(() => partie.demarrer('sciences'), (e) => NavinumAPI.erreurs.est(e, 'NON_LEADER'));
    assert.equal(partie.etat().etape, 'lobby');

    etat.refuserDemarrage = false;
    await partie.demarrer('sciences');
    await partie.marquerPoint();
    await partie.marquerPoint();
    // État relu, antérieur au dernier point
    partie.recevoir({ status: 'playing', players: [{ name: 'Léa', score: 1, isLeader: true }] });
    assert.equal(partie.etat().joueurs[0].score, 2);

    partie.terminer();
    assert.equal(partie.etat().etape, 'terminee');
    assert.equal(partie.etat().gagnant, null);
    await assert.rejects(() => partie.rejoindre('', 'Léa'), (e) => NavinumAPI.erreurs.est(e, 'VALIDATION'));
});

test('react : usePartie suit les changements d’état (React simulé)', async () => {
    const { routes } = serveurPartie('R');
    const api = makeApi(routes);
    const partie = api.multijoueur.partie();
    let valeur;
    const nettoyages = [];
    const ReactSimule = {
        useState: (init) => { valeur = typeof init === 'function' ? init() : init; return [valeur, (v) => { valeur = v; }]; },
        useEffect: (fn) => { const n = fn(); if (n) nettoyages.push(n); },
    };
    const { usePartie } = NavinumAPI.creerHooksReact(ReactSimule);
    usePartie(partie);
    assert.equal(valeur.etape, 'hors');
    await partie.rejoindre('R', 'Zoé');
    assert.equal(valeur.etape, 'lobby');
    nettoyages.forEach((n) => n());
    await partie.quitter();
    assert.equal(valeur.etape, 'lobby', 'désabonné après nettoyage');
});

// Bug : seuls les 30 premiers groupes étaient listés (pagination API Platform) ; un groupe récent
// n'apparaissait pas dans la liste du quiz, et findByNom pouvait le manquer (doublon refusé).
test('rfidGroupes.list : toutes les pages sont lues', async () => {
    const page = (n) => Array.from({ length: 30 }, (_, i) => ({ id: `${n}-${i}`, nom: `G${n}-${i}` }));
    const api = makeApi({
        'GET /api/rfid_groupes': (call) => (call.search.includes('page=2')
            ? { status: 200, contentType: 'application/ld+json', body: { member: [{ id: 'x', nom: 'Classe 4B' }], view: {} } }
            : { status: 200, contentType: 'application/ld+json', body: { member: page(1), view: { next: '/api/rfid_groupes?nom=Classe&page=2' } } }),
    });
    const groupes = await api.rfidGroupes.list({ nom: 'Classe' });
    assert.equal(groupes.length, 31);
    assert.equal(fetch.calls[1].search, '?nom=Classe&page=2', 'filtre conservé sur les pages suivantes');
    assert.equal((await api.rfidGroupes.findByNom('Classe 4B')).id, 'x');
});

// Bug : un lien réutilisé par un autre joueur encore actif créditait le joueur précédent.
test('gains : les événements portent le jeton du joueur ; lancement rattaché à un autre → rien de crédité', async () => {
    const token = 'f'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: { id: 'autre' }, mercure: null } },
        [`POST /api/launch/${token}/visiteur`]: { status: 409, body: { error: 'Ce lancement est déjà rattaché à un autre visiteur', code: 'DEJA_RATTACHE' } },
        [`POST /api/launch/${token}/events`]: (call) => (call.headers.Authorization === 'Bearer nvv2.bob'
            ? { status: 202, body: { accepted: true, gamification: null, rattache: false } }
            : { status: 202, body: { accepted: true, gamification: { xp: [{ points: 5 }], medailles: [], totalXp: 5 }, rattache: true } }),
        'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'bob', pseudo: 'Bob' }, token: 'nvv2.bob', expiresAt: new Date(Date.now() + 1800e3).toISOString(), appareil: 'nva1.bob' } },
    });
    await api.launch(token);
    assert.ok(await api.gamification.evenement('quiz.started', {}), 'sans joueur connecté : comportement inchangé');
    assert.equal(fetch.calls.at(-1).headers.Authorization, undefined);

    const bob = await api.auth.connecter({ pseudo: 'Bob' });
    assert.equal(bob.rattache, false);
    assert.equal(bob.motif, 'DEJA_RATTACHE', 'l’interactif peut prévenir le joueur');

    assert.equal(await api.gamification.evenement('quiz.completed', { score: 3 }), null, 'gains non comptés');
    assert.equal(fetch.calls.at(-1).headers.Authorization, 'Bearer nvv2.bob');
    assert.equal(api.gamification.bilan().xp, 5, 'seuls les gains réels sont cumulés');
});

test('multijoueur.groupes : logique de nom dans le module ; api.rfidGroupes ne fait que les appels HTTP', async () => {
    const api = makeApi({
        'GET /api/rfid_groupes': { status: 200, contentType: 'application/ld+json', body: { member: [{ id: '1', nom: 'Classe 4B bis' }, { id: '2', nom: 'Classe 4B' }] } },
        'POST /api/rfid_groupes': (call) => ({ status: 201, contentType: 'application/ld+json', body: { id: '3', nom: call.body.nom } }),
    });
    const { groupes } = api.multijoueur;
    assert.equal((await groupes.trouver(' Classe 4B ')).id, '2', 'nom exact, espaces ignorés');
    assert.equal((await groupes.trouverOuCreer('Classe 4B')).id, '2', 'existant : pas de création');
    assert.deepEqual(await groupes.trouverOuCreer(' Duo '), { id: '3', nom: 'Duo' });
    assert.equal(fetch.calls.filter((c) => c.method === 'POST').length, 1);
    await assert.rejects(() => groupes.trouver('  '), NavinumAPI.ValidationError);
    assert.equal((await groupes.lister()).length, 2);

    assert.equal((await api.rfidGroupes.findOrCreate('Classe 4B')).id, '2', 'alias obsolète toujours fonctionnel');
});

// --- #3 : modes de connexion branchables ---------------------------------------------------------

test('auth : un mode branché fournit connecter, entretenir et deconnecter (contrat des futurs modes)', async () => {
    const token = '9'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: { visiteur: { id: 'b' } } },
        [`POST /api/launch/${token}/events`]: { status: 202, body: { accepted: true, gamification: null, rattache: true } },
    });
    const appels = [];
    api.auth.ajouterMode('rfid', {
        connecter: async ({ badge }) => { appels.push(`connecter:${badge}`); return { id: 'b', pseudo: 'Badge 42', anonyme: false, token: 'nvv2.badge' }; },
        entretenir: async (joueur, { memoriser }) => { appels.push(`entretenir:${memoriser}`); return { ...joueur, token: 'nvv2.badge2' }; },
        deconnecter: async (joueur) => { appels.push(`deconnecter:${joueur.token}`); },
    });
    assert.deepEqual(api.auth.modes(), ['invite', 'compte', 'oauth', 'rfid']);

    const ctx = await api.launch(token);
    const joueur = await api.auth.connecter({ mode: 'rfid', badge: '042' });
    assert.equal(joueur.mode, 'rfid');
    assert.equal(joueur.rattache, true, 'même jeton visiteur : partie rattachée comme pour un invité');

    await ctx.track('jeu.etape', {});
    await flush();
    assert.equal(api.auth.joueur().token, 'nvv2.badge2', 'prolongée par le mode rfid, pas par le mode invité');
    await api.auth.deconnecter();
    assert.deepEqual(appels, ['connecter:042', 'entretenir:true', 'deconnecter:nvv2.badge2']);

    // Rétrocompatible : une simple fonction reste un mode valide (sans prolongation)
    api.auth.ajouterMode('simple', async () => ({ id: 's', pseudo: 'S', token: 't' }));
    assert.equal((await api.auth.connecter({ mode: 'simple', lancement: null })).pseudo, 'S');
    assert.equal((await api.auth.entretenir()).token, 't');
    assert.throws(() => api.auth.ajouterMode('casse', {}), NavinumAPI.ValidationError);
});

// Bug : une vérification locale de la librairie (avant tout appel) était décrite par le message générique
// du catalogue (« Certaines informations sont invalides ») au lieu de son message précis.
test('erreurs : le message d’une erreur levée par la librairie elle-même est conservé', async () => {
    const api = makeApi({});
    const err = await api.auth.connecter({ pseudo: 'A' }).catch((e) => e);
    const info = NavinumAPI.erreurs.decrire(err);
    assert.equal(info.code, 'VALIDATION');
    assert.equal(info.message, 'Le pseudo doit faire entre 2 et 40 caractères');
    assert.match(NavinumAPI.erreurs.decrire(new NavinumAPI.NetworkError('Timeout après 5000ms')).message, /ne répond pas/, 'réseau : message du catalogue');
});

// --- #4 : comptes (e-mail + mot de passe) ---------------------------------------------------------

test('auth : créer un compte depuis un invité (même visiteur), se connecter, reprendre la session du compte', async () => {
    const dans30 = () => new Date(Date.now() + 1800e3).toISOString();
    const api = makeApi({
        'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'v1', pseudo: 'Tom', anonyme: true }, token: 'nvv2.invite', expiresAt: dans30(), appareil: 'nva1.tom' } },
        'POST /api/visiteur/inscription': (call) => ({ status: 201, body: { visiteur: { id: call.headers.Authorization ? 'v1' : 'v9', pseudo: 'Tom', anonyme: false }, token: 'nvv2.compte', expiresAt: dans30() } }),
        'POST /api/visiteur/connexion': (call) => (call.body.motDePasse === 'secret-123'
            ? { status: 200, body: { visiteur: { id: 'v1', pseudo: 'Tom', anonyme: false }, token: 'nvv2.connexion', expiresAt: dans30(), appareil: 'nva1.autre' } }
            : { status: 401, body: { error: 'Adresse e-mail ou mot de passe incorrect.', code: 'IDENTIFIANTS_INVALIDES' } }),
        'POST /api/visiteur/reprendre': (call) => ({ status: 200, body: { visiteur: { id: 'v1', pseudo: call.body.pseudo, anonyme: false }, token: 'nvv2.repris', expiresAt: dans30() } }),
    });
    assert.deepEqual(api.auth.modes(), ['invite', 'compte', 'oauth']);

    await api.auth.connecter({ pseudo: 'Tom' });
    const compte = await api.auth.creerCompte({ email: 'tom@exemple.test', motDePasse: 'secret-123', newsletter: true });
    const inscription = fetch.calls.find((c) => c.path === '/api/visiteur/inscription');
    assert.equal(inscription.headers.Authorization, 'Bearer nvv2.invite', 'l’invité est transmis : il devient le compte');
    assert.deepEqual(inscription.body, { email: 'tom@exemple.test', motDePasse: 'secret-123', newsletter: true });
    assert.equal(compte.id, 'v1');
    assert.equal(compte.mode, 'compte');
    assert.equal(compte.appareil, 'nva1.tom', 'même appareil conservé');

    const refus = await api.auth.connecter({ mode: 'compte', email: 'tom@exemple.test', motDePasse: 'faux' }).catch((e) => e);
    assert.equal(NavinumAPI.erreurs.decrire(refus).code, 'IDENTIFIANTS_INVALIDES');
    assert.equal((await api.auth.connecter({ mode: 'compte', email: 'tom@exemple.test', motDePasse: 'secret-123' })).token, 'nvv2.connexion');

    // Plus tard, sans mot de passe : session du compte reprise sur l'appareil
    api.client.config.storage.setItem('navinum.visiteur', JSON.stringify({ ...api.auth.joueur(), expiresAt: new Date(Date.now() - 60e3).toISOString() }));
    api.auth.joueurCourant = null;
    const repris = await api.auth.connecter({ mode: 'compte' });
    assert.equal(repris.token, 'nvv2.repris');
    assert.equal(api.auth.joueur().mode, 'compte');

    api.auth.oublier();
    const sansCompte = await api.auth.connecter({ mode: 'compte' }).catch((e) => e);
    assert.equal(NavinumAPI.erreurs.decrire(sansCompte).code, 'NON_IDENTIFIE');
    assert.equal(NavinumAPI.erreurs.decrire(new NavinumAPI.NavinumAPIError('x', 429)).code, 'TROP_DE_TENTATIVES');
});

// --- #71 : e-mails de compte -------------------------------------------------------------------

test('auth : mot de passe oublié, réinitialisation, renvoi de la vérification', async () => {
    const dans30 = () => new Date(Date.now() + 1800e3).toISOString();
    const api = makeApi({
        'POST /api/visiteur/mot-de-passe/oublie': { status: 202, body: { message: 'Si un compte existe avec cette adresse, un e-mail vient de lui être envoyé.' } },
        'POST /api/visiteur/mot-de-passe/reinitialiser': (call) => (call.body.jeton === 'bon'
            ? { status: 200, body: { message: 'ok' } }
            : { status: 400, body: { error: 'Ce lien n’est plus valable', code: 'LIEN_COMPTE_INVALIDE' } }),
        'POST /api/visiteur/connexion': { status: 200, body: { visiteur: { id: 'v1', pseudo: 'Tom', anonyme: false }, token: 'nvv2.compte', expiresAt: dans30(), appareil: 'nva1.x' } },
        'POST /api/visiteur/email/renvoyer': { status: 202, body: { emailVerifie: false, envoye: true } },
    });

    const { message } = await api.auth.motDePasseOublie('  tom@exemple.test ');
    assert.match(message, /Si un compte existe/);
    assert.deepEqual(fetch.calls.find((c) => c.path === '/api/visiteur/mot-de-passe/oublie').body, { email: 'tom@exemple.test' });

    assert.equal((await api.auth.reinitialiserMotDePasse('bon', 'nouveau-456')).message, 'ok');
    const refus = await api.auth.reinitialiserMotDePasse('vieux', 'nouveau-456').catch((e) => e);
    const info = NavinumAPI.erreurs.decrire(refus);
    assert.equal(info.code, 'LIEN_COMPTE_INVALIDE');
    assert.equal(info.titre, 'Lien plus valable');

    const sansCompte = await api.auth.renvoyerVerification().catch((e) => e);
    assert.equal(NavinumAPI.erreurs.decrire(sansCompte).code, 'NON_IDENTIFIE');
    await api.auth.connecter({ mode: 'compte', email: 'tom@exemple.test', motDePasse: 'secret-123' });
    assert.deepEqual(await api.auth.renvoyerVerification(), { emailVerifie: false, envoye: true });
    assert.equal(fetch.calls.find((c) => c.path === '/api/visiteur/email/renvoyer').headers.Authorization, 'Bearer nvv2.compte');
});

// --- #4 : OAuth multi-fournisseurs ----------------------------------------------------------------

test('auth oauth : départ vers le fournisseur (joueur connecté transmis), retour, échange, rattachement', async () => {
    const token = '7'.repeat(48);
    const lieu = { href: 'http://navinum.test/jeu/index.html?nv=' + token, hash: '', assign(url) { this.parti = url; } };
    const api = makeApi({
        'GET /api/visiteur/oauth/fournisseurs': { status: 200, body: { fournisseurs: [{ nom: 'google', libelle: 'Google' }, { nom: 'microsoft', libelle: 'Microsoft' }] } },
        'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'v1', pseudo: 'Tom', anonyme: true }, token: 'nvv2.invite', expiresAt: new Date(Date.now() + 1800e3).toISOString(), appareil: 'nva1.tom' } },
        'POST /api/visiteur/oauth/google/demarrer': { status: 200, body: { url: 'https://accounts.google.com/o/oauth2/v2/auth?state=x' } },
        'POST /api/visiteur/oauth/echange': (call) => (call.body.code === 'abc'
            ? { status: 200, body: { visiteur: { id: 'v1', pseudo: 'Tom', anonyme: false }, token: 'nvv2.oauth', expiresAt: new Date(Date.now() + 1800e3).toISOString(), appareil: 'nva1.g', fournisseur: 'google', memoriser: true } }
            : { status: 401, body: { error: 'Connexion expirée : recommencez.', code: 'NON_IDENTIFIE' } }),
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: { visiteur: { id: 'v1' } } },
    });
    api.auth.modeOAuth.lieu = () => lieu;
    assert.deepEqual((await api.auth.fournisseursOAuth()).map((f) => f.nom), ['google', 'microsoft']);
    assert.deepEqual(api.auth.modes(), ['invite', 'compte', 'oauth']);

    // Départ : invité connecté transmis (il deviendra le compte), retour sur la même page
    await api.auth.connecter({ pseudo: 'Tom' });
    api.auth.connecter({ mode: 'oauth', fournisseur: 'google' });
    await flush(); await flush();
    const depart = fetch.calls.find((c) => c.path === '/api/visiteur/oauth/google/demarrer');
    assert.deepEqual(depart.body, { retour: lieu.href, memoriser: true });
    assert.equal(depart.headers.Authorization, 'Bearer nvv2.invite');
    assert.equal(lieu.parti, 'https://accounts.google.com/o/oauth2/v2/auth?state=x');

    // Retour du fournisseur sur la page (nouveau chargement)
    assert.equal(await api.auth.retourConnexion(), null, 'pas de fragment : rien à faire');
    lieu.hash = '#navinum-oauth=abc';
    await api.launch(token);
    const joueur = await api.auth.retourConnexion();
    assert.equal(joueur.mode, 'oauth');
    assert.equal(joueur.fournisseur, 'google');
    assert.equal(joueur.rattache, true, 'partie lancée rattachée');
    assert.equal(api.visiteurs.courant().appareil, 'nva1.g');

    lieu.hash = '#navinum-oauth-erreur=CONNEXION_ANNULEE';
    const annule = await api.auth.retourConnexion().catch((e) => e);
    assert.equal(NavinumAPI.erreurs.decrire(annule).code, 'CONNEXION_ANNULEE');
    lieu.hash = '#navinum-oauth=perime';
    assert.equal(NavinumAPI.erreurs.decrire(await api.auth.retourConnexion().catch((e) => e)).code, 'NON_IDENTIFIE');
});

test('gamification.medailles : catalogue de l’interactif, obtenues pour le joueur connecté ; null en mode autonome', async () => {
    const token = '5'.repeat(48);
    const api = makeApi({
        [`GET /api/launch/${token}`]: { status: 200, body: { interactif: { id: 'i' }, config: {}, visiteur: null, mercure: null } },
        [`POST /api/launch/${token}/visiteur`]: { status: 200, body: { visiteur: { id: 'v1' } } },
        [`GET /api/launch/${token}/medailles`]: (call) => ({ status: 200, body: { medailles: [
            { libelle: 'Explorateur', attribuable: true, obtenue: call.headers.Authorization ? false : null },
            { libelle: 'Sans faute', attribuable: false, obtenue: call.headers.Authorization ? true : null },
        ] } }),
        'POST /api/visiteur/invite': { status: 201, body: { visiteur: { id: 'v1', pseudo: 'Léa', anonyme: true }, token: 'nvv2.lea', expiresAt: new Date(Date.now() + 1800e3).toISOString(), appareil: 'nva1.lea' } },
    });
    assert.equal(await api.gamification.medailles(), null, 'mode autonome');

    await api.launch(token);
    assert.deepEqual((await api.gamification.medailles()).map((m) => m.obtenue), [null, null], 'sans joueur : inconnu');
    await api.auth.connecter({ pseudo: 'Léa' });
    const medailles = await api.gamification.medailles();
    assert.equal(fetch.calls.at(-1).headers.Authorization, 'Bearer nvv2.lea');
    assert.deepEqual(medailles.filter((m) => !m.obtenue).map((m) => m.libelle), ['Explorateur']);
});
