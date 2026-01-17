# Guide de contribution - Navinum API

Ce document explique comment contribuer à la librairie navinum-api et **maintenir la documentation à jour**.

---

## ⚠️ IMPORTANT : Maintenir la documentation à jour

**À CHAQUE modification de la librairie ou des API Symfony, la documentation DOIT être mise à jour.**

### Checklist de mise à jour de la documentation

Avant de commit vos changements, vérifiez :

- [ ] **README.md** - Documentation principale mise à jour
- [ ] **Code comments** - JSDoc à jour dans les fichiers sources
- [ ] **CHANGELOG.md** - Changement documenté avec version
- [ ] **Exemples** - Exemples de code testés et fonctionnels

---

## 🔄 Scénarios de mise à jour

### 1. Ajout d'une nouvelle ressource API

**Fichiers à créer :**
```
src/resources/NouvelleAPI.js
```

**Fichiers à modifier :**
- ✅ `src/index.js` - Ajouter l'import et l'initialisation
- ✅ `README.md` - Section "API Usage" → Ajouter la nouvelle ressource
- ✅ `README.md` - Section "Structure du projet" → Ajouter le fichier
- ✅ `CHANGELOG.md` - Documenter l'ajout

**Template README pour nouvelle ressource :**
```markdown
### Nom de la ressource

#### Description de la méthode

```javascript
// Exemple de code
const result = await api.nouvelleResource.method(params);
console.log(result);
```

#### Autre méthode

```javascript
// Autre exemple
```
```

### 2. Ajout d'une nouvelle méthode à une ressource existante

**Fichiers à modifier :**
- ✅ `src/resources/[Resource]API.js` - Ajouter la méthode avec JSDoc
- ✅ `README.md` - Section correspondante → Ajouter exemple de la méthode
- ✅ `CHANGELOG.md` - Documenter l'ajout

**Exemple :**
```javascript
/**
 * Description de la nouvelle méthode
 * @param {type} param - Description
 * @returns {Promise} Description du retour
 */
async nouvelleMethode(param) {
    return this.client.get(`${this.basePath}/nouvelle/${param}`);
}
```

### 3. Modification d'une API Symfony existante

**Si le backend Symfony change (nouveau endpoint, nouveaux paramètres, etc.) :**

- ✅ `src/resources/[Resource]API.js` - Adapter la méthode
- ✅ `README.md` - Mettre à jour l'exemple correspondant
- ✅ `README.md` - Section "Exemples réels" si impacté
- ✅ `CHANGELOG.md` - Documenter le changement (BREAKING CHANGE si applicable)

**Exemple de documentation de breaking change :**
```markdown
## [2.0.0] - 2025-01-20

### BREAKING CHANGES
- `api.sessions.create()` : Le paramètre `theme` est maintenant obligatoire
- Ancien : `api.sessions.create({ playerName })`
- Nouveau : `api.sessions.create({ playerName, theme })`
```

### 4. Ajout d'une nouvelle option de configuration

**Fichiers à modifier :**
- ✅ `src/core/Config.js` - Ajouter l'option avec valeur par défaut
- ✅ `README.md` - Section "Configuration avancée" → Documenter l'option
- ✅ `CHANGELOG.md` - Documenter l'ajout

**Exemple :**
```markdown
### Options de configuration

```javascript
const api = new NavinumAPI({
    // ... options existantes

    // Nouvelle option (ajoutée en v1.2.0)
    cacheEnabled: true,     // Active le cache des requêtes
    cacheDuration: 60000    // Durée du cache en ms (défaut: 60000)
});
```
```

### 5. Correction de bug

**Fichiers à modifier :**
- ✅ Code source concerné
- ✅ `CHANGELOG.md` - Documenter le fix
- ✅ `README.md` - Si le bug impactait un exemple, le corriger

### 6. Amélioration de performance ou refactoring

**Fichiers à modifier :**
- ✅ Code source concerné
- ✅ `CHANGELOG.md` - Documenter l'amélioration
- ✅ `README.md` - Uniquement si l'API publique change

---

## 📝 Format du CHANGELOG

