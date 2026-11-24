# Workflows n8n de Navinum

Les workflows sont **versionnés ici en JSON** (export n8n), relus en PR comme le code. Ils ne contiennent **aucun
secret** : les identifiants (Brevo, clé d'intégration Navinum…) sont créés dans n8n et référencés par leur nom.

## n8n local (facultatif, #16)

Même version que sur le VPS. Service Compose au profil `n8n` (il ne démarre pas avec le reste par défaut) :

```bash
docker compose -f docker-compose.yaml -f docker-compose.dev.yaml --profile n8n up -d
```

- Interface : http://localhost:5678 (créer le compte propriétaire au premier lancement).
- Depuis n8n, Navinum répond sur `http://app` (réseau Docker interne, comme sur le VPS).
- Arrêt : `docker compose -f docker-compose.yaml -f docker-compose.dev.yaml --profile n8n stop n8n`.

## Importer / exporter un workflow

```bash
# Importer tous les workflows du dépôt dans le n8n local
docker exec navinum_n8n n8n import:workflow --separate --input=/workflows
# Exporter un workflow modifié dans l'interface (puis copier le fichier dans n8n/workflows/)
docker exec navinum_n8n n8n export:workflow --id=<id> --output=/home/node/.n8n/export.json
```

Sur le VPS, même commande d'import dans le conteneur n8n de production.
