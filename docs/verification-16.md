# Vérification de la version 16

## Sources

Pack 15 fourni de nouveau par Kévin : archive intacte, ZIP du thème intact et SHA256 conforme au manifeste 0.11.1. Sources extraites en conservant les visuels existants. Version 16 du design, thème et module 0.12.0.

## Résultats

- `scripts/test-design-16.php` : 78 assertions exécutées avec succès dans WordPress 6.8.3, PHP 8.3 et MariaDB. Migration 15 → 16, archivage et conservation des modifications, sept nouveaux brouillons, répétition sans doublon, préservation des réglages publics, tarifs, quatre programmes, navigation par activité, contacts et URL d’aperçu.
- `scripts/test-pack.php` : installateur WordPress réel exercé dans un dossier de thèmes temporaire ; installation, mise à jour depuis 0.11.1, répétition, droits administrateur, présence des nouveaux fichiers et visuels, conservation des fichiers tiers et des réglages publics.
- `scripts/test-seo.py` : 52 contrôles réussis sur les réponses HTTP locales. Un H1, titre et description uniques, canonique native, données structurées cohérentes, pas de rue privée ni faux avis, mentions de préparation A.K et exclusion des parcours d’essai. États temporaires restaurés après contrôle.
- `scripts/check-showcase.py` : 28 rendus réussis (7 pages × 320, 390, 768 et 1440 px), images chargées, titres, marques et tarifs contrôlés. Quatre pages vérifiées à 320 px avec texte à 200 %. Menu mobile et navigation vers A.K testés. Aucune requête réseau déclenchée depuis la vitrine et aucune erreur JavaScript relevée. Rapport JSON et captures dans ce dossier.
- Intégrité des deux ZIP produits vérifiée avec ZipFile.testzip. Manifeste du thème inclus reconstruit avec son empreinte SHA256.
- Syntaxe PHP vérifiée dans l’image CLI PHP 8.3.

## Corrections et limites des contrôles

Pendant la remise en place locale, les droits des fichiers restaurés ont été corrigés pour permettre leur lecture par WordPress. Le lancement CLI a été adapté à l’entrypoint de l’image officielle. Le premier comptage des assertions était local au chargeur WP-CLI : corrigé, puis 78 assertions effectivement comptées. L’export initial retirait trop de contenu avec la barre d’administration : remplacé par sa désactivation pour le compte local et un contrôle explicite. Les balises header/footer des blocs remplacés ont été rétablies. Un débordement de texte dans la signature du pied de page à 200 % a été reproduit et corrigé, puis les rendus ont été revérifiés.

Pas de test sur téléphone physique ou Safari. Aucun changement distant sur kevcoachfit.com, aucun achat ou réglage DNS, aucun message ni suppression Google effectué. Les opérations Google décrites dans la conversation restent celles effectuées ou à effectuer par Kévin. Le moteur de réservation préexistant n’a pas été modifié, hors texte de périodicité dans le formulaire. Les connecteurs Google Agenda, e-mails, SMS, trajets et automatisation planifiée de l’attente restent absents.
