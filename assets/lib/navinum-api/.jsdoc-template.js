/**
 * Template JSDoc pour navinum-api
 *
 * Ce fichier sert de référence pour documenter correctement le code.
 * Copier-coller les templates appropriés lors de l'ajout de nouvelles méthodes.
 */

// ============================================
// TEMPLATE 1: Classe
// ============================================

/**
 * Description de la classe
 *
 * @class
 * @example
 * const instance = new MaClasse(param);
 * instance.method();
 */
class MaClasse {
    /**
     * Constructeur de la classe
     * @param {Type} param - Description du paramètre
     */
    constructor(param) {
        this.param = param;
    }
}

// ============================================
// TEMPLATE 2: Méthode simple (GET)
// ============================================

/**
 * Récupère une ressource par son ID
 *
 * @param {number} id - Identifiant de la ressource
 * @returns {Promise<Object>} La ressource demandée
 * @throws {NotFoundError} Si la ressource n'existe pas
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * const resource = await api.resources.getById(1);
 * console.log('Nom:', resource.nom);
 */
async getById(id) {
    return this.client.get(`${this.basePath}/${id}`);
}

// ============================================
// TEMPLATE 3: Méthode avec paramètres optionnels
// ============================================

/**
 * Récupère toutes les ressources avec filtres optionnels
 *
 * @param {Object} [params={}] - Paramètres de filtrage optionnels
 * @param {string} [params.nom] - Filtrer par nom (recherche partielle)
 * @param {boolean} [params.actif] - Filtrer uniquement les ressources actives
 * @param {number} [params.limit=20] - Nombre maximum de résultats
 * @param {number} [params.offset=0] - Décalage pour la pagination
 * @returns {Promise<Array>} Liste des ressources
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * // Sans filtres
 * const all = await api.resources.getAll();
 *
 * @example
 * // Avec filtres
 * const active = await api.resources.getAll({
 *     actif: true,
 *     limit: 10
 * });
 */
async getAll(params = {}) {
    return this.client.get(this.basePath, { params });
}

// ============================================
// TEMPLATE 4: Méthode POST avec données
// ============================================

/**
 * Crée une nouvelle ressource
 *
 * @param {Object} data - Données de la ressource à créer
 * @param {string} data.nom - Nom de la ressource (requis)
 * @param {string} [data.description] - Description optionnelle
 * @param {boolean} [data.actif=true] - État actif/inactif
 * @returns {Promise<Object>} La ressource créée avec son ID
 * @throws {ValidationError} Si les données sont invalides
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * const newResource = await api.resources.create({
 *     nom: 'Ma ressource',
 *     description: 'Description détaillée',
 *     actif: true
 * });
 * console.log('ID créé:', newResource.id);
 */
async create(data) {
    return this.client.post(this.basePath, data);
}

// ============================================
// TEMPLATE 5: Méthode PUT/PATCH
// ============================================

/**
 * Met à jour une ressource existante
 *
 * @param {number} id - Identifiant de la ressource
 * @param {Object} data - Données à mettre à jour
 * @param {string} [data.nom] - Nouveau nom
 * @param {string} [data.description] - Nouvelle description
 * @returns {Promise<Object>} La ressource mise à jour
 * @throws {NotFoundError} Si la ressource n'existe pas
 * @throws {ValidationError} Si les données sont invalides
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * await api.resources.update(1, {
 *     nom: 'Nouveau nom'
 * });
 */
async update(id, data) {
    return this.client.put(`${this.basePath}/${id}`, data);
}

// ============================================
// TEMPLATE 6: Méthode DELETE
// ============================================

/**
 * Supprime une ressource
 *
 * @param {number} id - Identifiant de la ressource à supprimer
 * @returns {Promise<void>}
 * @throws {NotFoundError} Si la ressource n'existe pas
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * await api.resources.delete(1);
 * console.log('Ressource supprimée');
 */
async delete(id) {
    return this.client.delete(`${this.basePath}/${id}`);
}

// ============================================
// TEMPLATE 7: Méthode spécifique métier
// ============================================

/**
 * Recherche des ressources par critères spécifiques
 *
 * @param {Object} criteria - Critères de recherche
 * @param {string} criteria.query - Texte de recherche
 * @param {string[]} [criteria.tags] - Tags à filtrer
 * @param {Date} [criteria.dateMin] - Date minimum
 * @param {Date} [criteria.dateMax] - Date maximum
 * @returns {Promise<Array>} Résultats de recherche avec score de pertinence
 * @throws {ValidationError} Si les critères sont invalides
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * const results = await api.resources.search({
 *     query: 'interactif',
 *     tags: ['science', 'histoire'],
 *     dateMin: new Date('2024-01-01')
 * });
 *
 * results.forEach(result => {
 *     console.log(`${result.nom} (score: ${result.score})`);
 * });
 */
