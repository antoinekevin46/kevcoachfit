# Maquette 20 dans WordPress — 17 octobre 2026

Transfert demandé par Kévin : sept nouveaux brouillons, sans remplacer les pages existantes ni changer le thème public. Les médias sont copiés dans la médiathèque ; l’apparence de cette version est intégrée uniquement aux nouveaux brouillons.

## Voir et modifier

1. Se connecter à https://kevcoachfit.com/wp-admin/ dans le navigateur.
2. Ouvrir l’un des liens « Modifier » ci-dessous, puis utiliser « Prévisualiser » / « Aperçu dans un nouvel onglet » pour regarder la page.
3. Modifier les textes, tableaux ou images directement dans les blocs WordPress. Choisir « Enregistrer le brouillon » pour conserver les changements. Ne pas cliquer sur « Publier » tant que le lancement n’est pas souhaité.
4. Les menus de ces maquettes pointent vers les autres nouveaux brouillons. Une session WordPress autorisée est nécessaire pour les consulter ; un ami non connecté ne dispose pas d’un aperçu partageable.

| Page | Modifier | Aperçu connecté |
|---|---|---|
| Accueil | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=63&action=edit) | [Voir](https://kevcoachfit.com/?page_id=63&preview=true) |
| Vitali’Van | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=64&action=edit) | [Voir](https://kevcoachfit.com/?page_id=64&preview=true) |
| A.K Nuisibles | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=65&action=edit) | [Voir](https://kevcoachfit.com/?page_id=65&preview=true) |
| Terroir Antoine | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=66&action=edit) | [Voir](https://kevcoachfit.com/?page_id=66&preview=true) |
| Contact | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=67&action=edit) | [Voir](https://kevcoachfit.com/?page_id=67&preview=true) |
| Réserver — démo | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=68&action=edit) | [Voir](https://kevcoachfit.com/?page_id=68&preview=true) |
| Mon inscription — démo | [Modifier](https://kevcoachfit.com/wp-admin/post.php?post=69&action=edit) | [Voir](https://kevcoachfit.com/?page_id=69&preview=true) |

## Mise en forme et limites

Les textes, images, tableaux et questions fréquentes restent des blocs Gutenberg. Les cartes et formulaires de démonstration sont des blocs HTML. Conserver les blocs HTML techniques au début et à la fin de la page : ils contiennent le style et les effets propres à cet aperçu. Ils évitent de mettre à jour le thème public 0.11.1 avant la revue. Les classes de cette version ont un préfixe distinct pour éviter les interactions avec les anciens scripts.

Les formulaires ne transmettent aucune demande et n’activent ni réservation publique ni paiement. Google Agenda, e-mails/SMS, calcul des trajets et notifications restent à connecter. Les tableaux affichent les horaires et tarifs validés. A.K reste présenté comme en préparation.

Avant toute modification automatique ultérieure : relire et sauvegarder les brouillons sur WordPress, puis conserver les changements de Kévin. Ne pas réimporter les sources en écrasant ses modifications. Le script d’import refuse d’écraser un brouillon terminé dont le contenu diffère.

## Reproduction et sauvegarde

Scripts `export-draft-fragments.php`, `wordpress-draft-transfer.py`, `check-portable-drafts.py` et `verify-wordpress-draft-transfer.py`. L’import distant nécessite une autorisation explicite comme celle donnée pour ce transfert. Les sauvegardes avant intervention et le manifeste de reprise sont dans `artifacts/wordpress-import-20/`, ignoré par Git ; aucun identifiant ni mot de passe n’y est sauvegardé par l’import. Les sauvegardes locales de pages contiennent des contenus privés et ne doivent pas être rendues publiques.

## Vérification finale

Sept brouillons authentifiés vérifiés, avec contenu conservé après normalisation des espaces dans les attributs JSON de Gutenberg. Les aperçus anonymes répondent 404. Les 24 pages antérieures, leurs contenus source, statuts et dates de modification sont inchangés. Réglages du site, versions et activation du thème et des extensions identiques aux relevés avant transfert. Treize médias copiés. Les formulaires ne communiquent pas avec les anciens endpoints actifs.

Contrôles locaux dans WordPress : 28 rendus, aucune erreur JavaScript, menus, cartes et absence de débordement horizontal. Dans l’éditeur, 29 titres, 8 images et 2 tableaux natifs sur Vitali’Van, sans bloc invalide. Les captures A.K et Vitali’Van à 390 px ont été inspectées. Le HTML rendu par l’API distante et les refus d’accès anonyme ont été vérifiés ; une session de navigateur administrateur distante n’est pas disponible pour capturer directement le lien d’aperçu connecté. Kévin peut le consulter avec sa session WordPress.

Deux corrections ont été nécessaires pendant la préparation locale : permissions de lecture des fixtures Docker, puis transport du JavaScript pour éviter que WordPress ne transforme les esperluettes en entités HTML. La vérification de l’éditeur attend son chargement plutôt qu’un réseau totalement inactif. Aucun de ces essais n’a modifié les anciennes pages distantes.
