# Réservations et Google Agenda — préparation sans licence payante

Décision de Kévin du 17 octobre 2026 : aucune licence payante. Aucun achat, aucune facturation ni abonnement engagé.

## Situation sur le WordPress distant

Le thème et le module atelier 0.11.1 restent actifs. Les sept maquettes 20 restent en brouillon. Amelia 2.4.11 est installé mais son interface authentifiée ne retourne encore aucun service, intervenant ni événement. Son interface MCP a été testée en lecture seule : aucune réservation ni fiche client créée et aucune notification envoyée.

L’édition gratuite d’Amelia présente dans le dépôt officiel fournit une implémentation Google de type Starter sans synchronisation effective. Sa liste d’attente ne constitue pas la proposition FIFO individuelle de deux heures demandée. Aucune licence n’a été contournée ou modifiée.

Le module indépendant **KevCoachFit — réservations et agenda 1.0.0** est maintenant installé et actif sur le WordPress distant (vérification du 17 octobre 2026). Il reste en mode privé. Les formulaires fonctionnels sont reliés aux brouillons Réserver (68) et Mon inscription (69), et leur rendu serveur a été vérifié. Trente séances sont préparées. Google Agenda reste déconnecté et la réception de l’e-mail de test est confirmée par Kévin ; la régularité de la tâche serveur reste à vérifier. Son ZIP installable est déposé dans la médiathèque WordPress (pièce jointe 81) :

https://kevcoachfit.com/wp-content/uploads/2026/10/kevcoachfit-reservations-1.0.0-8e045b4390a8.zip

Copie conservée dans `docs/kevcoachfit-reservations-1.0.0.zip`. L’archive n’installe aucun thème et ne remplace ni Amelia ni l’atelier existant. Aucun identifiant, secret Google ou état local inclus.

## Installation nécessaire — une seule extension

1. Télécharger le ZIP ci-dessus sans le décompresser.
2. Dans WordPress : **Extensions → Ajouter une extension → Téléverser une extension**.
3. Choisir ce ZIP, puis **Installer maintenant → Activer**.
4. Le menu **Réservations Vitali’Van** apparaît. Le mode initial reste privé : les visiteurs ne peuvent pas réserver et aucune notification client réelle n’est envoyée.

L’API WordPress actuelle permet la modification des pages et le téléversement de médias, mais elle n’expose pas l’installation d’un ZIP personnalisé. Le compte a les droits administrateur ; c’est la voie d’installation disponible qui impose ce passage par l’écran WordPress. Pas besoin de partager un mot de passe dans le chat.

## Ce qui est implémenté

- Tables séparées de l’ancienne démonstration et de celles d’Amelia.
- Les cinq cours validés, chacun à 15 places, sur les six prochaines semaines (fenêtre prolongée automatiquement par le traitement quotidien), sans générer au-delà du 20 décembre 2026 avant précision des vacances de fin d’année. Juillet et août sont exclus ; Saint-Projet n’est pas généré tant que ses modalités restent à confirmer.
- Réservation à chaque séance jusqu’au début du cours, annulation d’une place réservée jusqu’à deux heures avant.
- Liste FIFO : place proposée à la personne suivante, pour deux heures au maximum et dans la limite du début du cours ; confirmation nécessaire. Les personnes en attente peuvent se retirer sans être bloquées par la limite d’annulation des places déjà réservées.
- Liens de gestion aléatoires privés, empreintes pour vérification et copie chiffrée pour les notifications futures.
- File d’envoi des e-mails avec tentatives, suppression des notifications périmées et protection contre deux travailleurs concurrents. Conseils pour la première séance, invitation à appeler Kévin, tapis et eau. Aucun paiement en ligne.
- Réglage des capacités dans WordPress, sans réduction sous les places prises ou proposées.
- Demandes de coaching sur 60 minutes, horaires déjà validés, refus d’un chevauchement direct avec les cours et d’un créneau occupé dans Google une fois connecté. Les trajets et la confirmation finale restent manuels. Une indisponibilité de Google n’est jamais assimilée à une disponibilité certaine.
- Client Google OAuth avec état à usage unique lié à l’administrateur, stockage chiffré du secret et des jetons, renouvellement du jeton, contrôle des périodes occupées et création/mise à jour des cours avec identifiant stable. Les noms et e-mails des participants ne sont pas envoyés dans les événements Google.
- Routes de configuration et de diagnostic réservées à l’administrateur, utilisables avec la connexion WordPress existante après installation.

## Connexion Google — après installation

La connexion doit être autorisée avec le compte Google de Kévin. Elle n’est pas encore effectuée. Le projet `vitalivanreservations`, Calendar API et le client OAuth Web ont été créés par Kévin ; la saisie des paramètres dans WordPress et le consentement sont en attente.

