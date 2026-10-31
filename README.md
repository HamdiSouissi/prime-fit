# Prime Fit

Socle MVC PHP natif pour la gestion d'une salle de sport en Tunisie. **Première étape livrée : connexion, déconnexion, journal de connexion, tableau de bord MySQL, gestion de base des membres et abonnements, encaissements partiels, caisse journalière, pointage par carte, gestion des cours collectifs, séances privées des coachs, suivi sportif de base, boutique simple et inventaire des équipements.** Les autres rubriques du menu sont indicatives et ne sont pas encore implémentées. Ce projet ne constitue pas encore une application complète de gestion de salle.

## Prérequis

PHP 8.0+ avec extensions PDO MySQL et mbstring, MySQL 8 ou MariaDB compatible, Apache (XAMPP/WAMP) ou hébergement PHP. Chart.js, Lucide et les polices sont chargés depuis des CDN et nécessitent Internet. L'application utilise le fuseau Africa/Tunis et affiche les montants en DT à trois décimales.

## Installation

1. Copier le projet dans `htdocs/prime-fit` (XAMPP) ou `www/prime-fit` (WAMP). Sur un hébergement mutualisé, placer le projet hors du dossier public et configurer le document root sur `public/` lorsque possible. Ne pas exposer `app/` ni `database/` sur Internet.
2. Créer la base et les tables en important `database/schema.sql` dans phpMyAdmin ou avec `mysql -u root -p < database/schema.sql`. Si l'hébergeur interdit `CREATE DATABASE`, créer la base via son panneau et retirer les lignes `CREATE DATABASE` et `USE` avant import.
3. Sur une installation existante antérieure au module cours, importer `database/courses.sql` une seule fois ; une nouvelle installation utilisant le schéma actuel contient déjà ces tables.
4. Sur une installation existante antérieure au module coach, importer également `database/coaches.sql` ; les nouvelles installations disposent déjà de ces tables.
5. Sur une installation existante antérieure au suivi sportif, importer `database/fitness.sql` ; les nouvelles installations disposent déjà de ces tables.
6. Sur une installation existante antérieure à la boutique, importer `database/shop.sql` ; les nouvelles installations disposent déjà de ces tables.
7. Sur une installation existante antérieure aux équipements, importer `database/equipment.sql` ; les nouvelles installations disposent déjà de ces tables.
8. Pour tester, importer une seule fois `database/demo.sql` sur une base vierge. Les dates du jeu de démonstration sont relatives au jour d'import.
9. Copier `app/config/local.example.php` vers `app/config/local.php` et adapter les identifiants. Ce fichier est ignoré par Git.
10. Démarrer Apache et MySQL et ouvrir `http://localhost/prime-fit/public/` ou, si le document root pointe sur `public/`, l'URL du site.
11. Compte de démonstration : `admin@primefit.tn` / `PrimeFit2026!`. **Ne jamais utiliser ce compte en production** : supprimer le jeu de démo et créer un administrateur avec un mot de passe robuste et `password_hash()` avant mise en ligne.

Pour un essai local : `php -S 0.0.0.0:8000 -t public` (après configuration de MySQL).

## Arborescence

```text
app/
  config/        config.php, local.example.php
  core/          bootstrap.php (session, PDO, CSRF, helpers)
  controllers/   AuthController.php, DashboardController.php
  models/        Dashboard.php
  views/         login.php, dashboard.php
public/
  index.php      point d'entrée
  assets/        style.css, app.js
database/        schema.sql, demo.sql
```

## Fonctionnement et limites

Le tableau de bord calcule les membres ayant au moins un abonnement actif non expiré, les encaissements du jour et du mois, les passages acceptés, les échéances à 7 jours et la répartition des formules. Les sommes correspondent aux paiements enregistrés, sans calcul de TVA ni de timbre. Les graphiques couvrent les six derniers mois. Les données de démonstration ne représentent pas une activité réelle.

