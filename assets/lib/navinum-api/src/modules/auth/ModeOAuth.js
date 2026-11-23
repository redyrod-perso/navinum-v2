import { erreurs } from '../erreurs/Erreurs.js';

/** Fragment d'URL posé par Navinum au retour du fournisseur. */
const FRAGMENT = /(?:^#|&)navinum-oauth(-erreur)?=([^&]+)/;

/**
 * Mode de connexion « oauth » : Google, Microsoft, Facebook… (selon les fournisseurs activés sur le serveur).
 *
 * La connexion quitte la page : `connecter({ fournisseur })` envoie le joueur chez le fournisseur, qui le
 * renvoie sur la même page ; au chargement, `api.auth.retourConnexion()` termine la connexion. Le jeton n'apparaît
 * jamais dans l'URL (seulement un code d'échange à usage unique).
 *
 * @class
 */
export class ModeOAuth {
    /**
     * @param {VisiteurAPI} visiteurs
     * @param {Stockage} stockage
     * @param {function(): (Location|null)} lieu - `window.location` (remplaçable dans les tests)
     */
    constructor(visiteurs, stockage, lieu = () => (typeof window !== 'undefined' ? window.location : null)) {
        this.visiteurs = visiteurs;
        this.stockage = stockage;
        this.lieu = lieu;
    }

    /**
     * Envoie le joueur chez le fournisseur. La promesse ne se résout pas : la page est quittée.
     * @param {{fournisseur: string, memoriser?: boolean}} options
     * @param {Object|null} joueurActuel - Joueur connecté (invité ou compte) : le fournisseur lui sera lié
     */
    async connecter({ fournisseur, memoriser = true } = {}, joueurActuel = null) {
        if (!fournisseur) {
            throw erreurs.creer('VALIDATION', 'Fournisseur de connexion manquant (ex. google).');
        }
        const lieu = this.lieu();
        const retour = lieu ? lieu.href.split('#')[0] : '/';
        const jeton = joueurActuel && joueurActuel.token ? joueurActuel.token : undefined;
        const { url } = await this.visiteurs.demarrerOAuth(fournisseur, { retour, memoriser }, jeton);
        if (lieu) lieu.assign(url);
        return new Promise(() => {});
    }

    /**
     * Termine une connexion au retour du fournisseur (fragment `#navinum-oauth=…`), sinon rend null.
     * @returns {Promise<Object|null>}
     * @throws {NavinumAPIError} `CONNEXION_ANNULEE`, `NON_IDENTIFIE` (code expiré)…
     */
    async terminer() {
        const lieu = this.lieu();
        const trouve = lieu && lieu.hash ? lieu.hash.match(FRAGMENT) : null;
        if (!trouve) {
            return null;
        }
        ModeOAuth.nettoyer(lieu);
        if (trouve[1]) {
            throw erreurs.creer(trouve[2]);
        }
        const res = await this.visiteurs.echangeOAuth(trouve[2]);
        const joueur = { ...res.visiteur, token: res.token, expiresAt: res.expiresAt, appareil: res.appareil, fournisseur: res.fournisseur, mode: 'oauth' };
        if (res.memoriser !== false) {
            this.stockage.ecrire(joueur);
        }
        return { joueur, memoriser: res.memoriser !== false };
    }

    /** Retire le fragment de l'URL (ne pas le laisser dans l'historique ni le rejouer). @private */
    static nettoyer(lieu) {
        try {
            if (typeof history !== 'undefined' && history.replaceState) {
                history.replaceState(null, '', lieu.href.split('#')[0]);
            } else {
                lieu.hash = '';
            }
        } catch (e) { /* sans effet */ }
    }
}

export default ModeOAuth;
