/**
 * Couche React facultative : des hooks (de la logique, aucun design) au-dessus des modules.
 *
 * La librairie ne dépend pas de React : on lui passe l'instance de React de l'interactif.
 *
 * @example
 * const { usePartie, useJoueur, useGains } = NavinumAPI.creerHooksReact(React);
 * function Lobby({ partie }) {
 *     const etat = usePartie(partie);          // se met à jour à chaque changement
 *     return <p>{etat.joueurs.length} joueurs</p>;
 * }
 *
 * @param {Object} React - L'objet React de l'interactif (`useState`, `useEffect`)
 * @returns {{usePartie: function(Partie): EtatPartie, useJoueur: function(NavinumAPI): (Joueur|null),
 *            useGains: function(NavinumAPI): (Gains|null)}}
 */
export function creerHooks(React) {
    const { useState, useEffect } = React;

    /** État d'une partie multijoueur ({@link Partie}), mis à jour à chaque changement. */
    function usePartie(partie) {
        const [etat, setEtat] = useState(() => partie.etat());
        useEffect(() => {
            setEtat(partie.etat());
            return partie.onChange(suivant => setEtat(suivant));
        }, [partie]);
        return etat;
    }

    /** Joueur connecté (`api.auth`), ou null. */
    function useJoueur(api) {
        const [joueur, setJoueur] = useState(() => api.auth.joueur());
        useEffect(() => api.auth.onChange(j => setJoueur(j)), [api]);
        return joueur;
    }

    /** Derniers gains reçus (`api.gamification`), ou null. */
    function useGains(api) {
        const [gains, setGains] = useState(null);
        useEffect(() => api.gamification.onGains(g => setGains(g)), [api]);
        return gains;
    }

    return { usePartie, useJoueur, useGains };
}

export default creerHooks;
