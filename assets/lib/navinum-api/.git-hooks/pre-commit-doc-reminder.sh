#!/bin/bash

# Git pre-commit hook pour rappeler de mettre à jour la documentation
#
# Installation (optionnelle) :
# cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit
# chmod +x .git/hooks/pre-commit

echo ""
echo "🔍 Vérification de la documentation..."
echo ""

# Vérifier si des fichiers source ont été modifiés
MODIFIED_SRC=$(git diff --cached --name-only | grep "^src/")

if [ -n "$MODIFIED_SRC" ]; then
    echo "⚠️  Fichiers source modifiés détectés :"
    echo "$MODIFIED_SRC"
    echo ""
    echo "📝 RAPPEL : Avez-vous mis à jour la documentation ?"
    echo ""
    echo "Checklist :"
    echo "  [ ] JSDoc à jour dans le code source"
    echo "  [ ] README.md mis à jour (si API publique changée)"
    echo "  [ ] CHANGELOG.md mis à jour"
    echo "  [ ] Exemples testés"
    echo "  [ ] npm run build réussi"
    echo ""

    # Vérifier si README ou CHANGELOG ont été modifiés
    README_MODIFIED=$(git diff --cached --name-only | grep "README.md")
    CHANGELOG_MODIFIED=$(git diff --cached --name-only | grep "CHANGELOG.md")

    if [ -z "$README_MODIFIED" ] && [ -z "$CHANGELOG_MODIFIED" ]; then
        echo "⚠️  ATTENTION : Aucune modification de README.md ou CHANGELOG.md détectée !"
        echo ""
        read -p "Êtes-vous sûr de vouloir continuer sans mettre à jour la doc ? (y/N) " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "❌ Commit annulé. Veuillez mettre à jour la documentation."
            exit 1
        fi
    else
        echo "✅ Documentation modifiée détectée"
    fi
fi

echo "✅ Vérification terminée"
echo ""

# Laisser le commit continuer
exit 0
