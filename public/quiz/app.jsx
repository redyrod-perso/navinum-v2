const { useState, useEffect, useRef } = React;

// Client Navinum : SEUL point d'accès du quiz à Navinum (API, temps réel Mercure, identification).
// Aucun fetch / EventSource / localStorage direct dans un interactif (vérifié par la CI).
const api = new NavinumAPI({
    // Journal détaillé en local uniquement (ou avec ?debug dans l'URL)
    debug: ['localhost', '127.0.0.1'].includes(window.location.hostname) || /[?&]debug\b/.test(window.location.search),
    // Hub Mercure : celui du site par défaut ; config.js peut en indiquer un autre
    mercureHub: window.APP_CONFIG?.MERCURE_PUBLIC_URL
});

// Module d'erreurs de la librairie : code stable, titre, message, action conseillée (aucun affichage)
const { erreurs } = NavinumAPI;

// Couche React de la librairie : l'état d'une partie multijoueur suit le module (aucun design)
const { usePartie } = NavinumAPI.creerHooksReact(React);

// Toutes les erreurs d'API sont journalisées au même endroit
api.onError(info => console.warn(`[Quiz] Erreur API ${info.code} :`, info.message));

/**
 * Connecte le joueur avec le module d'authentification (mode invité : visiteur conservé sur
 * l'appareil ou nouvel invité). La librairie rattache elle-même la partie lancée par Navinum.
 * Jamais bloquant : la partie continue sans identification.
 */
async function identifyPlayer(pseudo, modeBorne = false) {
    try {
        // Borne partagée : rien n'est conservé sur l'appareil, le joueur est déconnecté en fin de partie
        const joueur = await api.auth.connecter({ pseudo, memoriser: !modeBorne });
        console.log('[Quiz] Joueur identifié :', joueur.pseudo, joueur.id, joueur.rattache ? '(partie rattachée)' : '');
        return joueur;
    } catch (err) {
        console.warn('[Quiz] Identification impossible, la partie continue sans :', erreurs.decrire(err).message);
        return null;
    }
}

/** Bandeau d'erreur : présentation propre au quiz d'une erreur décrite par NavinumAPI.erreurs. */
function BandeauErreur({ info, onClose, onRetry }) {
    if (!info) return null;
    return (
        <div className="erreur-bandeau" role="alert">
            <button className="erreur-fermer" onClick={onClose} aria-label="Fermer">×</button>
            <strong>{info.titre}</strong>
            <span>{info.message}</span>
            {info.reessayable && onRetry && (
                <div className="erreur-actions">
                    <button onClick={onRetry}>↻ Réessayer</button>
                </div>
            )}
        </div>
    );
}

// Manifeste de l'interactif : son configSchema porte les valeurs par défaut
const MANIFEST_URL = '/quiz/navinum.json';

// Jeton de lancement Navinum (?nv=) - null en mode autonome / développement
const LAUNCH_TOKEN = NavinumAPI.getLaunchToken();

// Contexte de lancement (résultat de api.launch()), null en mode autonome
let launchContext = null;

// Valeurs par défaut du configSchema (pour fusionner les mises à jour Mercure)
let manifestDefaults = {};

/**
 * Envoie un événement à Navinum (module gamification) : rend les gains (XP, médailles) ou null en mode
 * autonome. Jamais bloquant : une erreur n'interrompt pas le jeu.
 */
function track(type, payload = {}) {
    return api.gamification.evenement(type, payload);
}

/**
 * Nettoie la configuration : entiers bornés, thèmes et questions valides uniquement.
 */
