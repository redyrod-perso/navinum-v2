const { useState, useEffect } = React;

// Initialiser le client API Navinum
const api = new NavinumAPI({
    debug: true
});

// Référence aux classes d'erreur
const { NotFoundError } = NavinumAPI;

function QuizApp() {
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

    // Charger les thèmes disponibles
    useEffect(() => {
        fetch('/quiz/themes.json')
            .then(res => res.json())
            .then(data => setThemes(data))
            .catch(err => console.error('Erreur chargement thèmes:', err));
    }, []);

    // Fonction pour charger les groupes existants
    const loadGroups = () => {
        fetch('/api/rfid_groupes', {
            headers: {
                'Accept': 'application/ld+json'
            }
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Erreur HTTP: ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                console.log('Groupes reçus:', data);
                // API Platform retourne hydra:member ou member
                const groupes = data['hydra:member'] || data.member || [];
                if (Array.isArray(groupes)) {
                    setExistingGroups(groupes);
                } else {
                    console.error('Format inattendu:', data);
                    setExistingGroups([]);
                }
            })
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

    // Écouter Mercure pour les nouveaux groupes créés (uniquement sur home)
    useEffect(() => {
        if (stage !== 'home') {
            return;
        }

        let eventSource = null;

        try {
            // S'abonner au topic des groupes RFID
            const mercureUrl = new URL('http://srv802003.hstgr.cloud:3000/.well-known/mercure');
            mercureUrl.searchParams.append('topic', 'rfid-groupes');

            eventSource = new EventSource(mercureUrl);

            eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);

                    if (data.type === 'groupe_created') {
                        console.log('Nouveau groupe créé:', data.groupe);
                        // Rafraîchir la liste des groupes
                        loadGroups();
                    }
                } catch (err) {
                    console.error('Erreur parsing Mercure:', err);
                }
            };

            eventSource.onerror = (err) => {
                console.error('Erreur Mercure:', err);
                eventSource.close();
            };
        } catch (err) {
            console.error('Erreur création EventSource Mercure:', err);
        }

        return () => {
            if (eventSource) {
                eventSource.close();
            }
        };
    }, [stage]);

    // SSE pour recevoir les mises à jour en temps réel avec reconnexion automatique
    useEffect(() => {
        if (!sessionId || (stage !== 'lobby' && stage !== 'playing')) {
            return;
        }

        let eventSource = null;
        let reconnectTimeout = null;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 10;
        const baseReconnectDelay = 1000; // 1 seconde

        const connect = () => {
            if (reconnectAttempts >= maxReconnectAttempts) {
                console.error('[SSE] Nombre maximum de reconnexions atteint');
                return;
            }

            console.log(`[SSE] Connexion au stream pour session ${sessionId} (tentative ${reconnectAttempts + 1})`);

            try {
                eventSource = new EventSource(`/api/session/${sessionId}/stream`);

                eventSource.onopen = () => {
                    console.log('[SSE] Connexion établie');
                    reconnectAttempts = 0; // Reset le compteur en cas de succès
                };

                eventSource.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);

                        if (data.type === 'session' && data.session) {
                            const session = data.session;
                            console.log('[SSE] Session reçue:', {
                                status: session.status,
                                theme: session.theme,
                                nbPlayers: session.players?.length
                            });

                            // Mise à jour de la liste des joueurs (comparaison intelligente)
                            const playersChanged = players.length !== session.players.length ||
                                players.some((p, i) =>
                                    p.name !== session.players[i]?.name ||
                                    p.score !== session.players[i]?.score ||
                                    p.isLeader !== session.players[i]?.isLeader
                                );

                            if (playersChanged) {
                                console.log('[SSE] Mise à jour liste des joueurs');
                                setPlayers(session.players);
                            }

                            // Vérifier si le quiz a démarré
                            if (stage === 'lobby' && session.status === 'playing' && session.theme) {
                                console.log(`[SSE] Quiz démarré ! Chargement du thème: ${session.theme}`);
                                loadQuestionsAndStart(session.theme);
                            }

                            // Mise à jour des scores
                            if (stage === 'playing' && session.players) {
                                const newScores = {};
                                session.players.forEach(player => {
                                    newScores[player.name] = player.score;
                                });
                                setScores(newScores);
                            }
                        }
                    } catch (err) {
                        console.error('[SSE] Erreur parsing:', err);
                    }
                };

                eventSource.onerror = (err) => {
                    console.warn('[SSE] Erreur connexion, reconnexion dans ' +
                        (baseReconnectDelay * (reconnectAttempts + 1)) + 'ms');
                    eventSource.close();

                    // Reconnexion avec backoff exponentiel
                    const delay = baseReconnectDelay * (reconnectAttempts + 1);
                    reconnectAttempts++;

                    reconnectTimeout = setTimeout(() => {
                        connect();
                    }, delay);
                };
            } catch (err) {
                console.error('[SSE] Erreur création EventSource:', err);
            }
        };

        connect();

        return () => {
            if (eventSource) {
                console.log('[SSE] Fermeture de la connexion');
                eventSource.close();
            }
            if (reconnectTimeout) {
                clearTimeout(reconnectTimeout);
            }
        };
    }, [sessionId, stage]);

    const showNotification = (message) => {
        setNotification(message);
        setTimeout(() => setNotification(null), 3000);
    };

    const loadQuestionsAndStart = async (theme) => {
        try {
            const res = await fetch(`/quiz/questions/${theme}.txt`);
            const text = await res.text();

            // Parser le fichier texte
            const lines = text.trim().split('\n');
            const questions = [];

            lines.forEach((line, index) => {
                if (!line.trim()) return;

                const parts = line.split('|');
                if (parts.length !== 6) return;

                questions.push({
                    id: index,
                    question: parts[0],
                    answers: [parts[1], parts[2], parts[3], parts[4]],
                    correctIndex: parseInt(parts[5])
                });
            });

            setQuestions(questions);
            setSelectedTheme(theme);
            setStage('playing');
        } catch (err) {
            console.error('Erreur chargement questions:', err);
            alert('Erreur lors du chargement des questions');
        }
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

            // 1. Vérifier si le groupe RFID existe ou le créer
            try {
                // Chercher le groupe par nom via l'API
                const response = await fetch(`/api/rfid_groupes?nom=${encodeURIComponent(normalizedGroupName)}`);
                const groupsData = await response.json();

                if (!groupsData.member || groupsData.member.length === 0) {
                    // Le groupe n'existe pas, le créer
                    const createResponse = await fetch('/api/rfid_groupes', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/ld+json',
                        },
                        body: JSON.stringify({
                            nom: normalizedGroupName
                        })
                    });

                    if (!createResponse.ok) {
                        throw new Error('Impossible de créer le groupe');
                    }

                    console.log('Groupe RFID créé:', normalizedGroupName);
                } else {
                    console.log('Groupe RFID trouvé:', groupsData.member[0]);
                }
            } catch (err) {
                console.error('Erreur gestion groupe RFID:', err);
                alert('Erreur lors de la gestion du groupe: ' + err.message);
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
            // Ne pas bloquer l'UI, laisser le SSE gérer la redirection
            console.log('[Quiz] Démarrage du quiz...');
            showNotification('Démarrage du quiz...');

            // Appel non-bloquant (on n'attend pas la réponse complète)
            api.sessions.start(sessionId, {
                theme: selectedTheme,
                playerName: playerName
            }).then(() => {
                console.log('[Quiz] Requête de démarrage envoyée avec succès');
            }).catch(err => {
                console.error('[Quiz] Erreur démarrage:', err);
                // Même en cas d'erreur, le SSE peut avoir reçu la mise à jour
                // Ne pas afficher d'alerte si c'est juste un timeout réseau
                if (!err.message.includes('Timeout') && !err.message.includes('Network')) {
                    if (err.message && err.message.includes('leader')) {
                        alert('Seul le leader du groupe peut démarrer le quiz');
                    } else {
                        alert(err.message || 'Impossible de démarrer le quiz');
                    }
                }
            });

            // Le quiz démarrera via le SSE qui détectera le changement de status
        } catch (err) {
            console.error('[Quiz] Erreur inattendue:', err);
        }
    };

    const selectAnswer = (answerIndex) => {
        if (selectedAnswer !== null) return;

        const question = questions[currentQuestionIndex];
        const isCorrect = answerIndex === question.correctIndex;

        setSelectedAnswer(answerIndex);
        setShowResult(true);

        const myCurrentScore = scores[playerName] || 0;

        if (isCorrect) {
            const newScore = myCurrentScore + 1;
            setScores({ ...scores, [playerName]: newScore });

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
        setTimeout(() => {
            if (currentQuestionIndex < questions.length - 1) {
                setCurrentQuestionIndex(currentQuestionIndex + 1);
                setSelectedAnswer(null);
                setShowResult(false);
            } else if ((scores[playerName] || 0) < questions.length) {
                // Toutes les questions sont posées mais pas toutes correctes
                setStage('finished');
            }
        }, 2000);
    };

    const disconnect = async () => {
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
        setPlayerName('');
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
    if (stage === 'home') {
        return (
            <div className="quiz-container">
                <h1>🎯 Quiz Interactif</h1>
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

                <div className="lobby-players">
                    <h2>Joueurs connectés ({players.length})</h2>
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
                                        key={theme}
                                        className={`theme-button ${selectedTheme === theme ? 'selected' : ''}`}
                                        onClick={() => setSelectedTheme(theme)}
                                    >
                                        {theme.charAt(0).toUpperCase() + theme.slice(1)}
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
                    <h1 style={{ margin: 0 }}>🎯 Quiz - {selectedTheme.charAt(0).toUpperCase() + selectedTheme.slice(1)}</h1>
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
                                    disabled={selectedAnswer !== null}
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

ReactDOM.render(<QuizApp />, document.getElementById('root'));
