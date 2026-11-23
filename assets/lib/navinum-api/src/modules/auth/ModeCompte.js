import { erreurs } from '../erreurs/Erreurs.js';
import { ModeInvite } from './ModeInvite.js';

/**
 * Mode de connexion « compte » : adresse e-mail + mot de passe (#4). Même jeton visiteur que les autres modes.
 *
 * - `connecter({ email, motDePasse })` : connexion (nouvel appareil) ;
 * - `connecter({})` sans mot de passe : reprise de la session du compte conservée sur cet appareil (session
 *   en cours, ou reprise par le secret d'appareil après 30 min d'inactivité) ; sinon `NON_IDENTIFIE` ;
 * - `creerCompte()` : inscription ; un invité connecté devient un compte et garde ses XP et médailles.
 *
 * @class
 */
export class ModeCompte {
    /**
     * @param {VisiteurAPI} visiteurs - Appels HTTP visiteur
     * @param {Stockage} stockage - Joueur conservé sur l'appareil
     */
    constructor(visiteurs, stockage) {
        this.visiteurs = visiteurs;
        this.stockage = stockage;
    }

    async connecter({ email, motDePasse, memoriser = true } = {}) {
        if (!motDePasse) {
            return this.reprendre(memoriser);
        }
        const res = await this.visiteurs.connexion({ email, motDePasse });
        return this.conserver({ ...res.visiteur, token: res.token, expiresAt: res.expiresAt, appareil: res.appareil }, memoriser);
    }

    /**
     * @param {{email: string, motDePasse: string, pseudo?: string, newsletter?: boolean, memoriser?: boolean}} options
     * @param {Object|null} joueurActuel - Joueur connecté : s'il est invité, il devient ce compte
     */
    async creerCompte({ email, motDePasse, pseudo, newsletter = false, memoriser = true }, joueurActuel = null) {
        const invite = joueurActuel && joueurActuel.anonyme && joueurActuel.token && !ModeInvite.expire(joueurActuel) ? joueurActuel : null;
        const res = await this.visiteurs.inscription({ email, motDePasse, pseudo, newsletter: newsletter === true }, invite ? invite.token : undefined);
        return this.conserver({
            ...res.visiteur, token: res.token, expiresAt: res.expiresAt,
            appareil: res.appareil || (invite ? invite.appareil : undefined),
        }, memoriser);
    }

    /** @private */
    async reprendre(memoriser) {
        const saved = memoriser ? this.stockage.lire() : null;
        if (!saved || saved.mode !== 'compte') {
            throw erreurs.creer('NON_IDENTIFIE', 'Connectez-vous avec votre adresse e-mail et votre mot de passe.');
        }
        if (!ModeInvite.expire(saved)) {
            return saved;
        }
        try {
            const res = await this.visiteurs.reprendre({ pseudo: saved.pseudo, appareil: saved.appareil });
            return this.conserver({ ...saved, ...res.visiteur, token: res.token, expiresAt: res.expiresAt }, memoriser);
        } catch (error) {
            if (erreurs.est(error, 'NON_IDENTIFIE')) {
                throw erreurs.creer('NON_IDENTIFIE', 'Session expirée : reconnectez-vous avec votre mot de passe.');
            }
            throw error;
        }
    }

    /** @private */
    conserver(joueur, memoriser) {
        const complet = { ...joueur, mode: 'compte' };
        if (memoriser) {
            this.stockage.ecrire(complet);
        }
        return complet;
    }
}

export default ModeCompte;
