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

    // Polling pour recevoir les mises à jour
    useEffect(() => {
        if (sessionId && (stage === 'lobby' || stage === 'playing')) {
            const pollInterval = setInterval(async () => {
                try {
                    const data = await api.sessions.get(sessionId);
                    const session = data.session;

                    if (session) {
                        // Mise à jour de la liste des joueurs
                        if (JSON.stringify(players) !== JSON.stringify(session.players)) {
                            setPlayers(session.players);
                        }

                        // Vérifier si le quiz a démarré
                        if (stage === 'lobby' && session.status === 'playing' && session.theme) {
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
                    console.error('Erreur polling:', err);
                }
            }, 1000); // Poll every second

            return () => {
                clearInterval(pollInterval);
            };
        }
    }, [sessionId, stage, playerName, players]);

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

        const globalSessionId = 'global';

        try {
            let data;

            // D'abord essayer de rejoindre la session globale
            try {
                data = await api.sessions.join(globalSessionId, { playerName: playerName });
            } catch (err) {
                // Si la session n'existe pas encore, la créer
                if (err instanceof NotFoundError) {
                    data = await api.sessions.create({
                        playerName: playerName,
                        sessionId: globalSessionId
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

            setSessionId(globalSessionId);
            setPlayers(data.session.players);
            setStage('lobby');
        } catch (err) {
            console.error('Erreur jonction session globale:', err);
            alert('Erreur de connexion au serveur: ' + err.message);
        }
    };

    const startQuiz = async () => {
        if (!selectedTheme) {
            alert('Veuillez sélectionner un thème');
            return;
        }

        try {
            await api.sessions.start(sessionId, { theme: selectedTheme });
            // Le quiz démarrera via le polling
        } catch (err) {
            console.error('Erreur démarrage quiz:', err);
            alert(err.message || 'Impossible de démarrer le quiz');
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
                        onKeyPress={(e) => e.key === 'Enter' && joinGlobalSession()}
                    />
                </div>

                <button className="start-button" onClick={joinGlobalSession}>
                    Rejoindre le lobby
                </button>
            </div>
        );
    }

    if (stage === 'lobby') {
        return (
            <div className="quiz-container">
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <h1 style={{ margin: 0 }}>🎯 Lobby du Quiz</h1>
                    <button className="disconnect-button" onClick={disconnect}>
                        Se déconnecter
                    </button>
                </div>

                <div className="lobby-players">
                    <h2>Joueurs connectés ({players.length})</h2>
                    <ul className="players-list">
                        {players.map((player, index) => (
                            <li key={index} className="player-item">
                                <span className="player-icon">👤</span>
                                <span className="player-name">{player.name}</span>
                                {player.name === playerName && <span className="you-badge">(Vous)</span>}
                            </li>
                        ))}
                    </ul>
                </div>

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
