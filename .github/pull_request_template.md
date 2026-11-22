## Quoi et pourquoi

<!-- Ce que change la PR, et pourquoi. Tickets : « Closes #… » -->

## Vérifications

- [ ] Tests ajoutés ou adaptés ; **un bug corrigé = un test qui échouait sans le correctif**
- [ ] `php bin/phpunit` passe en code 0 (base `navinum_test`), et `npm test` si la librairie change
- [ ] Interactifs : aucun appel direct (`bin/check-interactifs.sh`) ; besoin manquant ajouté à `@navinum/api`
- [ ] Librairie modifiée : JSDoc, README, CHANGELOG, bundle `public/build` reconstruit
- [ ] Migration Doctrine si le schéma change (`doctrine:schema:validate` OK)
- [ ] Aucun secret, adresse IP, port ou faille non corrigée dans le code, les commits ou la description (dépôt public)
- [ ] Vérifié en local (et sur le VPS si déployé)
