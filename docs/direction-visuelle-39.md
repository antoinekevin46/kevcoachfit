# Direction visuelle — références de Kévin, 18 octobre 2026

Kévin fournit Ponpon Mania, Sun Metalon, Ribbit, Symphony of Vines, Microsoft AI et Terminal Industries. Il précise qu’il souhaite s’inspirer des couleurs et apparitions, sans reproduire exactement ces sites.

## Consultation et limites

Les six URL ont répondu en HTTPS avec les vérifications TLS normales, et leurs pages HTML ont été lues. L’affichage direct de Chromium rencontrait un problème d’autorité de certificat dans cet environnement ; les essais visuels ont ensuite utilisé un relais de réponses téléchargées par urllib avec TLS vérifié, sans désactiver les contrôles.

Ponpon Mania : visuel effectivement examiné, forts aplats rose/orange, formes amples et composition autour d’un visuel central. Ribbit : visuel effectivement examiné, personnage coloré, typographie marquée et espace autour du sujet. La version de Sun Metalon rendue par l’outil a affiché une page404, malgré une réponse initiale200 et du contenu de page d’accueil dans le HTML : ne pas prétendre avoir étudié visuellement tout son accueil. Les captures Microsoft AI et Terminal Industries sont restées partielles ou sur une introduction ; ne pas leur attribuer une observation détaillée du déroulé. Symphony of Vines : HTML/canvas lus mais capture expirée, animation complète non vérifiée. Les captures et résultats bruts restent dans `artifacts/design-references-39/`.

Références :

- https://ponpon-mania.com/fr/chapters
- https://sunmetalon.com/
- https://ribbit.dk/
- https://symphonyofvines.unseen.co/
- https://microsoft.ai/
- https://terminal-industries.com/

## Déclinaison retenue

- Vitali’Van : couleurs franches par programme, photos de cours bien visibles, fonds clairs et légères nappes de couleur, titres lisibles et cartes qui apparaissent une fois au défilement.
- A.K Nuisibles : identité jaune/noir du bandeau, corps de page crème et blanc, étapes avec numéros en pastilles, photos d’ambiance naturelles identifiées comme illustrations.
- Terroir : or et crème pour le miel, bordeaux pour les vins, visuel apicole et bloc éditorial mettant en avant les deux producteurs et le retrait.
- Contact, Réserver, Mon inscription : même famille de formes et de typographie, parcours pratiques lisibles ; pas de nouveau flux Instagram ni d’ouverture des réservations annoncés par le design.

## Mouvement implémenté

Apparition courte des cartes via IntersectionObserver, une seule fois à leur entrée dans l’écran. Le contenu reste visible sans JavaScript : il n’existe pas d’état initial globalement caché. Effet de rapprochement léger des photos au survol sur ordinateur. Aucun défilement capturé, aucune vidéo sonore imposée, aucune dépendance distante ajoutée. Les préférences de réduction des animations sont respectées.

Le mouvement du site n’est pas une génération de vidéo avec des personnes en mouvement : cette demande reste distincte et limitée par les outils de la session. Les vrais flux Instagram/Facebook et l’ouverture des réservations restent à finaliser selon les notes de configuration.
