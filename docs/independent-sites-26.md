# Préparation des trois sites par domaine

Mise à jour après cette préparation : la phase27 consacre désormais l’accueil public au sport et sépare les menus des activités. Les domaines supplémentaires attendent toujours leur choix et leur configuration ; voir `specialisation-27.md`.

Décision de Kévin du 17 octobre 2026 : appliquer à toutes les activités l’organisation par domaines dédiés, en conservant pour l’instant les essais et sans acheter de domaines.

| Futur site | Domaine | Contenu |
|---|---|---|
| Vitali’Van / KevCoachFit | kevcoachfit.com, déjà existant | Programmes, cours, coaching, tarifs et réservation sport |
| A.K Nuisibles | À choisir ; aknuisible.com est une idée, disponibilité non vérifiée | Prestations nuisibles, essaims, secteur et devis |
| Kev’Miel et Maison Lou’Piac | À choisir | Deux catalogues et deux vendeurs, retrait sur rendez-vous |

Trois aperçus privés WordPress montrent les parcours séparés. Chaque menu reste dans son activité ; les liens du pied de page vers les autres univers sont retirés. Les coordonnées, prix validés et contenus réels sont conservés. Les images fictives, exemples de produits et état de préparation restent indiqués. Les nouvelles pages restent en brouillon : aucun nouveau domaine n’est en service.

La vitrine publique actuelle conserve les quatre pages et le raccourci RDV pour les essais. Les formulaires opérationnels, Google Agenda et les notifications ne sont pas davantage activés par la préparation de ces sites.

## Déploiement futur

La préférence est de conserver un seul abonnement d’hébergement et d’y créer trois installations WordPress distinctes, sous réserve des possibilités réelles de l’offre : domaines, répertoires, bases et HTTPS. Le prestataire exact reste à confirmer. Les domaines A.K et miel/vin ne doivent pas seulement rediriger vers kevcoachfit.com : leurs contenus devront être servis sous leurs propres URL.

Importer les contenus et les médias dans la bonne installation, conserver la séparation des vendeurs et ne pas recopier les données de réservation des clients vers les autres activités. Configurer les domaines, certificats et adresses WordPress après confirmation de leur propriété. Préparer puis vérifier les nouvelles destinations avant les redirections et l’indexation.

Le plan `domain-migration-plan.json` conserve les titres, descriptions, sources et correspondances des anciennes URL. Les redirections y sont toutes inactives. Le domaine sport continuera d’exister : la migration des deux autres sections ne constitue pas un changement d’adresse du domaine entier dans Search Console. Chaque site devra avoir son plan de site et sa propriété Search Console. Les URL des fiches Google et réseaux seront mises à jour après lancement.

Aucun achat ni changement d’hébergement effectué. Les noms ne garantissent pas un classement dans Google. L’activité A.K et les ventes restent soumises à leur vraie date d’ouverture et aux informations encore à compléter.

## Validation

Douze rendus locaux des trois aperçus à 320, 390, 768 et 1440 px ; menus, liens locaux, images et blocs éditables. Création de nouveaux brouillons uniquement, vérification de leur statut et du contenu sauvegardé. Les pages sources publiques et le choix de la page d’accueil sont relus pour confirmer leur conservation. Les IDs et liens de prévisualisation sont dans `independent-sites-26-previews.json` ; les aperçus nécessitent une session WordPress du propriétaire.
