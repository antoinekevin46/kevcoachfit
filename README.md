# KevCoachFit — version de test 16

Un WordPress, trois espaces distincts : Vitali’Van, A.K Nuisibles et Terroir Antoine. Aucun domaine supplémentaire nécessaire pour cet essai. Sources restaurées à partir du pack 15 fourni par Kévin, puis mises à jour avec les tarifs explicitement validés.

## Développer et vérifier

Prérequis : Docker avec Compose, Python 3, Playwright Python et Chromium à `/usr/bin/chromium`. Utiliser le checkout existant ; ne pas créer de worktree pour cette procédure.

Depuis `/workspace/kevcoachfit` :

```sh
./scripts/bootstrap.sh
python3 scripts/build-package.py
docker compose run --rm cli wp eval-file /project/scripts/test-design-16.php
docker compose run --rm cli wp eval-file /project/scripts/test-pack.php
python3 scripts/test-seo.py
python3 scripts/build-showcase.py
python3 scripts/check-showcase.py
```

Le bootstrap crée les mots de passe locaux aléatoires dans `.env` si nécessaire, démarre les deux services et prépare sept brouillons privés. Il est réexécutable. Ne pas afficher ni partager `.env`. Les images Docker sont épinglées par empreinte. La validation est locale ; aucun de ces scripts ne modifie kevcoachfit.com. Le port est limité à la boucle locale pour les contrôles internes ; les fichiers de vitrine constituent les livrables à consulter.

Les scripts de tests travaillent dans cet atelier local. Le contrôle HTTP SEO publie temporairement les brouillons locaux et restaure ensuite leurs états. Le contrôle du pack utilise un dossier de thèmes temporaire. Aucune donnée réelle de client ne doit être utilisée pour ces essais.

## Sources et livrables

- `wordpress/themes/kevcoachfit/` : thème de blocs, identités et navigation par activité, effets et métadonnées.
- `wordpress/plugins/kevcoachfit-workflows/` : atelier de démonstration ; pages source dans `starter-pages/`.
- `scripts/build-package.py` : produit le ZIP installable `artifacts/kevcoachfit-pack-16.zip` et son thème inclus. Les fichiers de démonstration générés sont ignorés par Git.
- `scripts/build-showcase.py` : produit `artifacts/kevcoachfit-vitrine-16.html`, avec ressources intégrées et formulaires sans envoi.
- `docs/installation-16.md` : revue et installation ultérieure ; les nouveaux brouillons conservent les précédents.
- `docs/tarifs-valides.md` : tarifs par période confirmés.
- `docs/fiche-google-coaching.md` : description et observations sur les fiches suspendues, sans démarche Google effectuée.

## Limites

Les réservations publiques, Google Agenda, les trajets, l’envoi des e-mails et SMS et le traitement planifié de la liste d’attente restent à terminer. Abby n’est pas connecté. Le terroir conserve deux vendeurs et des prix produits partiellement provisoires. A.K reste une activité en préparation jusqu’à confirmation. Pas d’achat ni de configuration DNS, et pas d’accès WordPress distant utilisé pour écrire.

Git conserve les sources et les visuels nécessaires à la reconstruction ; il ne remplace pas une sauvegarde du WordPress de production avec sa base de données. Les données locales, mots de passe et archives de livraison sont exclus du dépôt. Conserver également les ZIP livrés et les sauvegardes de l’hébergeur.