function normalizeConfig(raw) {
    const toInt = (value, fallback, min, max) => {
        const n = parseInt(value, 10);
        if (Number.isNaN(n)) return fallback;
        return Math.min(max, Math.max(min, n));
    };

    const themes = (Array.isArray(raw.themes) ? raw.themes : [])
        .filter(theme => theme && theme.id && Array.isArray(theme.questions))
        .map(theme => ({
            id: String(theme.id),
            libelle: theme.libelle || String(theme.id),
            questions: theme.questions.filter(q =>
                q && q.question &&
                Array.isArray(q.reponses) && q.reponses.length === 4 &&
                Number.isInteger(q.correcte) && q.correcte >= 0 && q.correcte <= 3
            )
        }))
        .filter(theme => theme.questions.length > 0);

    return {
        tempsParQuestion: toInt(raw.tempsParQuestion, 20, 0, 300),
        nbQuestions: toInt(raw.nbQuestions, 10, 1, 100),
        joueursMax: toInt(raw.joueursMax, 8, 1, 50),
        modeBorne: raw.modeBorne === true,
        themes
    };
}

/**
 * Charge la configuration du quiz :
 * - avec ?nv= : ctx.config (configuration du parcours) fusionnée sur les défauts du manifeste
 * - sans jeton : valeurs `default` du configSchema de navinum.json
 */
async function loadQuizConfig() {
    let defaults = {};
    try {
        ({ defaults } = await api.interactifs.chargerManifeste(MANIFEST_URL));
        manifestDefaults = defaults;
    } catch (err) {
        // Sans jeton, le manifeste est la seule source de configuration
        if (!LAUNCH_TOKEN) throw new Error('Impossible de charger ' + MANIFEST_URL + ' (' + err.message + ')');
        console.warn('[Quiz] Manifeste indisponible, utilisation de ctx.config seul:', err);
    }

    if (!LAUNCH_TOKEN) {
        return { config: normalizeConfig(defaults), ctx: null };
    }

    const ctx = await api.launch(LAUNCH_TOKEN);
    return { config: normalizeConfig({ ...defaults, ...(ctx.config || {}) }), ctx };
}

