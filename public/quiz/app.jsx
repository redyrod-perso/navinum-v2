const { useState, useEffect, useRef } = React;

// Client Navinum : SEUL point d'accès du quiz à Navinum (API, temps réel Mercure, identification).
// Aucun fetch / EventSource / localStorage direct dans un interactif (vérifié par la CI).
const api = new NavinumAPI({
    debug: true,
    // Hub Mercure : celui du site par défaut ; config.js peut en indiquer un autre
    mercureHub: window.APP_CONFIG?.MERCURE_PUBLIC_URL
});

// Référence aux classes d'erreur
const { NotFoundError, GoneError } = NavinumAPI;

/**
 * Identifie le joueur (visiteur Navinum conservé sur l'appareil, ou nouvel invité) et rattache
 * la partie lancée par Navinum à son visiteur. Jamais bloquant : la partie continue sans.
 */
async function identifyPlayer(pseudo) {
    try {
        const visiteur = await api.visiteurs.identifier(pseudo);
        if (launchContext) {
            await launchContext.identify(visiteur.token);
        }
        console.log('[Quiz] Joueur identifié :', visiteur.pseudo, visiteur.id);
        return visiteur;
    } catch (err) {
        console.warn('[Quiz] Identification impossible, la partie continue sans :', err);
        return null;
    }
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
 * Envoie un événement à Navinum, uniquement si le quiz a été lancé avec un jeton.
 * Les erreurs sont journalisées sans interrompre le jeu.
 */
function track(type, payload = {}) {
    if (!launchContext) return;
    launchContext.track(type, payload)
        .catch(err => console.warn(`[Quiz] Événement ${type} non envoyé:`, err));
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
    // Suivi de la partie en cours (score local, début, minuteur de passage à la question suivante)
    const gameRef = useRef({ score: 0, startedAt: null, nextTimeout: null });
    // Configuration reçue via Mercure, appliquée hors partie uniquement
    const [pendingConfig, setPendingConfig] = useState(null);
    const [configNotice, setConfigNotice] = useState(false);
    // Config courante accessible depuis les callbacks longue durée (SSE)
    const configRef = useRef(null);
    configRef.current = config;

    const [stage, setStage] = useState('home'); // home, lobby, playing, finished
    const [themes, setThemes] = useState([]);
    const [selectedTheme, setSelectedTheme] = useState(null);
    const [playerName, setPlayerName] = useState('');
    const [groupName, setGroupName] = useState('');
    const [existingGroups, setExistingGroups] = useState([]);
    const [groupMode, setGroupMode] = useState('select'); // 'select' ou 'create'
    const [sessionId, setSessionId] = useState('');
    const [players, setPlayers] = useState([]);
    const [questions, setQuestions] = useState([]);
    const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
    const [scores, setScores] = useState({});
    const [selectedAnswer, setSelectedAnswer] = useState(null);
    const [showResult, setShowResult] = useState(false);
    const [winner, setWinner] = useState(null);
    const [notification, setNotification] = useState(null);

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
                    if (ctx.visiteur && !ctx.visiteur.anonyme && ctx.visiteur.pseudo) {
                        setPlayerName(ctx.visiteur.pseudo);
                    } else if (api.visiteurs.courant()?.pseudo) {
                        setPlayerName(api.visiteurs.courant().pseudo); // joueur déjà venu sur ce navigateur
                    }
                } else {
                    console.log('[Quiz] Mode autonome : configuration par défaut de navinum.json');
                }
                if (config.themes.length === 0) {
                    setConfigError('Aucun thème configuré pour ce quiz.');
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
                if (err instanceof GoneError) {
                    setConfigError('Ce lien a expiré. Relancez le quiz depuis Navinum.');
                } else if (err instanceof NotFoundError) {
                    setConfigError('Lien de lancement invalide.');
                } else {
                    setConfigError('Impossible de charger la configuration du quiz : ' + err.message);
                }
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
            gameRef.current.startedAt = Date.now();
            track('quiz.started', {
                theme: selectedTheme,
                nbQuestions: questions.length,
                tempsParQuestion: config ? config.tempsParQuestion : null,
                joueurs: players.length,
                groupe: sessionId
            });
        } else if (stage === 'finished') {
            const startedAt = gameRef.current.startedAt;
            track('quiz.completed', {
                theme: selectedTheme,
                score: gameRef.current.score,
                total: questions.length,
                gagnant: winner,
                victoire: winner !== null && winner === playerName,
                joueurs: players.length,
                duree: startedAt ? Math.round((Date.now() - startedAt) / 1000) : null
            });
        }
    }, [stage]);

    // Compte à rebours par question (tempsParQuestion = 0 : pas de limite)
    useEffect(() => {
        if (stage !== 'playing' || !config || config.tempsParQuestion <= 0 || showResult) {
            return;
        }
        if (timeLeft <= 0) {
            selectAnswer(null); // Temps écoulé : réponse comptée fausse
            return;
        }
        const timer = setTimeout(() => setTimeLeft(t => t - 1), 1000);
        return () => clearTimeout(timer);
    }, [stage, timeLeft, showResult, currentQuestionIndex]);

    // Charger les groupes existants (via la librairie Navinum)
    const loadGroups = () => {
        api.rfidGroupes.list()
            .then(groupes => setExistingGroups(groupes))
            .catch(err => {
                console.error('Erreur chargement groupes:', err);
                setExistingGroups([]);
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
        return api.rfidGroupes.onCreated(groupe => {
            console.log('Nouveau groupe créé:', groupe.nom);
            loadGroups();
        });
    }, [stage]);

    // Session en temps réel (Mercure) : la librairie relit l'état à chaque (re)connexion
    // puis transmet chaque changement (joueurs, démarrage, scores).
    useEffect(() => {
        if (!sessionId || (stage !== 'lobby' && stage !== 'playing')) {
            return;
        }
        console.log(`[Session] Suivi en temps réel de ${sessionId}`);

        return api.sessions.subscribe(sessionId, session => {
            setPlayers(session.players || []);

            // Le leader a démarré le quiz
            if (stage === 'lobby' && session.status === 'playing' && session.theme) {
                console.log(`[Session] Quiz démarré ! Thème : ${session.theme}`);
                loadQuestionsAndStart(session.theme);
            }

            // Scores en direct
            if (stage === 'playing' && session.players) {
                const newScores = {};
                session.players.forEach(player => {
                    newScores[player.name] = player.score;
                });
                setScores(newScores);
            }
        }, { onError: err => console.warn('[Session] Relecture de l’état impossible :', err) });
    }, [sessionId, stage]);

    const showNotification = (message) => {
        setNotification(message);
        setTimeout(() => setNotification(null), 3000);
    };

    const loadQuestionsAndStart = (themeId) => {
        // Les questions viennent de la configuration (ctx.config ou défauts du manifeste).
        // configRef : ce callback est aussi appelé depuis le flux SSE, ouvert avant une mise à jour éventuelle
        const config = configRef.current;
        const theme = config && config.themes.find(t => t.id === themeId);
        if (!theme) {
            console.error('Thème introuvable dans la configuration:', themeId);
            alert('Erreur : thème "' + themeId + '" introuvable');
            return;
        }

        // Les nbQuestions premières questions, dans l'ordre : identiques pour tous les joueurs
        const questions = theme.questions.slice(0, config.nbQuestions).map((q, index) => ({
            id: index,
            question: q.question,
            answers: q.reponses,
            correctIndex: q.correcte
        }));

        gameRef.current.score = 0;
        setQuestions(questions);
        setCurrentQuestionIndex(0);
        setSelectedAnswer(null);
        setShowResult(false);
        setWinner(null);
        setTimeLeft(config.tempsParQuestion);
        setSelectedTheme(themeId);
        setStage('playing');
    };

    const joinGlobalSession = async () => {
        if (!playerName.trim()) {
            alert('Veuillez entrer votre nom');
            return;
        }

        if (!groupName.trim()) {
            alert('Veuillez entrer le nom du groupe');
            return;
        }

        try {
            // Normaliser le nom du groupe pour l'utiliser comme sessionId
            const normalizedGroupName = groupName.trim();

            // Le joueur devient un visiteur Navinum (ses événements seront enregistrés à son nom)
            await identifyPlayer(playerName.trim());

            // 0. Vérifier que le groupe n'est pas complet (joueursMax)
            try {
                const existing = await api.sessions.get(normalizedGroupName);
                const existingPlayers = (existing && existing.session && existing.session.players) || [];
                const alreadyIn = existingPlayers.some(p => p.name === playerName);
                if (!alreadyIn && existingPlayers.length >= config.joueursMax) {
                    alert(`Ce groupe est complet (${config.joueursMax} joueurs maximum)`);
                    return;
                }
            } catch (err) {
                // 404 : la session n'existe pas encore, elle sera créée plus bas
                if (!(err instanceof NotFoundError)) {
                    console.warn('Vérification du nombre de joueurs impossible:', err);
                }
            }

            // 1. Retrouver le groupe par son nom, ou le créer (via la librairie Navinum)
            try {
                const groupe = await api.rfidGroupes.findOrCreate(normalizedGroupName);
                console.log('Groupe :', groupe.nom);
            } catch (err) {
                console.error('Erreur gestion groupe :', err);
                alert('Erreur lors de la gestion du groupe : ' + err.message);
                return;
            }

            // 2. Rejoindre ou créer la session avec le nom du groupe comme ID
            let data;
            try {
                data = await api.sessions.join(normalizedGroupName, { playerName: playerName });
            } catch (err) {
                // Si la session n'existe pas encore, la créer
                if (err instanceof NotFoundError) {
                    data = await api.sessions.create({
                        playerName: playerName,
                        sessionId: normalizedGroupName,
                        rfidGroupeName: normalizedGroupName
                    });
                } else {
                    throw err;
                }
            }

            if (!data.session || !data.session.players) {
                console.error('Réponse invalide:', data);
                alert('Erreur: Réponse serveur invalide');
                return;
            }

            setSessionId(normalizedGroupName);
            setPlayers(data.session.players);
            setStage('lobby');
        } catch (err) {
            console.error('Erreur jonction session:', err);
            alert('Erreur de connexion au serveur: ' + err.message);
        }
    };

    const startQuiz = async () => {
        if (!selectedTheme) {
            alert('Veuillez sélectionner un thème');
            return;
        }

        try {
            console.log('[Quiz] Démarrage immédiat du quiz pour le leader...');
            showNotification('Démarrage du quiz...');

            // OPTIMISATION : Le leader démarre IMMÉDIATEMENT (pas besoin d'attendre le SSE)
            // Il connaît déjà le thème qu'il a choisi
            loadQuestionsAndStart(selectedTheme);

            // En parallèle, notifier les autres joueurs via l'API
            // Appel non-bloquant pour ne pas ralentir le leader
            api.sessions.start(sessionId, {
                theme: selectedTheme,
                playerName: playerName
            }).then(() => {
                console.log('[Quiz] Autres joueurs notifiés via API');
            }).catch(err => {
                console.error('[Quiz] Erreur notification API:', err);
                // Pas grave, le leader est déjà redirigé
                // Les autres recevront la mise à jour via SSE
            });

        } catch (err) {
            console.error('[Quiz] Erreur inattendue:', err);
            alert('Erreur lors du démarrage: ' + err.message);
        }
    };

    // answerIndex = null : temps écoulé
    const selectAnswer = (answerIndex) => {
        if (showResult) return;

        const question = questions[currentQuestionIndex];
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
            const newScore = gameRef.current.score + 1;
            gameRef.current.score = newScore;
            setScores(prev => ({ ...prev, [playerName]: newScore }));

            // Mettre à jour le score côté serveur
            api.sessions.updateScore(sessionId, {
                playerName: playerName,
                score: newScore
            }).catch(err => console.error('Erreur mise à jour score:', err));

            // Vérifier si le joueur a fini
            if (newScore >= questions.length) {
                setWinner(playerName);
                setStage('finished');
                return;
            }
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
                setStage('finished');
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
                score: gameRef.current.score,
                questionIndex: currentQuestionIndex,
                total: questions.length
            });
        }
        gameRef.current.score = 0;
        gameRef.current.startedAt = null;

        if (sessionId && playerName) {
            try {
                await api.sessions.leave(sessionId, { playerName: playerName });
            } catch (err) {
                console.error('Erreur déconnexion:', err);
            }
        }

        // Réinitialiser l'état
        setStage('home');
        setSelectedTheme(null);
        // Conserver le pseudo du visiteur Navinum s'il est connu
        const visiteur = (launchContext && launchContext.visiteur) || api.visiteurs.courant();
        setPlayerName(visiteur && visiteur.pseudo ? visiteur.pseudo : '');
        setGroupName('');
        setSessionId('');
        setPlayers([]);
        setQuestions([]);
        setCurrentQuestionIndex(0);
        setScores({});
        setSelectedAnswer(null);
        setShowResult(false);
        setWinner(null);
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
                    <strong>{configError}</strong>
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
                <h2>Bienvenue !</h2>

                <div className="input-group">
                    <label>Votre nom :</label>
                    <input
                        type="text"
                        value={playerName}
                        onChange={(e) => setPlayerName(e.target.value)}
                        placeholder="Entrez votre nom"
                        onKeyPress={(e) => e.key === 'Enter' && (groupMode === 'select' ? document.getElementById('groupSelect').focus() : document.getElementById('groupNameInput').focus())}
                    />
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
                            onKeyPress={(e) => e.key === 'Enter' && joinGlobalSession()}
                        >
                            <option value="">-- Choisir un groupe --</option>
                            {existingGroups.map((group, index) => (
                                <option key={index} value={group.nom}>
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
                            onKeyPress={(e) => e.key === 'Enter' && joinGlobalSession()}
                        />
                    )}
                </div>

                <button className="start-button" onClick={joinGlobalSession}>
                    Rejoindre le lobby
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

                <div className="lobby-players">
                    <h2>Joueurs connectés ({players.length}/{config.joueursMax})</h2>
                    <ul className="players-list">
                        {players.map((player, index) => (
                            <li key={index} className="player-item">
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
                    {players.map((player, index) => (
                        <div key={index} className="player-score">
                            <div className="name">
                                👤 {player.name}
                                {player.name === playerName && ' (Vous)'}
                            </div>
                            <div className="score">{scores[player.name] || 0}</div>
                        </div>
                    ))}
                </div>

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

                <div className="score-board">
                    {players.map((player, index) => (
                        <div key={index} className="player-score">
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
