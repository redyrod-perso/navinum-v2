import { ValidationError } from '../../core/Errors.js';
import { NotFoundError } from '../../core/Errors.js';
import { erreurs } from '../erreurs/Erreurs.js';
import { Groupes } from './Groupes.js';

/**
 * Joueur d'une partie.
 * @typedef {Object} JoueurPartie
 * @property {string} nom - Nom du joueur (identifiant dans le groupe)
 * @property {number} score - Score
 * @property {boolean} leader - Leader du groupe (choisit le thème, démarre)
 * @property {boolean} moi - C'est le joueur de cette page
 */

/**
 * État d'une partie multijoueur, transmis à chaque changement.
 * @typedef {Object} EtatPartie
 * @property {('hors'|'lobby'|'en_cours'|'terminee')} etape
 * @property {string|null} groupe - Nom du groupe (identifiant de session)
 * @property {string|null} moi - Nom du joueur de cette page
 * @property {JoueurPartie[]} joueurs - Joueurs (après la fin : figés, y compris ceux partis)
 * @property {boolean} leader - Le joueur de cette page est le leader
 * @property {number|null} joueursMax - Joueurs maximum imposés par le serveur
 * @property {string|null} theme - Thème choisi au démarrage
 * @property {number|null} objectif - Score qui fait gagner (ex. nombre de questions)
 * @property {number} score - Score du joueur de cette page
 * @property {string|null} gagnant - Nom du gagnant, ou null
 * @property {boolean} victoire - Le joueur de cette page a gagné
 * @property {number|null} debut - Démarrage (horodatage ms), pour calculer une durée
 */

const ETAT_INITIAL = Object.freeze({
    etape: 'hors', groupe: null, moi: null, joueurs: [], leader: false, joueursMax: null,
    theme: null, objectif: null, score: 0, gagnant: null, victoire: false, debut: null,
});

/**
 * Partie multijoueur dans un groupe, sans affichage : rejoindre ou créer le groupe, lobby et leader,
 * démarrage synchronisé, scores en direct, gagnant, départs (y compris à la fermeture de la page).
 *
 * Règles (celles du quiz, réutilisables par tout jeu « le premier qui atteint l'objectif gagne ») :
 * - le premier joueur du groupe est leader ; s'il part, le suivant le devient (serveur) ;
 * - le leader démarre avec un thème ; tous les joueurs passent à `en_cours` en même temps (Mercure) ;
 * - chaque bonne réponse : `marquerPoint()` (+1, contrôlé par le serveur) ;
 * - le premier à atteindre `objectif` gagne : la partie se termine pour tout le groupe ;
 * - `terminer()` quand le joueur a fini sans atteindre l'objectif (toutes les questions posées).
 *
 * @class
 * @example
 * const partie = api.multijoueur.partie({ objectif: theme => questionsDuTheme(theme).length });
 * partie.onChange((etat, avant) => {
 *     if (avant.etape === 'lobby' && etat.etape === 'en_cours') afficherQuestions(etat.theme);
 *     if (etat.etape === 'terminee') afficherPodium(etat.joueurs, etat.gagnant);
 * });
 * await partie.rejoindre('Classe 4B', 'Léa');   // lobby
 * if (partie.etat().leader) await partie.demarrer('histoire');
 * await partie.marquerPoint();                  // bonne réponse
 * await partie.quitter();
 */
export class Partie {
    /**
     * @param {NavinumAPI} api
     * @param {Object} [options={}]
     * @param {number|function(string): number} [options.objectif] - Score gagnant, ou fonction du thème
     */
    constructor(api, options = {}) {
        this.api = api;
        this.options = options;
        this.ecouteurs = [];
        this.courant = { ...ETAT_INITIAL };
        this.arrets = [];
    }

    /** État courant (copie). @returns {EtatPartie} */
    etat() {
        return { ...this.courant, joueurs: this.courant.joueurs.map(j => ({ ...j })) };
    }

    /**
     * Suit les changements d'état.
     * @param {function(EtatPartie, EtatPartie): void} callback - Reçoit le nouvel état et le précédent
     * @returns {function(): void} Fonction de désabonnement
     */
    onChange(callback) {
        this.ecouteurs.push(callback);
        return () => { this.ecouteurs = this.ecouteurs.filter(c => c !== callback); };
    }