async search(criteria) {
    return this.client.post(`${this.basePath}/search`, criteria);
}

// ============================================
// TEMPLATE 8: Méthode avec types complexes
// ============================================

/**
 * Type définissant une session
 * @typedef {Object} Session
 * @property {string} id - Identifiant unique de la session
 * @property {Player[]} players - Liste des joueurs
 * @property {string} status - Statut ('waiting'|'playing'|'finished')
 * @property {string|null} theme - Thème du jeu
 * @property {Date} createdAt - Date de création
 */

/**
 * Type définissant un joueur
 * @typedef {Object} Player
 * @property {string} name - Nom du joueur
 * @property {number} score - Score actuel
 * @property {boolean} isReady - Joueur prêt
 */

/**
 * Crée une nouvelle session de jeu
 *
 * @param {Object} options - Options de création
 * @param {string} options.playerName - Nom du premier joueur
 * @param {string} [options.sessionId='global'] - ID de session personnalisé
 * @param {string} [options.theme] - Thème prédéfini
 * @returns {Promise<{session: Session}>} Session créée
 * @throws {ValidationError} Si le nom du joueur est vide
 * @throws {NetworkError} En cas d'erreur réseau
 *
 * @example
 * const { session } = await api.sessions.create({
 *     playerName: 'Alice',
 *     sessionId: 'room-123',
 *     theme: 'sciences'
 * });
 *
 * console.log('Session:', session.id);
 * console.log('Joueurs:', session.players.length);
 */
async createSession(options) {
    return this.client.post('/api/sessions/create', options);
}

// ============================================
// TEMPLATE 9: Méthode avec callbacks
// ============================================

/**
 * Callback appelé lors de la progression
 * @callback ProgressCallback
 * @param {number} current - Élément actuel
 * @param {number} total - Nombre total d'éléments
 * @param {Object} item - Élément en cours de traitement
 */

/**
 * Traite plusieurs ressources avec callback de progression
 *
 * @param {number[]} ids - Liste des IDs à traiter
 * @param {ProgressCallback} onProgress - Callback de progression
 * @returns {Promise<Object[]>} Résultats du traitement
 *
 * @example
 * const results = await api.resources.processMany(
 *     [1, 2, 3, 4, 5],
 *     (current, total, item) => {
 *         console.log(`Traitement ${current}/${total}: ${item.nom}`);
 *     }
 * );
 */
async processMany(ids, onProgress) {
    const results = [];
    for (let i = 0; i < ids.length; i++) {
        const item = await this.getById(ids[i]);
        results.push(item);
        if (onProgress) {
            onProgress(i + 1, ids.length, item);
        }
    }
    return results;
}

// ============================================
// TEMPLATE 10: Propriété de classe
// ============================================

/**
 * Classe avec propriétés documentées
 */
class ResourceAPI {
    /**
     * @param {ApiClient} client - Instance du client HTTP
     */
    constructor(client) {
        /**
         * Client HTTP pour les requêtes
         * @type {ApiClient}
         * @private
         */
        this.client = client;

        /**
         * Chemin de base de l'API
         * @type {string}
         * @readonly
         */
        this.basePath = '/api/resources';

        /**
         * Nom de la ressource
         * @type {string}
         * @readonly
         */
        this.resourceName = 'resource';
    }
}

// ============================================
// BONNES PRATIQUES
// ============================================

/**
 * ✅ À FAIRE :
 *
 * 1. Documenter TOUTES les méthodes publiques
 * 2. Décrire TOUS les paramètres avec leur type
 * 3. Indiquer TOUTES les exceptions possibles
 * 4. Fournir AU MOINS un exemple fonctionnel
 * 5. Utiliser des types précis (number, string, boolean, Object, Array)
 * 6. Marquer les paramètres optionnels avec []
 * 7. Donner des valeurs par défaut quand applicable
 * 8. Utiliser @typedef pour les types complexes
 *
 * ❌ À ÉVITER :
 *
 * 1. Laisser des méthodes publiques sans JSDoc
 * 2. Utiliser "any" comme type
 * 3. Oublier de documenter les paramètres optionnels
 * 4. Ne pas indiquer les exceptions
 * 5. Exemples non fonctionnels ou trop abstraits
 * 6. Documentation obsolète
 * 7. Copier-coller sans adapter
 */

// ============================================
// VÉRIFICATION
// ============================================

/**
 * Pour vérifier la qualité de votre JSDoc :
 *
 * 1. Générer la doc :
 *    npx jsdoc src/ -r -d docs/
 *
 * 2. Vérifier dans VSCode :
 *    - Hover sur la méthode pour voir la doc
 *    - IntelliSense doit afficher les paramètres
 *
 * 3. Tester l'exemple :
 *    - Copier l'exemple dans un fichier test
 *    - Vérifier qu'il fonctionne réellement
 */
