# Modèle de données — 005

```
Organisateur 1─* Exposition *─* Parcours 1─* ParcoursInteractif *─1 Interactif
                                                   │ ordre, config (JSON)       │ variable (JSON Schema)
                                                   │                            │
                                            LaunchToken *─────────────────────────┘
                                              token, expiresAt, visiteur?, exposition?, logVisite?
                                                   │
                                               LogVisite (score, resultats = événements, start/end)

User (admin_user) : email, roles, password, organisateur?, isActive
```

| Entité | Table | Rôle |
|---|---|---|
| `ParcoursInteractif` | `parcours_interactif` | interactif placé dans un parcours : `ordre`, `config` (surcharge des défauts). Ancienne table de jointure migrée en conservant les liens. |
| `LaunchToken` | `launch_token` | jeton `?nv=` (48 hex, 12 h) ; relie interactif, placement, exposition, visiteur et le `LogVisite` créé au premier événement |
| `User` | `admin_user` | compte d'admin ; `ROLE_ADMIN` ou `ROLE_ORGANISATEUR` (+ organisateur rattaché) |
| `Interactif.variable` | — | JSON Schema de configuration (ou JSON simple) |

Migration : `migrations/Version20261121180000.php` (réversible).
