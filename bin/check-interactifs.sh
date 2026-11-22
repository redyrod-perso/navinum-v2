#!/bin/sh
# Garde-fou : les interactifs (public/*) n'accèdent à Navinum QUE via la librairie @navinum/api.
# Interdits dans leur code : fetch(), new EventSource(), XMLHttpRequest, localStorage / sessionStorage.
# Exclus : la librairie publiée (public/build), les ressources des bundles Symfony (public/bundles),
# les pages de diagnostic (test-*.html) et la configuration locale (config.js).
# Usage : bin/check-interactifs.sh   (code de sortie 1 si un appel direct est trouvé)
cd "$(dirname "$0")/.." || exit 2

matches=$(grep -rnE '\bfetch\(|new EventSource\(|XMLHttpRequest|\b(localStorage|sessionStorage)\b' public \
    --include='*.js' --include='*.jsx' --include='*.html' \
    --exclude-dir=build --exclude-dir=bundles --exclude='test-*.html' --exclude='config.js' \
    | grep -vE '^\S+:[0-9]+:\s*(//|\*)')

if [ -n "$matches" ]; then
    echo "Appels directs interdits dans un interactif (utiliser la librairie @navinum/api) :"
    echo "$matches"
    exit 1
fi
echo "Interactifs : aucun appel direct, tout passe par @navinum/api."