function QuizApp() {
    const [config, setConfig] = useState(null);
    const [configError, setConfigError] = useState(null);
    const [timeLeft, setTimeLeft] = useState(0);
    // Minuteur de passage à la question suivante
    const gameRef = useRef({ nextTimeout: null });
    // Configuration reçue via Mercure, appliquée hors partie uniquement
    const [pendingConfig, setPendingConfig] = useState(null);
    const [configNotice, setConfigNotice] = useState(false);
    // Config courante accessible depuis les callbacks longue durée (SSE)
    const configRef = useRef(null);
    configRef.current = config;

    // Partie multijoueur (module de la librairie) : groupe, lobby, leader, démarrage, scores, gagnant, départs.
    // Le quiz ne garde que l'affichage et ses questions. Objectif : toutes les questions du thème justes.
    const partieRef = useRef(null);
    if (!partieRef.current) {
        partieRef.current = api.multijoueur.partie({
            objectif: themeId => {
                const c = configRef.current;
                const theme = c && c.themes.find(t => t.id === themeId);
                return theme ? Math.min(theme.questions.length, c.nbQuestions) : null;
            }
        });
    }
    const partie = partieRef.current;
    const etat = usePartie(partie);
    const stage = { hors: 'home', lobby: 'lobby', en_cours: 'playing', terminee: 'finished' }[etat.etape];
    const sessionId = etat.groupe || '';
    const players = etat.joueurs.map(j => ({ name: j.nom, score: j.score, isLeader: j.leader }));
    const scores = Object.fromEntries(etat.joueurs.map(j => [j.nom, j.score]));
    const winner = etat.gagnant;

    const [themes, setThemes] = useState([]);
    const [selectedTheme, setSelectedTheme] = useState(null);
    const [playerName, setPlayerName] = useState('');
    const [groupName, setGroupName] = useState('');
    const [existingGroups, setExistingGroups] = useState([]);
    const [groupMode, setGroupMode] = useState('select'); // 'select' ou 'create'
    const [questions, setQuestions] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [selectedAnswer, setSelectedAnswer] = useState(null);
    const [showResult, setShowResult] = useState(false);
    const [notification, setNotification] = useState(null);
    // Erreur affichée (décrite par NavinumAPI.erreurs) et action « Réessayer » associée
    const [erreur, setErreur] = useState(null);
    const retryRef = useRef(null);
    const [busy, setBusy] = useState(false);
    // Gains de la partie (XP, médailles), renvoyés par Navinum à la fin
    const [gains, setGains] = useState(null);

    const afficherErreur = (err, retry = null) => {
        retryRef.current = retry;
        setErreur(erreurs.decrire(err));
    };
    const fermerErreur = () => {
        retryRef.current = null;
        setErreur(null);
    };
    const bandeauErreur = (
        <BandeauErreur
            info={erreur}
            onClose={fermerErreur}
            onRetry={retryRef.current ? () => { const retry = retryRef.current; fermerErreur(); retry(); } : null}
        />
    );

    // Charger la configuration (jeton Navinum ou défauts du manifeste) et les thèmes
    useEffect(() => {
        let unsubscribeConfig = () => {};
        let cancelled = false;
        loadQuizConfig()
            .then(({ config, ctx }) => {
                if (cancelled) return;
                launchContext = ctx;
                if (ctx) {
                    console.log('[Quiz] Lancé par Navinum:', ctx.interactif, ctx.parcours);
                    if (config.modeBorne) {
                        // Borne : jamais le nom du joueur précédent
                    } else if (ctx.visiteur && !ctx.visiteur.anonyme && ctx.visiteur.pseudo) {
                        setPlayerName(ctx.visiteur.pseudo);
                    } else if (api.auth.joueur()?.pseudo) {
                        setPlayerName(api.auth.joueur().pseudo); // joueur déjà venu sur ce navigateur
                    }
                } else {
                    console.log('[Quiz] Mode autonome : configuration par défaut de navinum.json');
                    // Joueur déjà venu sur ce navigateur (hors borne : la borne n'a pas de lien de lancement autonome)
                    if (api.auth.joueur()?.pseudo) setPlayerName(api.auth.joueur().pseudo);
                }
                if (config.themes.length === 0) {
                    setConfigError(erreurs.decrire(erreurs.creer('VALIDATION', 'Aucun thème configuré pour ce quiz.')));
                    return;
                }
                setThemes(config.themes.map(t => ({ id: t.id, libelle: t.libelle })));
                setConfig(config);

                // Mises à jour de configuration en direct (no-op si ctx.mercure est null)
                if (ctx) {
                    unsubscribeConfig = ctx.onConfigChange(newConfig => {
                        const next = normalizeConfig({ ...manifestDefaults, ...newConfig });
                        if (next.themes.length === 0) {
                            console.warn('[Quiz] Configuration reçue sans thème valide, ignorée');
                            return;
                        }
                        console.log('[Quiz] Nouvelle configuration reçue (Mercure)');
                        setPendingConfig(next);
                    });
                }
            })
            .catch(err => {
                console.error('Erreur chargement configuration:', err);
                // Lien expiré / invalide, réseau… : message du module d'erreurs
                setConfigError(erreurs.decrire(err));
            });
        return () => {
            cancelled = true;
            unsubscribeConfig();
        };
    }, []);

    // Appliquer une configuration reçue pour les PROCHAINES parties : jamais pendant une partie
    useEffect(() => {
        if (!pendingConfig || stage === 'playing') return;
        setConfig(pendingConfig);
        setThemes(pendingConfig.themes.map(t => ({ id: t.id, libelle: t.libelle })));
        // Thème sélectionné supprimé de la nouvelle configuration : le désélectionner
        if (selectedTheme && !pendingConfig.themes.some(t => t.id === selectedTheme)) {
            setSelectedTheme(null);
        }
        setPendingConfig(null);
        setConfigNotice(true);
    }, [pendingConfig, stage]);

    useEffect(() => {
        if (!configNotice) return;
        const timer = setTimeout(() => setConfigNotice(false), 5000);
        return () => clearTimeout(timer);
    }, [configNotice]);

    const configNoticeEl = configNotice && (
        <div className="config-notice" style={{ textAlign: 'center', fontSize: '0.85em', color: '#4caf50', marginBottom: '15px' }}>
            ✓ Configuration mise à jour
        </div>
    );

    const themeLabel = (themeId) => {
        const theme = themes.find(t => t.id === themeId);
        return theme ? theme.libelle : (themeId || '');
    };

    // Événements de début et de fin de partie (envoyés seulement avec un jeton)
    useEffect(() => {
        if (stage === 'playing') {
            track('quiz.started', {
                theme: etat.theme,
                nbQuestions: etat.objectif,
                tempsParQuestion: config ? config.tempsParQuestion : null,
                joueurs: players.length,
                groupe: sessionId
            });
        } else if (stage === 'finished') {
            // Fin (y compris quand un autre joueur gagne) : plus de passage à la question suivante
            if (gameRef.current.nextTimeout) {
                clearTimeout(gameRef.current.nextTimeout);
                gameRef.current.nextTimeout = null;
            }
            setGains(null);
            track('quiz.completed', {
                theme: etat.theme,
                score: etat.score,
                total: etat.objectif,
                gagnant: etat.gagnant,
                victoire: etat.victoire,
                joueurs: etat.joueurs.length,
                duree: etat.debut ? Math.round((Date.now() - etat.debut) / 1000) : null,
                // Le serveur reprend score, total et victoire de cette session (règle scoreSession)
                groupe: etat.groupe,
                joueur: etat.moi
            }).then(gains => {
                setGains(gains);
                // Borne : le joueur suivant ne doit pas jouer sous ce nom
                if (configRef.current && configRef.current.modeBorne) api.auth.deconnecter();
            });
        }
    }, [stage]);

    // Compte à rebours par question (tempsParQuestion = 0 : pas de limite)
    useEffect(() => {
        // Pas de compte à rebours avant le chargement des questions (la partie démarre juste avant)
        if (stage !== 'playing' || !config || config.tempsParQuestion <= 0 || showResult || questions.length === 0) {
            return;
        }
        if (timeLeft <= 0) {
            selectAnswer(null); // Temps écoulé : réponse comptée fausse
            return;
        }
        const timer = setTimeout(() => setTimeLeft(t => t - 1), 1000);
        return () => clearTimeout(timer);
    }, [stage, timeLeft, showResult, currentQuestionIndex, questions]);

    // Charger les groupes existants (via la librairie Navinum)
    const loadGroups = () => {
        api.multijoueur.groupes.lister()
            .then(groupes => setExistingGroups(groupes))
            .catch(err => {
                setExistingGroups([]);
                afficherErreur(err, loadGroups);
            });
    };

    // Charger les groupes au montage et à chaque fois qu'on revient sur home
    useEffect(() => {
        if (stage === 'home') {
            loadGroups();
        }
    }, [stage]);

    // Nouveaux groupes créés en direct (Mercure, uniquement sur home)
    useEffect(() => {
        if (stage !== 'home') {
            return;
        }
        return api.multijoueur.groupes.onCree(groupe => {
            console.log('Nouveau groupe créé:', groupe.nom);
            loadGroups();
        });
    }, [stage]);

    // Démarrage de la partie (par nous ou par le leader) : charger les questions du thème
    useEffect(() => {
        if (etat.etape === 'en_cours' && etat.theme) chargerQuestions(etat.theme);
    }, [etat.etape, etat.theme]);

    const showNotification = (message) => {
        setNotification(message);
        setTimeout(() => setNotification(null), 3000);
    };

    const chargerQuestions = (themeId) => {
        // Les questions viennent de la configuration (ctx.config ou défauts du manifeste)
        const config = configRef.current;
        const theme = config && config.themes.find(t => t.id === themeId);
        if (!theme) {
            afficherErreur(erreurs.creer('INTROUVABLE', `Le thème « ${themeId} » n’existe plus dans la configuration du quiz.`));
            return;
        }

        // Les nbQuestions premières questions, dans l'ordre : identiques pour tous les joueurs
        const questions = theme.questions.slice(0, config.nbQuestions).map((q, index) => ({
            id: index,
            question: q.question,
            answers: q.reponses,
            correctIndex: q.correcte
        }));

        setQuestions(questions);
        setCurrentQuestionIndex(0);
        setSelectedAnswer(null);
        setShowResult(false);
        setGains(null);
        setTimeLeft(config.tempsParQuestion);
        setSelectedTheme(themeId);
    };

    const joinGlobalSession = async () => {
        if (busy) return; // double clic : une seule tentative à la fois
        const nom = playerName.trim();
        const groupe = groupName.trim(); // le nom du groupe sert d'identifiant de session
        if (!nom) {
            afficherErreur(erreurs.creer('VALIDATION', 'Veuillez entrer votre nom.'));
            return;
        }
        if (!groupe) {
            afficherErreur(erreurs.creer('VALIDATION', 'Veuillez choisir ou créer un groupe.'));
            return;
        }

        fermerErreur();
        setBusy(true);
        setPlayerName(nom);
        try {
            // Le joueur devient un visiteur Navinum (ses événements seront enregistrés à son nom)
            const joueur = await identifyPlayer(nom, config.modeBorne);

            // Groupe retrouvé ou créé, lobby (le serveur impose le nombre maximum de joueurs du parcours)
            await partie.rejoindre(groupe, nom);

            // Lien de lancement déjà utilisé par un autre joueur encore actif : ses gains ne seront pas comptés
            if (joueur && joueur.motif === 'DEJA_RATTACHE') {
                afficherErreur(erreurs.creer('DEJA_RATTACHE', 'Vos XP et médailles ne seront pas comptés : ce lien est déjà utilisé par un autre joueur. Relancez le quiz depuis Navinum pour jouer à votre nom.'));
            }
        } catch (err) {
            // Partie en cours, groupe complet (imposé par le serveur), réseau… : titre, message, « Réessayer »
            afficherErreur(err, joinGlobalSession);
        } finally {
            setBusy(false);
        }
    };

    const startQuiz = () => {
        if (!selectedTheme) {
            afficherErreur(erreurs.creer('VALIDATION', 'Veuillez sélectionner un thème.'));
            return;
        }
        fermerErreur();
        showNotification('Démarrage du quiz...');

        // Le leader démarre tout de suite ; les autres joueurs suivent. Refus : retour au lobby + message
        partie.demarrer(selectedTheme).catch(err => afficherErreur(err));
    };

    // answerIndex = null : temps écoulé
    const selectAnswer = (answerIndex) => {
        if (showResult) return;

        const question = questions[currentQuestionIndex];
        if (!question) return; // questions pas encore chargées
        const isCorrect = answerIndex !== null && answerIndex === question.correctIndex;

        setSelectedAnswer(answerIndex);
        setShowResult(true);

        track('quiz.answered', {
            theme: selectedTheme,
            questionIndex: currentQuestionIndex,
            reponse: answerIndex,
            correcte: isCorrect,
            tempsReponse: config && config.tempsParQuestion > 0 ? config.tempsParQuestion - timeLeft : null
        });

        if (isCorrect) {
            // +1 (envoyé au serveur) ; atteindre l'objectif fait gagner la partie
            partie.marquerPoint();
            if (partie.etat().etape === 'terminee') return;
        }

        // Passer à la question suivante après 2 secondes
        gameRef.current.nextTimeout = setTimeout(() => {
            gameRef.current.nextTimeout = null;
            if (currentQuestionIndex < questions.length - 1) {
                setSelectedAnswer(null);
                setShowResult(false);
                setTimeLeft(config.tempsParQuestion);
                setCurrentQuestionIndex(currentQuestionIndex + 1);
            } else {
                // Toutes les questions sont posées mais pas toutes correctes
                partie.terminer();
            }
        }, 2000);
    };

    const disconnect = async () => {
        if (gameRef.current.nextTimeout) {
            clearTimeout(gameRef.current.nextTimeout);
            gameRef.current.nextTimeout = null;
        }
        if (stage === 'playing') {
            track('quiz.abandoned', {
                theme: selectedTheme,
                score: etat.score,
                questionIndex: currentQuestionIndex,
                total: questions.length
            });
        }
        // Quitter le groupe (module multijoueur) : retour à l'accueil
        await partie.quitter();
        setSelectedTheme(null);
        if (config && config.modeBorne) {
            // Borne : déconnexion (aussi en cours de partie) et champ vide pour le joueur suivant
            api.auth.deconnecter();
            setPlayerName('');
        } else {
            // Conserver le pseudo du visiteur Navinum s'il est connu
            const visiteur = (launchContext && launchContext.visiteur) || api.auth.joueur();
            setPlayerName(visiteur && visiteur.pseudo ? visiteur.pseudo : '');
        }
        setGroupName('');
        setQuestions([]);
        setCurrentQuestionIndex(0);
        setSelectedAnswer(null);
        setShowResult(false);
        setGains(null);
    };

    const restart = () => {
        disconnect();
    };

    // Rendu selon l'étape
    if (configError) {
        return (
            <div className="quiz-container">
                <h1>🎯 Quiz Interactif</h1>
                <div className="session-info">
                    <strong>{configError.titre}</strong>
                    <p>{configError.message}</p>
                    {configError.reessayable && (
                        <button className="start-button" style={{ marginTop: '15px' }} onClick={() => window.location.reload()}>
                            ↻ Réessayer
                        </button>
                    )}
                </div>
            </div>
        );
    }

    if (!config) {
        return (
            <div className="quiz-container">
                <h1>🎯 Quiz Interactif</h1>
                <p style={{ textAlign: 'center', color: '#666' }}>Chargement...</p>
            </div>
        );
    }

    if (stage === 'home') {
        return (
            <div className="quiz-container">
                <h1>🎯 Quiz Interactif</h1>
                {configNoticeEl}
                {bandeauErreur}
                <h2>Bienvenue !</h2>

                <div className="input-group">
                    <label>Votre nom :</label>
                    <input
                        type="text"
                        value={playerName}
                        onChange={(e) => setPlayerName(e.target.value)}
                        placeholder="Entrez votre nom"
                        onKeyDown={(e) => e.key === 'Enter' && (groupMode === 'select' ? document.getElementById('groupSelect').focus() : document.getElementById('groupNameInput').focus())}
                    />
                    {!config.modeBorne && playerName && api.auth.joueur()?.pseudo === playerName && (
                        <button
                            type="button"
                            className="pas-moi"
                            onClick={() => { api.auth.oublier(); setPlayerName(''); }}
                        >
                            Ce n’est pas moi
                        </button>
                    )}
                </div>

                <div className="input-group">
                    <label>Groupe :</label>
                    <div style={{ display: 'flex', gap: '10px', marginBottom: '10px' }}>
                        <button
                            onClick={() => setGroupMode('select')}
                            style={{
                                flex: 1,
                                padding: '10px',
                                background: groupMode === 'select' ? '#667eea' : '#e0e0e0',
                                color: groupMode === 'select' ? 'white' : '#333',
                                border: 'none',
                                borderRadius: '8px',
                                cursor: 'pointer',
                                fontWeight: groupMode === 'select' ? 'bold' : 'normal'
                            }}
                        >
                            Sélectionner un groupe
                        </button>
                        <button
                            onClick={() => setGroupMode('create')}
                            style={{
                                flex: 1,
                                padding: '10px',
                                background: groupMode === 'create' ? '#667eea' : '#e0e0e0',
                                color: groupMode === 'create' ? 'white' : '#333',
                                border: 'none',
                                borderRadius: '8px',
                                cursor: 'pointer',
                                fontWeight: groupMode === 'create' ? 'bold' : 'normal'
                            }}
                        >
                            Créer un groupe
                        </button>
                    </div>

                    {groupMode === 'select' ? (
                        <select
                            id="groupSelect"
                            value={groupName}
                            onChange={(e) => setGroupName(e.target.value)}
                            style={{
                                width: '100%',
                                padding: '12px',
                                border: '2px solid #e0e0e0',
                                borderRadius: '8px',
                                fontSize: '1em',
                                cursor: 'pointer'
                            }}
                            onKeyDown={(e) => e.key === 'Enter' && joinGlobalSession()}
                        >
                            <option value="">-- Choisir un groupe --</option>
                            {existingGroups.map(group => (
                                <option key={group.id || group.nom} value={group.nom}>
                                    {group.nom}
                                </option>
                            ))}
                        </select>
                    ) : (
                        <input
                            id="groupNameInput"
                            type="text"
                            value={groupName}
                            onChange={(e) => setGroupName(e.target.value)}
                            placeholder="Entrez le nom du nouveau groupe"
                            onKeyDown={(e) => e.key === 'Enter' && joinGlobalSession()}
                        />
                    )}
                </div>

                <button className="start-button" onClick={joinGlobalSession} disabled={busy}>
                    {busy ? 'Connexion…' : 'Rejoindre le lobby'}
                </button>
            </div>
        );
    }

    if (stage === 'lobby') {
        // Déterminer si l'utilisateur actuel est le leader
        const currentPlayer = players.find(p => p.name === playerName);
        const isLeader = currentPlayer?.isLeader || false;

        return (
            <div className="quiz-container">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <h1 style={{ margin: 0 }}>🎯 Lobby du Quiz</h1>
                    <button className="disconnect-button" onClick={disconnect}>
                        Se déconnecter
                    </button>
                </div>

                <div style={{
                    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    color: 'white',
                    padding: '15px 20px',
                    borderRadius: '10px',
                    marginBottom: '20px',
                    textAlign: 'center',
                    boxShadow: '0 4px 6px rgba(0,0,0,0.1)'
                }}>
                    <strong>🏠 Groupe :</strong> {groupName || sessionId}
                </div>

                {configNoticeEl}
                {bandeauErreur}

                <div className="lobby-players">
                    <h2>Joueurs connectés ({players.length}/{etat.joueursMax || config.joueursMax})</h2>
                    <ul className="players-list">
                        {players.map(player => (
                            <li key={player.name} className="player-item">
                                <span className="player-icon">{player.isLeader ? '👑' : '👤'}</span>
                                <span className="player-name">{player.name}</span>
                                {player.isLeader && <span className="you-badge" style={{background: '#ffa500'}}>Leader</span>}
                                {player.name === playerName && !player.isLeader && <span className="you-badge">(Vous)</span>}
                            </li>
                        ))}
                    </ul>
                </div>

                {isLeader ? (
                    <>
                        <div className="theme-selection-lobby">
                            <h2>Choisissez un thème</h2>
                            <div className="theme-selection">
                                {themes.map(theme => (
                                    <button
                                        key={theme.id}
                                        className={`theme-button ${selectedTheme === theme.id ? 'selected' : ''}`}
                                        onClick={() => setSelectedTheme(theme.id)}
                                    >
                                        {theme.libelle}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <button
                            className="start-button"
                            onClick={startQuiz}
                            disabled={!selectedTheme}
                            style={{ marginTop: '20px' }}
                        >
                            Démarrer le Quiz
                        </button>
                    </>
                ) : (
                    <div style={{
                        textAlign: 'center',
                        padding: '30px',
                        background: '#f8f9fa',
                        borderRadius: '15px',
                        marginTop: '20px'
                    }}>
                        <p style={{ fontSize: '1.2em', color: '#666', marginBottom: '10px' }}>
                            ⏳ En attente du leader...
                        </p>
                        <p style={{ color: '#999' }}>
                            Le leader du groupe va choisir le thème et démarrer le quiz
                        </p>
                    </div>
                )}

                {notification && (
                    <div className="notification">
                        {notification}
                    </div>
                )}
            </div>
        );
    }

    if (stage === 'playing') {
        const question = questions[currentQuestionIndex];
        // La partie démarre (état du module) juste avant que les questions du thème soient chargées
        if (!question) {
            return (
                <div className="quiz-container">
                    <h1>🎯 Quiz - {themeLabel(etat.theme)}</h1>
                    <p style={{ textAlign: 'center', color: '#666' }}>Démarrage…</p>
                </div>
            );
        }
        const myScore = scores[playerName] || 0;
        const progress = (myScore / questions.length) * 100;

        return (
            <div className="quiz-container">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <h1 style={{ margin: 0 }}>🎯 Quiz - {themeLabel(selectedTheme)}</h1>
                    <button className="disconnect-button" onClick={disconnect}>
                        Se déconnecter
                    </button>
                </div>

                <div className="score-board">
                    {players.map(player => (
                        <div key={player.name} className="player-score">
                            <div className="name">
                                👤 {player.name}
                                {player.name === playerName && ' (Vous)'}
                            </div>
                            <div className="score">{scores[player.name] || 0}</div>
                        </div>
                    ))}
                </div>

                {bandeauErreur}

                <div className="progress-bar">
                    <div className="progress-fill" style={{ width: `${progress}%` }}></div>
                </div>

                <div className="question-card">
                    <div className="question-text">
                        Question {currentQuestionIndex + 1}/{questions.length}: {question.question}
                    </div>

                    {config.tempsParQuestion > 0 && (
                        <div className="timer" style={{ textAlign: 'right', marginBottom: '15px', fontWeight: 'bold', color: timeLeft <= 5 ? '#f44336' : '#667eea' }}>
                            {showResult && selectedAnswer === null ? '⏰ Temps écoulé !' : `⏱ ${Math.max(0, timeLeft)} s`}
                        </div>
                    )}

                    <div className="answers-grid">
                        {question.answers.map((answer, index) => {
                            let className = 'answer-button';

                            if (showResult) {
                                if (index === question.correctIndex) {
                                    className += ' correct';
                                } else if (index === selectedAnswer) {
                                    className += ' incorrect';
                                }
                            }

                            return (
                                <button
                                    key={index}
                                    className={className}
                                    onClick={() => selectAnswer(index)}
                                    disabled={showResult}
                                >
                                    {answer}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {notification && (
                    <div className="notification">
                        {notification}
                    </div>
                )}
            </div>
        );
    }

    if (stage === 'finished') {
        return (
            <div className="quiz-container">
                <h1>🎯 Quiz Terminé !</h1>

                {winner && (
                    <div className="winner-message">
                        <h2>🏆 {winner === playerName ? 'Vous avez gagné !' : `${winner} a gagné !`}</h2>
                        <p>Félicitations !</p>
                    </div>
                )}

                {gains && (gains.xp.length > 0 || gains.medailles.length > 0) && (
                    <div className="gamification">
                        {gains.xp.length > 0 && (
                            <div className="xp">+{gains.xp.reduce((total, gain) => total + gain.points, 0)} XP</div>
                        )}
                        <div>Total : {gains.totalXp} XP</div>
                        {gains.medailles.length > 0 && (
                            <ul>
                                {gains.medailles.map(medaille => (
                                    <li key={medaille.libelle} title={medaille.description || ''}>🏅 Nouvelle médaille : <strong>{medaille.libelle}</strong></li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <div className="score-board">
                    {players.map(player => (
                        <div key={player.name} className="player-score">
                            <div className="name">
                                👤 {player.name}
                                {player.name === playerName && ' (Vous)'}
                            </div>
                            <div className="score">{scores[player.name] || 0}/{questions.length}</div>
                        </div>
                    ))}
                </div>

                <button className="start-button" onClick={restart}>
                    Nouveau Quiz
                </button>
            </div>
        );
    }

    return null;
}

ReactDOM.createRoot(document.getElementById('root')).render(<QuizApp />);