Les requêtes utilisent PDO et les entrées SQL sont préparées ; l'affichage est échappé, les formulaires sensibles utilisent un jeton CSRF et les mots de passe sont vérifiés avec `password_verify`. La limitation des tentatives est **par session uniquement** et n'est pas suffisante contre une attaque distribuée : ajouter un contrôle côté serveur/IP et une politique de mots de passe avant la production. Les rôles sont stockés mais les permissions fines, la gestion des utilisateurs et le changement de mot de passe ne sont pas encore implémentés. Activer HTTPS, sauvegardes, supervision et durcissement serveur avant déploiement. L'accès au tableau de bord est actuellement identique pour tous les rôles.

## Étapes suivantes

Fiches membres enrichies (photo, historique, certificat et QR), renouvellement guidé et gel avec prolongation, facturation fiscale, contrôle d'accès physique, enrichissement des cours, coachs, du suivi sportif, de la boutique et des équipements, comptabilité, marketing, rapports, paramètres et portail membre. Les exports PDF, QR codes, notifications, TVA/timbre, gestion multi-salles et arabe/RTL restent à développer.

## Membres et abonnements

Les rôles Admin, Gérant et Réceptionniste peuvent créer et modifier les membres, ajouter des formules et abonnements et changer leur statut. Coach et Comptable disposent ici d’un accès en lecture seule. La liste des membres est recherchable et paginée. Un abonnement créé ne génère aucun encaissement. Le gel est un statut manuel : il ne décale pas automatiquement la date de fin. Les abonnements peuvent se chevaucher ; leur contrôle et le renouvellement guidé restent à développer. La liste noire est prise en compte lors du pointage.

## Paiements et caisse

Admin, Gérant, Réceptionniste et Comptable peuvent accéder à la caisse et enregistrer un paiement partiel sur un abonnement. Le prix de la formule est utilisé pour calculer le solde ; un verrou SQL empêche deux paiements simultanés de dépasser ce montant. Les moyens de paiement tunisiens sont proposés. La caisse affiche les encaissements d’une date, ventilés par moyen, et chaque paiement possède un reçu imprimable (ou enregistrable en PDF via le navigateur). Ce reçu n’est **pas une facture fiscale** : TVA, timbre, numérotation fiscale et informations légales ne sont pas implémentés. Le prix d’une formule n’est pas encore figé dans l’abonnement : ne modifiez pas directement les prix dans la base après utilisation. Les annulations, remboursements et les ventes hors abonnement restent à développer.

## Accès et présences

Admin, Gérant et Réceptionniste peuvent enregistrer un passage à partir du numéro de carte ; Coach consulte le journal en lecture seule. L’accès est refusé si le membre est inactif ou sur liste noire, ou s’il ne possède pas d’abonnement actif couvrant la date du jour (les abonnements gelés, annulés, futurs et expirés ne sont pas valides). Les passages de membres connus, acceptés comme refusés, sont historisés ; une carte inconnue ne peut pas être associée à un membre et n'est donc pas enregistrée. Le tableau de bord compte les passages acceptés, et non les membres uniques. Un lecteur QR qui saisit le numéro de carte comme un clavier peut être utilisé, mais la génération de QR et la lecture par caméra ne sont pas encore disponibles. Le système ne pilote pas de contrôle d’accès physique et n’applique pas de limite de passages ou de décompte de séances.

## Cours collectifs

Le planning permet de créer des salles et des cours, d'attribuer un utilisateur ayant le rôle Coach, d'inscrire un membre et d'annuler un cours ou une inscription avant son début. Un abonnement actif couvrant la date du cours est nécessaire pour réserver. La capacité est contrôlée sous verrou ; au-delà, les inscriptions rejoignent une liste d'attente. À l'annulation d'une place confirmée, le premier membre toujours éligible est promu automatiquement. Les salles ne peuvent pas accueillir deux cours simultanés. Les rôles Admin, Gérant et Réceptionniste gèrent le planning ; le Coach le consulte.

