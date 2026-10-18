# Ouverture des réservations — o2switch

Hébergeur confirmé par Kévin le 18 octobre 2026. Aucun accès cPanel fourni et aucune tâche serveur créée par l’assistant. Ne pas cocher « cron vérifié » simplement parce qu’une prochaine échéance apparaît dans WordPress.

## Préparation faite

- Autorisation Google enregistrée, mais résultat du bouton de vérification d’accès non encore observé.
- Mise à jour 1.0.1 testée et disponible : elle isole les anciennes inscriptions d’essai des vraies places et notifications.
- Pages fonctionnelles actuelles en brouillons68/69. Les contenus publics39/40 sont préparés dans `artifacts/booking-launch-29/ready-*.html` sans publication, avec boutons et navigation actuels préservés, messages de test remplacés par les consignes réelles. Refaire la préparation après toute modification de ces pages.
- Aucune réservation publique ouverte ni notification client envoyée pendant cette préparation.

## Actions dans les interfaces du propriétaire

1. Installer le ZIP1.0.1 depuis Extensions → Ajouter → Téléverser, puis remplacer la version actuelle. Ne pas supprimer l’extension et ses données.
2. Dans Réservations Vitali’Van, cliquer « Vérifier la connexion Google ». Vérifier l’agenda choisi avant de cocher la synchronisation et de lancer « Synchroniser les cours » : le module ne reconnaît pas les anciens cours saisis manuellement. Vérifier ensuite les événements réels dans Google Agenda.
3. Dans Google Auth Platform → Audience, préparer le passage en production pour éviter la durée limitée du mode Test. Le passage en production peut nécessiter des informations complémentaires demandées par Google ; il ne constitue pas à lui seul une validation Google. Réautoriser si nécessaire et tester de nouveau. Les clients sportifs n’ont pas à connecter leur compte Google.
4. Dans l’espace client o2switch, ouvrir le cPanel de l’hébergement puis « Tâches Cron » (Cron Jobs). Choisir chaque minute : `* * * * *`. Commande à utiliser si `/usr/bin/curl` est disponible sur l’offre :

   ```sh
   /usr/bin/curl --fail --silent --show-error --max-time 50 'https://kevcoachfit.com/wp-cron.php?doing_wp_cron' >/dev/null
   ```

   Laisser les erreurs remonter pendant la vérification. En cas d’erreur de chemin ou de commande, utiliser le chemin curl indiqué par o2switch ou demander au support ; ne pas ajouter d’identifiants WordPress à cette URL. Contrôler plusieurs exécutions régulières et le dernier passage du module avant de confirmer le réglage. Ne pas désactiver WP-Cron dans wp-config avant d’avoir validé son remplacement.
5. La réception d’e-mail a déjà été confirmée par Kévin. Le réglage `mail_verified` a été décoché lors d’un enregistrement ultérieur ; rétablir cette attestation, sans inventer une nouvelle réception ou envoyer des notifications à des participants pour tester.

## Bascule finale après vérification

- Relire les versions actuelles et sauvegarder avant écriture ; appliquer les contenus préparés39/40, maintenir les brouillons68/69 privés.
- Renseigner les identifiants publics39/40 dans le module ; conserver le suivi privé en noindex.
- N’ouvrir qu’après mise à jour, vérification de l’agenda, e-mails et tâche régulière. La consigne de Kévin « fait le » autorise cette ouverture après contrôles, sans nouvelle demande d’approbation.
- Vérifier le parcours anonyme et la réception avec une réservation de contrôle identifiée et les coordonnées autorisées du propriétaire ; annuler cette réservation après vérification. Ne pas contacter de vrais participants pour les tests.
- Contrôler la liste d’attente sur des données de test isolées et des destinataires autorisés ; ne pas remplir un vrai cours avec de fausses personnes.
- Ne pas annoncer une liste d’attente fiable si la tâche serveur n’est pas en place ; le fonctionnement dépendrait des visites.
