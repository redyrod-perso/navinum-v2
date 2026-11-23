import { ValidationError } from '../../core/Errors.js';
import { erreurs } from '../erreurs/Erreurs.js';
import { ModeInvite } from './ModeInvite.js';
import { Stockage } from './Stockage.js';

/**
 * Joueur connecté : profil public et jeton visiteur.
 * @typedef {Object} Joueur
 * @property {string} id - UUID du visiteur
 * @property {string} pseudo - Pseudo affiché
 * @property {boolean} anonyme - true pour un visiteur invité (sans compte)
 * @property {string} token - Jeton visiteur Bearer (`nvv2.…`), valable 30 min, prolongé par l'activité (12 h au plus)
 * @property {string} expiresAt - Expiration du jeton (ISO-8601)
 * @property {string} mode - Mode de connexion utilisé (`invite`, …)
 */

/**
 * Fournisseur d'un mode de connexion : reçoit les options de `connecter()` et la librairie,
 * et rend un {@link Joueur} (au minimum `id`, `pseudo`, `token`).
 * @callback FournisseurAuth
 * @param {Object} options - Options passées à {@link Auth#connecter}
 * @param {NavinumAPI} api - Instance de la librairie
 * @returns {Promise<Joueur>}
 */

/** Modes prévus par la feuille de route, pas encore branchés côté serveur. */
const MODES_A_VENIR = ['compte', 'oauth', 'rfid'];

/**
 * Module d'authentification des joueurs, partagé par tous les interactifs.
 *
 * Une seule API quel que soit le mode : `await api.auth.connecter({ mode, … })` rend un joueur et
 * son **jeton visiteur** (`nvv2.…`). Si l'interactif a été lancé par Navinum (`api.launch()`), la
 * partie est rattachée automatiquement au joueur : ses événements alimentent son parcours et sa
 * gamification.
 *
 * Session : **30 minutes d'inactivité**, **12 heures au plus**. La librairie prolonge le jeton à chaque
 * activité (événements de la partie). Après expiration, `connecter()` avec le même pseudo sur le même appareil
 * retrouve le même profil (XP, médailles). En **mode borne** (`memoriser: false`), rien n'est conservé sur
 * l'appareil : appeler `deconnecter()` en fin de partie.
 *
 * Modes :
 * - `invite` (disponible) : visiteur invité à partir d'un pseudo, conservé sur l'appareil ;
 * - `compte`, `oauth`, `rfid` (à venir) : lèvent une erreur `MODE_INDISPONIBLE` en attendant ;
 * - modes personnalisés : {@link Auth#ajouterMode}.
 *
 * @class
 * @example
 * const ctx = await api.launch();                 // facultatif
 * const joueur = await api.auth.connecter({ pseudo: 'Léa' });   // mode « invite » par défaut
 * console.log(joueur.pseudo, joueur.anonyme);
 * api.auth.onChange(j => afficherProfil(j));      // connexion / déconnexion
 * api.auth.oublier();                            // « ce n’est pas moi » (déconnecter() : révoque aussi)
 */
export class Auth {
    /**
     * @param {NavinumAPI} api - Instance de la librairie
     */
    constructor(api) {
        this.api = api;
        this.fournisseurs = new Map();
        this.ecouteurs = [];
        /** Joueur conservé sur l'appareil. */
        this.stockage = new Stockage(api.config);
        /** Mode « invité » (pseudo seul). */
        this.modeInvite = new ModeInvite(api.visiteurs, this.stockage);
        this.ajouterMode('invite', async ({ pseudo, memoriser }) => ({ ...(await this.modeInvite.connecter({ pseudo, memoriser })) }));
    }

    /**
     * Modes de connexion utilisables maintenant.
     * @returns {string[]}
     *
     * @example
     * if (api.auth.modes().includes('rfid')) afficherBoutonBadge();
     */
    modes() {
        return [...this.fournisseurs.keys()];
    }

    /**
     * Ajoute (ou remplace) un mode de connexion.
     *
     * @param {string} nom - Nom du mode
     * @param {FournisseurAuth} fournisseur - Fonction qui connecte le joueur
     *
     * @example
     * api.auth.ajouterMode('kiosque', async () => ({ id: 'x', pseudo: 'Borne 3', anonyme: true, token: '…' }));
     */
    ajouterMode(nom, fournisseur) {
        if (!nom || typeof fournisseur !== 'function') {
            throw new ValidationError('Mode de connexion invalide', { mode: 'requis' });
        }
        this.fournisseurs.set(nom, fournisseur);
    }