    /**
     * Rejoint le groupe (créé s'il n'existe pas) et entre dans le lobby. Le jeton de lancement éventuel
     * (`api.launch()`) est transmis : le serveur impose le nombre maximum de joueurs du parcours.
     *
     * @param {string} groupe - Nom du groupe
     * @param {string} nom - Nom du joueur
     * @returns {Promise<EtatPartie>}
     * @throws {NavinumAPIError} `VALIDATION`, `GROUPE_COMPLET`, `PARTIE_EN_COURS`, `RESEAU`… (module d'erreurs)
     */
    async rejoindre(groupe, nom) {
        const g = typeof groupe === 'string' ? groupe.trim() : '';
        const n = typeof nom === 'string' ? nom.trim() : '';
        if (!n) throw erreurs.creer('VALIDATION', 'Veuillez entrer votre nom.');
        if (!g) throw erreurs.creer('VALIDATION', 'Veuillez choisir ou créer un groupe.');
        if (this.courant.etape !== 'hors') await this.quitter();

        await this.api.multijoueur.groupes.trouverOuCreer(g);
        const launchToken = this.api.contexte ? this.api.contexte.token : undefined;
        let data;
        try {
            data = await this.api.sessions.join(g, { playerName: n, launchToken });
        } catch (error) {
            if (!(error instanceof NotFoundError)) throw error;
            data = await this.api.sessions.create({ playerName: n, sessionId: g, rfidGroupeName: g, launchToken });
        }
        if (!data || !data.session || !Array.isArray(data.session.players)) {
            throw erreurs.creer('SERVEUR', 'Réponse inattendue du serveur.');
        }

        this.changer({ ...ETAT_INITIAL, etape: 'lobby', groupe: g, moi: n, ...this.depuisSession(data.session, n) });
        this.arrets.push(this.api.sessions.subscribe(g, session => this.recevoir(session), {
            onError: error => this.api.config.log('Relecture de la session impossible', error),
        }));
        this.arrets.push(this.api.sessions.quitterEnPartant(g, { playerName: n }));
        return this.etat();
    }

    /**
     * Démarre la partie (leader seulement). Le leader passe tout de suite à `en_cours` ; si le serveur
     * refuse, il revient au lobby et l'erreur est levée.
     *
     * @param {string} theme - Thème choisi
     * @returns {Promise<EtatPartie>}
     * @throws {NavinumAPIError} `NON_LEADER`, `VALIDATION`…
     */
    async demarrer(theme) {
        if (this.courant.etape !== 'lobby') throw erreurs.creer('PARTIE_DEMARREE');
        if (!theme) throw new ValidationError('Veuillez sélectionner un thème.', { theme: 'requis' });
        if (!this.courant.leader) throw erreurs.creer('NON_LEADER');
        this.lancer(theme);
        try {
            await this.api.sessions.start(this.courant.groupe, { theme, playerName: this.courant.moi });
        } catch (error) {
            if (!erreurs.est(error, 'PARTIE_DEMARREE')) {
                this.changer({ ...this.courant, etape: 'lobby', theme: null, objectif: null, debut: null });
                throw error;
            }
        }
        return this.etat();
    }

    /**
     * Bonne réponse : +1 point (envoyé au serveur). Atteindre l'objectif fait gagner la partie.
     * @returns {Promise<EtatPartie>}
     */
    async marquerPoint() {
        if (this.courant.etape !== 'en_cours') return this.etat();
        const score = this.courant.score + 1;
        const joueurs = this.courant.joueurs.map(j => (j.moi ? { ...j, score } : j));
        const gagne = this.courant.objectif !== null && score >= this.courant.objectif;
        this.changer({
            ...this.courant, score, joueurs,
            ...(gagne ? { etape: 'terminee', gagnant: this.courant.moi, victoire: true } : {}),
        });
        this.api.sessions.updateScore(this.courant.groupe, { playerName: this.courant.moi, score })
            .catch(error => this.api.config.log('Score non enregistré', error));
        return this.etat();
    }

    /** Le joueur a fini (toutes les questions posées) sans atteindre l'objectif. */
    terminer() {
        if (this.courant.etape === 'en_cours') this.changer({ ...this.courant, etape: 'terminee' });
        return this.etat();
    }

