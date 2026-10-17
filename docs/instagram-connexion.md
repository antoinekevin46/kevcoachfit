# Publications Instagram — préparation du 17 octobre 2026

Kévin demande l’affichage automatique des publications de son coaching et de Yohan. L’extension gratuite officielle Smash Balloon Social Photo Feed (`instagram-feed`) 6.14.0 était installée mais inactive. Activation effectuée via l’API WordPress. Aucun achat de licence ni abonnement engagé.

Les deux pages publiques continuent de fonctionner et leurs liens de profil sont conservés. Aucun shortcode sans source n’est affiché publiquement : les publications ne sont pas encore intégrées.

## Action du titulaire du compte

Ouvrir https://kevcoachfit.com/wp-admin/admin.php?page=sbi-feed-builder dans la session WordPress habituelle. Lancer la configuration si proposée, puis ajouter/sélectionner une source Instagram. Pour le sport, utiliser `kev_coach_fit`. Valider la connexion dans l’écran officiel Instagram/Meta, pas dans le chat. Si Instagram demande un compte professionnel, le titulaire peut choisir Entreprise ou Créateur ; ne pas convertir le compte d’une autre personne sans son accord.

Créer un flux de publications du compte, nommé « Vitali’Van — Kévin ». Configuration cible : grille simple de six publications, trois colonnes sur ordinateur et deux sur téléphone. Conserver l’ordre des plus récentes. Les paramètres disponibles exacts seront vérifiés dans le constructeur gratuit, sans sélectionner de fonction Pro.

Créer séparément « Maison Lou’Piac — Yohan » avec `yoantoine46`. Si le compte n’est pas déjà une source autorisée, Yohan ou une personne autorisée à le gérer doit valider sa connexion. Aucun mot de passe ni jeton ne doit être partagé dans le chat.

## Intégration à effectuer ensuite

Le constructeur donne le vrai shortcode de chaque flux (`[instagram-feed feed="…"]`). Ne pas inventer ni présumer les numéros 1 ou 2. Les insérer dans les sections `kcf20-social-follow` existantes des pages publiques Vitali’Van 36 et Terroir 37, en conservant le bouton de profil comme solution de repli. Reporter la même intégration dans les brouillons 64 et 66 pour ne pas la perdre à une prochaine mise à jour.

Avant modification, relire et sauvegarder les pages actuelles. Vérifier le bon compte dans chaque flux, les liens vers les vraies publications, le rafraîchissement, les versions mobile et ordinateur et le comportement sans connexion Instagram chez le visiteur. Ne pas présenter ce travail comme terminé tant qu’aucune publication réelle n’a été affichée.

Les outils disponibles utilisent l’API REST WordPress avec un mot de passe d’application. Le plugin gère les sources et flux depuis des actions de l’administration avec session et nonce ; il n’expose pas d’API REST de configuration des flux. L’autorisation Instagram appartient au titulaire et ne peut pas être faite par l’assistant avec son seul accès WordPress. Aucun contournement d’authentification ni collecte de mot de passe.

Affichage des publications sur le site uniquement : aucune publication automatique du site vers Instagram ou Facebook n’est activée.