    /**
     * Connecte le joueur et, si l'interactif a été lancé par Navinum, lui rattache la partie.
     *
     * @param {Object} [options={}]
     * @param {string} [options.mode='invite'] - Mode de connexion
     * @param {string} [options.pseudo] - Pseudo (mode `invite`, 2 à 40 caractères)
     * @param {boolean} [options.memoriser=true] - false (borne partagée) : rien n'est conservé sur l'appareil
     * @param {LaunchContext|null} [options.lancement] - Contexte de lancement ; à défaut, le dernier
     *        résolu par `api.launch()`
     * @returns {Promise<Joueur>}
     * @throws {ValidationError} Pseudo invalide
     * @throws {NavinumAPIError} code `MODE_INDISPONIBLE` pour un mode pas encore disponible
     * @throws {NetworkError} API injoignable (et aucun visiteur conservé pour ce pseudo)
     *
     * @example
     * try {
     *     const joueur = await api.auth.connecter({ pseudo: saisie });
     * } catch (err) {
     *     afficher(NavinumAPI.erreurs.decrire(err).message);
     * }
     */
    async connecter(options = {}) {
        const mode = options.mode || 'invite';
        const fournisseur = this.fournisseurs.get(mode);
        if (!fournisseur) {
            throw erreurs.creer('MODE_INDISPONIBLE', MODES_A_VENIR.includes(mode)
                ? `La connexion « ${mode} » n’est pas encore disponible.`
                : `Mode de connexion inconnu : « ${mode} ».`);
        }

        const joueur = { ...(await fournisseur(options, this.api)), mode };
        this.memoriser = options.memoriser !== false;
        if (!joueur.token) {
            throw new ValidationError('Le mode de connexion n’a pas fourni de jeton visiteur', { token: 'requis' });
        }

        const lancement = options.lancement !== undefined ? options.lancement : this.api.contexte;
        if (lancement && typeof lancement.identify === 'function') {
            try {
                await lancement.identify(joueur.token);
                joueur.rattache = true;
            } catch (error) {
                // Jamais bloquant : le joueur reste connecté, la partie n'est simplement pas rattachée
                joueur.rattache = false;
                this.api.config.log('Rattachement de la partie impossible', error);
            }
        }

        this.joueurCourant = joueur;
        this.notifier(joueur);
        return joueur;
    }

    /**
     * Joueur connecté dans cette page, à défaut celui conservé sur l'appareil (sans appel réseau).
     * @returns {Joueur|null}
     *
     * @example
     * champPseudo.value = api.auth.joueur()?.pseudo ?? '';
     */
    joueur() {
        if (this.joueurCourant) return this.joueurCourant;
        const conserve = this.stockage.lire();
        return conserve ? { ...conserve, mode: 'invite' } : null;
    }

    /**
     * Déconnecte le joueur : sa session est révoquée côté serveur (jetons invalides immédiatement) et il est
     * oublié sur cet appareil. À appeler en fin de partie sur une borne partagée. Jamais bloquant.
     *
     * @returns {Promise<void>}
     */
    async deconnecter() {
        const joueur = this.joueur();
        this.oublier();
        if (joueur && joueur.token) {
            try {
                await this.api.visiteurs.deconnexion(joueur.token);
            } catch (error) {
                this.api.config.log('Déconnexion côté serveur impossible', error);
            }
        }
    }

    /**
     * Oublie le joueur sur cet appareil sans le déconnecter côté serveur (« Ce n'est pas moi »).
     */
    oublier() {
        this.joueurCourant = null;
        this.stockage.effacer();
        this.notifier(null);
    }

    /**
     * Activité du joueur : prolonge sa session si elle expire bientôt. Appelé automatiquement à chaque
     * événement de la partie (`ctx.track()`). Jamais bloquant.
     *
     * @returns {Promise<Joueur|null>}
     */
    async entretenir() {
        const joueur = this.joueurCourant;
        if (!joueur || !joueur.token) {
            return null;
        }
        try {
            this.joueurCourant = { ...(await this.modeInvite.entretenir(joueur, { memoriser: this.memoriser })), mode: joueur.mode };
        } catch (error) {
            this.api.config.log('Prolongation de la session impossible', error);
        }
        return this.joueurCourant;
    }

    /**
     * Suit les connexions / déconnexions.
     * @param {function(Joueur|null): void} callback
     * @returns {function(): void} Fonction de désabonnement
     */
    onChange(callback) {
        this.ecouteurs.push(callback);
        return () => { this.ecouteurs = this.ecouteurs.filter(c => c !== callback); };
    }

    /** @private */
    notifier(joueur) {
        for (const ecouteur of this.ecouteurs) {
            try { ecouteur(joueur); } catch (e) { /* un écouteur ne casse pas la connexion */ }
        }
    }
}

export default Auth;
