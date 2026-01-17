# Git Hooks pour navinum-api

Ce dossier contient des git hooks optionnels pour améliorer le workflow de développement.

## 📌 Hooks disponibles

### pre-commit-doc-reminder.sh

**But :** Rappeler de mettre à jour la documentation avant chaque commit

**Fonctionnalités :**
- Détecte si des fichiers source (`src/`) ont été modifiés
- Affiche une checklist de documentation
- Vérifie si README.md ou CHANGELOG.md ont été modifiés
- Demande confirmation si aucune doc n'a été mise à jour

## 🔧 Installation

### Option 1 : Installation manuelle (Recommandé)

```bash
# Aller dans le dossier de la librairie
cd assets/lib/navinum-api

# Copier le hook dans .git/hooks/
cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit

# Rendre le hook exécutable
chmod +x .git/hooks/pre-commit
```

### Option 2 : Installation automatique via script

```bash
# Créer un script d'installation
cat > install-hooks.sh << 'EOF'
#!/bin/bash
echo "Installation des git hooks..."
cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
echo "✅ Hook pre-commit installé avec succès !"
EOF

chmod +x install-hooks.sh
./install-hooks.sh
```

### Option 3 : Configuration Git globale (pour tous les repos)

```bash
# Configurer le dossier de templates git hooks
git config --global core.hooksPath .git-hooks

# Note: Cette option affecte tous vos repos Git !
```

## 🎯 Utilisation

Une fois installé, le hook s'exécute automatiquement avant chaque commit.

### Exemple de sortie

```
🔍 Vérification de la documentation...

⚠️  Fichiers source modifiés détectés :
src/resources/SessionAPI.js

📝 RAPPEL : Avez-vous mis à jour la documentation ?

Checklist :
  [ ] JSDoc à jour dans le code source
  [ ] README.md mis à jour (si API publique changée)
  [ ] CHANGELOG.md mis à jour
  [ ] Exemples testés
  [ ] npm run build réussi

⚠️  ATTENTION : Aucune modification de README.md ou CHANGELOG.md détectée !

Êtes-vous sûr de vouloir continuer sans mettre à jour la doc ? (y/N)
```

## ⚙️ Désactivation temporaire

Si vous devez bypass le hook pour un commit spécifique :

```bash
git commit --no-verify -m "Message de commit"
```

**⚠️ Attention :** N'abusez pas de `--no-verify` ! Le hook est là pour une bonne raison.

## 🗑️ Désinstallation

```bash
# Supprimer le hook
rm .git/hooks/pre-commit

# Ou le désactiver
mv .git/hooks/pre-commit .git/hooks/pre-commit.disabled
```

## 📝 Personnalisation

Vous pouvez modifier le hook selon vos besoins :

```bash
# Éditer le hook
nano .git/hooks/pre-commit

# Exemple : Rendre la vérification obligatoire (sans prompt)
# Remplacer la section "read -p" par :
# echo "❌ Documentation manquante ! Commit annulé."
# exit 1
```

## 🤝 Contribution

Pour améliorer les hooks :

1. Modifier le fichier dans `.git-hooks/`
2. Tester localement
3. Commit et push
4. Les autres développeurs devront réinstaller le hook

## 📚 Ressources

- [Documentation Git Hooks](https://git-scm.com/book/fr/v2/Personnalisation-de-Git-Crochets-Git)
- [CONTRIBUTING.md](../CONTRIBUTING.md) - Guide de contribution complet

---

**Note :** Les git hooks ne sont pas versionnés par défaut dans `.git/hooks/`, c'est pourquoi nous les gardons dans `.git-hooks/` et les copions manuellement.