    /**
     * Quitte le groupe (abonnements arrêtés, départ envoyé au serveur). Jamais bloquant.
     * @returns {Promise<void>}
     */
    async quitter() {
        const { groupe, moi, etape } = this.courant;
        this.arrets.splice(0).forEach(arret => { try { arret(); } catch (e) { /* déjà arrêté */ } });
        this.changer({ ...ETAT_INITIAL });
        if (etape !== 'hors' && groupe && moi) {
            try {
                await this.api.sessions.leave(groupe, { playerName: moi });
            } catch (error) {
                this.api.config.log('Départ non enregistré', error);
            }
        }
    }

    /** @private */
    lancer(theme) {
        const objectif = typeof this.options.objectif === 'function' ? this.options.objectif(theme) : (this.options.objectif ?? null);
        this.changer({
            ...this.courant, etape: 'en_cours', theme, objectif: Number.isFinite(objectif) ? objectif : null,
            score: 0, joueurs: this.courant.joueurs.map(j => ({ ...j, score: 0 })), gagnant: null, victoire: false,
            debut: Date.now(),
        });
    }

    /**
     * État reçu du serveur (Mercure, ou relecture à la connexion).
     * @private
     */
    recevoir(session) {
        const c = this.courant;
        if (c.etape === 'hors') return;
        if (c.etape === 'lobby') {
            this.changer({ ...c, ...this.depuisSession(session, c.moi) });
            if (session.status === 'playing' && session.theme) this.lancer(session.theme);
            return;
        }

        // En cours ou terminée : scores fusionnés ; notre score ne descend jamais sous le score local
        // (l'état relu peut précéder l'enregistrement de notre dernier point) ; après la fin, les joueurs
        // partis restent affichés.
        const recus = new Map((session.players || []).map(p => [p.name, p]));
        const joueurs = c.joueurs.map(j => {
            const p = recus.get(j.nom);
            if (!p) return j;
            return { ...j, score: j.moi ? Math.max(c.score, p.score || 0) : (p.score || 0), leader: Boolean(p.isLeader) };
        });
        if (c.etape === 'en_cours') {
            (session.players || []).filter(p => !c.joueurs.some(j => j.nom === p.name))
                .forEach(p => joueurs.push({ nom: p.name, score: p.score || 0, leader: Boolean(p.isLeader), moi: false }));
        }
        const premier = c.objectif !== null ? joueurs.find(j => j.score >= c.objectif) : null;
        if (c.etape === 'en_cours' && premier && !premier.moi) {
            // Un autre joueur a atteint l'objectif : la partie se termine pour tout le groupe
            this.changer({ ...c, joueurs, etape: 'terminee', gagnant: premier.nom, victoire: false });
        } else if (c.etape === 'terminee' && !c.gagnant && premier) {
            this.changer({ ...c, joueurs, gagnant: premier.nom, victoire: premier.moi });
        } else {
            this.changer({ ...c, joueurs });
        }
    }

    /** @private */
    depuisSession(session, moi) {
        const joueurs = (session.players || []).map(p => ({ nom: p.name, score: p.score || 0, leader: Boolean(p.isLeader), moi: p.name === moi }));
        return {
            joueurs,
            leader: joueurs.some(j => j.moi && j.leader),
            joueursMax: Number.isInteger(session.maxPlayers) ? session.maxPlayers : this.courant.joueursMax,
        };
    }

    /** @private */
    changer(suivant) {
        const avant = this.etat();
        this.courant = suivant;
        const apres = this.etat();
        for (const ecouteur of this.ecouteurs) {
            try { ecouteur(apres, avant); } catch (e) { /* un écouteur ne bloque pas la partie */ }
        }
    }
}

/**
 * Module multijoueur : crée des parties et donne accès aux groupes.
 * @class
 */
export class Multijoueur {
    /** @param {NavinumAPI} api */
    constructor(api) {
        this.api = api;
        /** Groupes : lister, suivre en direct, retrouver ou créer par nom (voir {@link Groupes}). */
        this.groupes = new Groupes(api.rfidGroupes);
    }

    /**
     * Nouvelle partie (une par joueur et par page).
     * @param {Object} [options] - Voir {@link Partie}
     * @returns {Partie}
     */
    partie(options = {}) {
        return new Partie(this.api, options);
    }
}

export default Multijoueur;