Suivre le format [Keep a Changelog](https://keepachangelog.com/) :

```markdown
## [Version] - Date YYYY-MM-DD

### Added
- Nouvelle fonctionnalité X
- Nouvelle méthode `api.resource.newMethod()`

### Changed
- Modification du comportement de Y

### Deprecated
- Méthode X est deprecated, utiliser Y à la place

### Removed
- Suppression de la méthode obsolète Z

### Fixed
- Correction du bug #123

### Security
- Correction de la vulnérabilité XYZ
```

---

## 🧪 Tester les exemples de documentation

**Avant de commit, vérifier que tous les exemples fonctionnent :**

1. Copier l'exemple du README
2. L'intégrer dans une page de test
3. Vérifier qu'il fonctionne sans erreur
4. Vérifier que le retour correspond à ce qui est documenté

**Exemple de page de test :**
```html
<!DOCTYPE html>
<html>
<head><title>Test README Example</title></head>
<body>
    <script src="/build/navinum-api.js"></script>
    <script>
        // Copier-coller l'exemple du README ici
        const api = new NavinumAPI({ debug: true });

        async function test() {
            // Votre exemple
            const result = await api.sessions.create({ playerName: 'Test' });
            console.log('Result:', result);
        }

        test();
    </script>
</body>
</html>
```

---

## 🏗️ Workflow de développement

### Développement d'une nouvelle fonctionnalité

1. **Créer une branche**
   ```bash
   git checkout -b feature/nom-feature
   ```

2. **Développer la fonctionnalité**
   - Écrire le code dans `src/`
   - Ajouter les JSDoc

3. **Mettre à jour la documentation**
   - README.md
   - CHANGELOG.md
   - Exemples si nécessaire

4. **Tester**
   ```bash
   npm run build
   # Tester dans le quiz ou créer une page de test
   ```

5. **Vérifier la checklist**
   - [ ] Code écrit et testé
   - [ ] JSDoc ajoutées
   - [ ] README.md mis à jour
   - [ ] CHANGELOG.md mis à jour
   - [ ] Exemples testés
   - [ ] Build réussi sans erreurs

6. **Commit**
   ```bash
   git add .
   git commit -m "feat: ajout de la fonctionnalité X

   - Ajout de la méthode api.resource.newMethod()
   - Documentation mise à jour dans README.md
   - Exemples ajoutés"
   ```

7. **Push et PR**
   ```bash
   git push origin feature/nom-feature
   ```

### Correction de bug

1. **Créer une branche**
   ```bash
   git checkout -b fix/nom-bug
   ```

2. **Corriger le bug**

3. **Mettre à jour CHANGELOG.md**
   ```markdown
   ### Fixed
   - Correction du bug où api.sessions.join() échouait avec...
   ```

4. **Commit**
   ```bash
   git commit -m "fix: correction du bug dans sessions.join()

   Le bug se produisait quand...
   Correction appliquée en..."
   ```

---

## 📚 Standards de documentation

### JSDoc dans le code

**Toujours documenter :**
- Classes
- Méthodes publiques
- Paramètres avec types
- Valeurs de retour

**Exemple :**
```javascript
/**
 * Récupère les parcours d'une exposition
 * @param {number} expositionId - ID de l'exposition
 * @param {Object} [params={}] - Paramètres optionnels de filtrage
 * @param {boolean} [params.actif] - Filtrer uniquement les parcours actifs
 * @returns {Promise<Array>} Liste des parcours
 * @throws {NotFoundError} Si l'exposition n'existe pas
 * @example
 * const parcours = await api.parcours.getByExposition(1);
 * console.log(`${parcours.length} parcours trouvés`);
 */
async getByExposition(expositionId, params = {}) {
    return this.client.get(`${this.basePath}`, {
        params: { exposition: expositionId, ...params }
    });
}
```

### Exemples dans le README

**Bonnes pratiques :**
- ✅ Exemples concrets et réalistes
- ✅ Commentaires expliquant les étapes importantes
- ✅ Gestion d'erreur quand pertinent
- ✅ Console.log pour montrer le résultat attendu
- ❌ Éviter les exemples trop simplistes ou abstraits
- ❌ Éviter le code sans contexte

**Bon exemple :**
```javascript
// Rechercher un visiteur par son badge RFID et démarrer sa visite
const visiteur = await api.visiteurs.getByRfid('TAG123456');

if (visiteur) {
    console.log(`Bienvenue ${visiteur.prenom} ${visiteur.nom}`);

    // Logger le début de visite
    await api.logVisites.track({
        visiteurId: visiteur.id,
        action: 'start_visit'
    });
}
```

**Mauvais exemple :**
```javascript
// Trop simple, sans contexte
const data = await api.visiteurs.getByRfid('TAG');
console.log(data);
```

---

## 🚀 Publication d'une nouvelle version

1. **Mettre à jour la version dans package.json**
   ```bash
   npm version patch  # 1.0.0 → 1.0.1
   npm version minor  # 1.0.0 → 1.1.0
   npm version major  # 1.0.0 → 2.0.0
   ```

2. **Mettre à jour CHANGELOG.md**
   - Ajouter la section de version avec la date
   - Lister tous les changements

3. **Build production**
   ```bash
   npm run build:prod
   ```

4. **Copier dans public/build**
   ```bash
   cp dist/navinum-api.js ../../../public/build/
   cp dist/navinum-api.min.js ../../../public/build/
   ```

5. **Tag Git**
   ```bash
   git tag -a v1.0.1 -m "Version 1.0.1"
   git push origin v1.0.1
   ```

6. **Publication npm** (quand prêt)
   ```bash
   npm publish
   ```

---

## 📋 Template de Pull Request

```markdown
## Description
Brève description du changement

## Type de changement
- [ ] Bug fix
- [ ] Nouvelle fonctionnalité
- [ ] Breaking change
- [ ] Documentation

## Checklist
- [ ] Code testé localement
- [ ] JSDoc ajoutées/mises à jour
- [ ] README.md mis à jour
- [ ] CHANGELOG.md mis à jour
- [ ] Exemples testés
- [ ] Build réussi
- [ ] Pas de breaking change (ou documenté si oui)

## Tests effectués
Décrire les tests réalisés

## Screenshots (si applicable)
Ajouter des captures d'écran
```

---

## 🔍 Revue de code

**Points à vérifier lors d'une review :**

1. **Code**
   - [ ] Respect des conventions de nommage
   - [ ] Pas de code dupliqué
   - [ ] Gestion d'erreur appropriée
   - [ ] JSDoc complètes

2. **Documentation**
   - [ ] README.md mis à jour
   - [ ] CHANGELOG.md mis à jour
   - [ ] Exemples clairs et testés

3. **Tests**
   - [ ] Build sans erreur
   - [ ] Exemples fonctionnels

---

## 💬 Questions ?

Si vous avez des questions sur la contribution ou la documentation :
- Consulter ce guide
- Regarder les commits précédents pour voir des exemples
- Demander à l'équipe technique

---

## 📌 Rappels importants

1. **La documentation est aussi importante que le code**
2. **Un exemple vaut mieux qu'une longue explication**
3. **Tester avant de documenter**
4. **Documenter en même temps que le développement, pas après**
5. **Penser à l'utilisateur final qui lira la doc**

---

**Dernière mise à jour :** 2025-01-17