1. Dans Google Cloud, utiliser un projet personnel, activer **Google Calendar API**, configurer l’écran de consentement OAuth et créer un client **Application Web**.
2. Ajouter exactement l’URI de redirection affichée dans le menu WordPress. Sur le domaine actuel : `https://kevcoachfit.com/wp-admin/admin-post.php?action=kcfr_google_callback`.
3. Renseigner le Client ID et le Client secret **dans WordPress**, jamais dans le chat. Le secret est conservé chiffré et n’est pas réaffiché.
4. Choisir l’agenda où inscrire les cours (`primary` signifie l’agenda principal) et les identifiants des agendas dont il faut vérifier les occupations. Un agenda distinct pour les cours peut faciliter la lecture ; les agendas personnel et professionnel peuvent être contrôlés ensemble.
5. Enregistrer, puis utiliser **Autoriser Google Agenda** et valider la demande Google. Utiliser **Vérifier la connexion Google**, puis **Synchroniser les cours** lorsque les réglages ont été revus.

Les deux permissions demandées concernent les événements et les périodes occupées ; le module n’accède pas à Gmail. Il ne sait pas reconnaître des cours déjà saisis manuellement dans Google : vérifier le choix de l’agenda avant la première synchronisation. Les synchronisations répétées du module réutilisent leurs propres identifiants et ne créent pas de doublons.

En mode Test d’une application Google externe, les jetons peuvent expirer après sept jours. La configuration du consentement doit être adaptée à l’utilisation quotidienne avant de considérer le lien comme durable. Aucun compte ou abonnement Google payant n’a été créé par Codex.

## Vérification avant ouverture réelle

L’e-mail de test a été reçu et confirmé par Kévin le 17 octobre ; le réglage de réception vérifiée est enregistré. La réussite de `wp_mail` signifie que WordPress a accepté l’envoi, pas que le destinataire l’a reçu. Si nécessaire, configurer le transport e-mail de l’hébergeur avant ouverture.

Configurer un appel régulier de WP-Cron chez l’hébergeur, idéalement chaque minute. Sans tâche serveur, les traitements dépendent des visites. Le menu affiche la prochaine échéance et le dernier passage. Vérifier les refus et expirations sur le vrai hébergement ; les tests effectués ici sont locaux.

L’ouverture réelle reste bloquée tant que les e-mails et la tâche planifiée n’ont pas été confirmés et que les deux pages fonctionnelles ne sont pas publiées. Aucune ouverture publique effectuée pendant cette préparation. La liaison des formulaires opérationnels aux brouillons 68 et 69 a été effectuée avec `bind-reservation-drafts.py`, après sauvegarde et relecture des contenus distants. Les shortcodes sont présents et leur rendu fonctionnel est vérifié. Les pages restent en brouillon et les réservations publiques restent fermées. Les sauvegardes et le rapport de vérification sont conservés dans `artifacts/reservations-binding/`.

Les SMS ne sont pas implémentés : il faudrait un prestataire, un compte et généralement des crédits. Le calcul automatique des trajets n’est pas implémenté. Les interventions A.K gardent leur prise de contact sur devis et leur ouverture à confirmer après formation ; aucune durée d’intervention inventée.

## Résultats de tests

80 assertions locales sur les places, doublons, liste FIFO, expirations, annulations, été et jours fermés, horaires du coaching, chiffrement, confidentialité, file d’e-mails, OAuth, erreurs Google, passage à l’heure d’hiver et absence de doublons de synchronisation. Les envois e-mail et les appels Google sont interceptés dans ces tests ; ce n’est pas une connexion réelle au compte de Kévin.

Contrôles navigateur à 320, 390, 768 et 1440 px, vraie réservation et annulation HTTP en mode privé. Vingt demandes de réservation, quatre en parallèle : quinze places réservées, cinq en attente. Pas d’erreur JavaScript ni débordement horizontal. Modification de capacité 15 → 16 → 15 testée depuis l’administration. Les données d’essai sont retirées après les contrôles.

Les trois corrections du moteur atelier existant sont aussi conservées dans les sources, sans mise à jour automatique de l’extension distante : promotion à moins de deux heures du cours, retrait de la liste d’attente et comparaison d’heure qui acceptait à tort une séance de 23 h 30 franchissant minuit.

Scripts : `test-reservations.php`, `check-reservations-ui.py`, `test-workflow-regressions.php`, `build-reservations-package.py`. Sources officielles consultées : https://wordpress.org/plugins/ameliabooking/ ; https://developers.google.com/identity/protocols/oauth2/web-server ; https://developers.google.com/calendar/api/v3/reference/freebusy/query .
