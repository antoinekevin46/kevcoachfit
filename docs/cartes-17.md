# Cartes des secteurs — version 17

## Deux prestations sportives distinctes

- Cours actuels : Calès (seniors lundi 17 h, Renfort jeudi 18 h), Payrac (seniors mardi 17 h 30, Training mardi 18 h 30, Renfort jeudi 19 h 05).
- Cours à venir : seniors à Saint-Projet mercredi 17 h 45 à partir de janvier 2027 ; lancement et modalités à confirmer, pas de réservations actives.
- Coaching privé : environ 15 minutes autour de Loupiac 46350, adresse et trajet à confirmer avant rendez-vous.

Sur la carte Vitali’Van, boutons séparés « Cours en salle » et « Coaching à domicile ». Les points des salles sont des centres communaux : ne pas les présenter comme des géolocalisations exactes de l’entrée. Aucune adresse privée de départ des trajets affichée.

La carte A.K Nuisibles est indépendante : point de référence Payrac, environ 20 minutes annoncées dans le flyer, communes repères Loupiac, Souillac et Gourdon ; activité en préparation. Elle n’affiche pas de cours.

## Données et limites

20 communes du Lot : contours et centres récupérés par HTTPS auprès de l’API Découpage administratif. Codes INSEE contrôlés pour éviter les homonymes de Calès et Loupiac. Métadonnées de provenance et SHA256 des réponses dans `wordpress/themes/kevcoachfit/data/communes-lot.json`. Contours simplifiés pour limiter le poids ; le script de préparation est conservé.

Affichage local en SVG, avec noms des communes, sélection par liste et par point, zoom, recentrage, déplacement souris, pincement à deux doigts en mode « Déplacer » et commandes clavier. Les étiquettes évitent les chevauchements : les points et la liste permettent de choisir les petites communes même si leur nom est temporairement masqué faute de place. Le défilement de page tactile reste disponible par défaut.

Cette carte administrative ne calcule pas d’itinéraire, de trafic ni d’isochrone : aucun cercle ou contour n’est présenté comme une limite exacte de 15 ou 20 minutes. Une commune voisine sur la carte n’est pas automatiquement une zone de coaching validée. Aucun compte Google Maps, clé API, suivi de position ou téléchargement de tuiles nécessaire pour utiliser la maquette.

## Vérifications

- 88 assertions WordPress réussies : migration 16 → 17, conservation des anciens brouillons et des modifications, tarifs et programmes, profils des cartes, 20 marqueurs et codes INSEE, séparation cours/coaching/anti-nuisibles, statut futur de Saint-Projet.
- Installation du thème inclus et mise à jour réelles testées dans un répertoire temporaire ; fichiers des cartes et données inclus, réglages publics préservés.
- 28 rendus de la vitrine vérifiés sur 7 pages et 4 largeurs, dont 320 px ; texte agrandi à 200 % sur quatre pages. Images et tarifs conservés.
- 6 rendus spécifiques des cartes à 320, 390 et 1440 px : sélection, zoom, réinitialisation, vues distinctes, clavier, déplacement souris et pincement tactile simulé dans Chromium. Aucune requête réseau ni erreur JavaScript lors de l’utilisation de la vitrine. Pas de test sur appareil physique ou Safari.
- La synchronisation du chargement des pages de la vitrine a été renforcée pour éviter un contrôle ou une action pendant le remplacement d’une page.
- Syntaxe PHP vérifiée. Aucun changement des connecteurs absents ni des règles du moteur de réservation.

## Livraison et publication

Vitrine : `artifacts/kevcoachfit-vitrine-17.html`. Pack installable ultérieurement : `artifacts/kevcoachfit-pack-17.zip`. Guide : `docs/installation-17.md`.

Travail effectué ici uniquement, sans installation sur kevcoachfit.com, modification de la fiche Google ou configuration de domaine. L’essai ne demande aucun achat. Les limites de réservation, notifications, Google Agenda et trajets indiquées dans le guide restent applicables.