Limites : la détection des conflits de planning coach entre cours collectifs et séances privées n’est pas encore disponible ; les comptes coach peuvent être créés dans le module Coachs. Les notifications de promotion, l'espace membre, l'historique des réservations annulées dans l'interface et la modification des cours restent à développer. Les membres en attente devenus inéligibles restent en attente et sont ignorés lors de la promotion.

## Coachs et séances privées

Admin et Gérant peuvent créer un profil coach avec compte de connexion et taux de commission. Admin, Gérant et Réceptionniste peuvent programmer une séance privée pour un membre actif, puis l’annuler ou la marquer terminée après sa fin. Les conflits entre séances privées d’un même coach ou membre sont bloqués. Le taux est copié au moment de la création ; les commissions affichées aux rôles Admin, Gérant et Comptable sont des estimations basées sur les séances terminées, pas des paiements ou des commissions effectivement réglées. Le Coach ne voit que sa fiche et ses propres séances privées ; la Réception ne voit pas les montants. Les créations et changements de statut sont journalisés.

Limites : modification/désactivation du coach, réinitialisation de mot de passe, paiement des séances et règlement des commissions restent à développer. La séance privée ne vérifie pas l’abonnement et n’est pas rapprochée des encaissements. Les conflits avec les cours collectifs ne sont pas détectés. Les séances ne peuvent pas être rouvertes après annulation ou clôture. Pour les utilisateurs Coach déjà créés avant cette migration, créer une ligne correspondante dans `coach_profiles` avant de les utiliser pour les séances privées.

## Suivi sportif

Admin, Gérant et Coach peuvent consulter et ajouter des mesures (poids, taille, tour de taille, notes) ainsi que des programmes et objectifs, puis archiver ces derniers. L'IMC est calculé à partir de chaque poids et taille saisis ; il n'est pas un diagnostic médical. Le graphique affiche au plus les 100 dernières mesures. Les données affichées sont volontairement échappées et les modifications sont journalisées. Les autres rôles n'ont pas accès à ce module.

Ces données sont sensibles : avant usage réel, définir les autorisations, la base légale, les durées de conservation et les mesures de confidentialité adaptées, y compris les sauvegardes. Les coachs autorisés peuvent actuellement consulter tous les membres : il n'y a pas encore de restriction par affectation. Modification/suppression des mesures, exercices structurés, suivi des performances, consentement et export restent à développer.

## Boutique

Admin et Gérant peuvent créer des produits et les réapprovisionner. Admin, Gérant et Réceptionniste peuvent enregistrer une vente simple ; Comptable dispose d'un accès en lecture seule. Une vente concerne un produit et une quantité, et fige le prix et le moyen de paiement. Le stock est vérifié sous verrou puis mis à jour dans la même transaction que la vente et son mouvement de stock. Les produits dont le stock atteint le seuil configuré sont signalés.

Les ventes boutique sont séparées des paiements d'abonnement : elles ne figurent pas encore dans la caisse des abonnements ni dans les chiffres du tableau de bord. Pas de panier multi-produits, de modification de produit, d'inventaire correctif, d'annulation/remboursement, de reçu, de TVA/timbre ou d'historique des mouvements dans l'interface à ce stade. Les montants et quantités sont limités par les champs SQL et les validations ; ce module nécessite des tests PHP/MySQL avant toute utilisation réelle.

## Équipements et maintenance

Admin et Gérant peuvent créer des équipements et enregistrer une panne, un entretien ou une réparation, en choisissant explicitement le nouveau statut et la prochaine échéance. L'inventaire permet la recherche et signale les appareils hors service, en maintenance ou dont l'échéance est passée ou dans les sept jours. Tous les rôles peuvent le consulter ; les coûts sont visibles seulement par Admin, Gérant et Comptable. Les actions sont journalisées. Une intervention peut être datée au plus tard du jour courant ; l'échéance peut être passée pour signaler un retard.

L'historique affiche les 100 dernières interventions par équipement. Le coût est informatif et ne crée pas de dépense comptable. L'édition et la suppression des fiches ou interventions, les pièces jointes et les notifications automatiques restent à développer. Les alertes ne sont affichées que dans ce module.
