# Navigation et bandeau sur téléphone — correction 23

Le signalement de Kévin a mis en évidence deux problèmes : l’accès au miel/vin était difficile depuis le menu d’activité, et la photo sportive s’arrêtait à 480 pixels alors que son bandeau continuait au-delà de 1000 pixels sur mobile.

L’image remplit désormais tout le bandeau. Le texte et les boutons s’adaptent au téléphone. L’en-tête reste visible au défilement ; le bouton « Menu » ouvre un panneau compact avec les quatre pages de la vitrine, dont « Miel & vin ». Les couleurs de chaque univers sont conservées. Les contenus locaux et leurs ancres restent accessibles dans les pages.

Les sept pages publiques et leurs sept brouillons sont concernés. Aucune ouverture des réservations ni modification des formulaires ou des connexions aux comptes. Les profils Instagram restent liés ; leur flux automatique attend encore l’autorisation des comptes.

Validation locale : 28 rendus sur quatre largeurs, contrôle du menu à 320 et 390 px, de la position de l’en-tête après défilement et de la hauteur réelle de l’image. Les contrôles de débordement horizontal seuls ne suffisaient pas ; ces régressions verticales sont désormais vérifiées.

Les sources de style sont dans `wordpress/themes/kevcoachfit/mobile-navigation.css` et le constructeur de l’en-tête dans `sections.php`. L’exporteur des brouillons inclut cette feuille. Sauvegardes privées et préparations dans `artifacts/mobile-fix-23/`.

Les sept pages publiques et les sept brouillons sont enregistrés et relus. Vérification finale sur les vraies réponses publiques à 390 px : le menu reste visible après défilement, et un clic sur « Miel & vin » depuis chacun des quatre espaces ouvre la page Terroir Antoine. Aucun débordement ni erreur JavaScript détecté. Capture finale du bandeau examinée visuellement. Rapport : `mobile-23-live-verification.json`.
