# Version 18 — Vitali’Van, première visite et photos

## Ajouts

Quatre cartes de choix dirigent vers les quatre programmes détaillés, conservés intégralement. Le planning graphique approuvé est copié sans retouche et s’agrandit dans la visionneuse existante, avec le tableau texte maintenu à côté ou dessous sur mobile. Présentation de Kévin fondée sur son BPJEPS et son retour de Bordeaux à Loupiac, sans expérience ou qualification ajoutée.

Cinq emplacements photo explicites : portrait de Kévin et deux vues pour chacune des salles de Calès et Payrac. Coordonnées précises, stationnement et accessibilité restent à vérifier. Huit questions fréquentes natives, utilisables au clavier, précisent le premier cours, les formules, le financement seniors, les places et les limites du système de réservation en préparation. Espace de témoignages sans avis, noms ou notes inventés. Aucun schéma FAQPage ou AggregateRating ajouté.

## Validation

- 98 assertions WordPress : migration 17 → 18 et conservation des brouillons, tarifs, programmes, cartes, nouveaux contenus, huit FAQ et cinq espaces photo. Les quatre blocs de programmes détaillés sont comparés intégralement à la version 17 après normalisation des URL d’assets par l’installateur. La première comparaison brute signalait cette seule différence de chemin d’images ; aucune offre n’avait été modifiée. Une fixture source permet de reproduire ce contrôle sans dépendre d’une archive générée.
- Installation et mise à jour du thème inclus testées dans un répertoire temporaire, avec présence du planning PNG, des cartes et des originaux. Réglages publics et fichiers tiers préservés.
- 28 rendus de vitrine (7 pages × 4 largeurs) réussis, avec texte agrandi sur quatre pages, images, tarifs et navigation contrôlés ; pas de requête réseau ni erreur JavaScript.
- Contrôle dédié Vitali’Van à 320, 390, 768 et 1440 px : quatre choix de cours, quatre programmes détaillés, cinq emplacements photo, huit FAQ, liens d’ancrage, agrandissement et zoom du planning, fermeture Échap et ouverture de FAQ au clavier. Tous les panneaux ouverts à 320 px et texte à 200 % : pas de débordement horizontal.
- Les captures de composants sont réalisées hors du conteneur iframe, avec le même document et les mêmes ressources, pour éviter les zones noires dues au recadrage de captures d’éléments plus hauts que l’iframe ; le positionnement fixe du sommaire est désactivé uniquement pour ces captures longues. Les interactions restent testées dans la vitrine réelle.
- 52 contrôles SEO HTTP passent localement et les états des pages sont restaurés. Six contrôles de rendu et d’interaction des cartes passent également avec la nouvelle mise en page. Aucun changement de structure SEO externe ou de connecteur.

Les captures Chromium ne remplacent pas un test sur téléphone physique ou Safari. Aucune modification de kevcoachfit.com et aucune publication ou communication externe effectuée. Les réservations publiques, Google Agenda, e-mails/SMS, trajets et traitement planifié de l’attente restent à terminer.

## Livraison

`artifacts/kevcoachfit-vitrine-18.html`, `artifacts/kevcoachfit-pack-18.zip` et `docs/photos-a-faire-vitalivan.md`. Le planning source est conservé dans `wordpress/themes/kevcoachfit/assets/vitalivan-planning-cours.png` ; les images originales générées hors dépôt ne sont pas supprimées.
