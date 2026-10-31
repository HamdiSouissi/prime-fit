# Horizon Sportif de Bir El Hafey

Socle MVC PHP natif pour la gestion d'une salle de sport en Tunisie. **Première étape livrée : connexion, déconnexion, journal de connexion, tableau de bord MySQL, gestion de base des membres et abonnements, encaissements partiels, caisse journalière, pointage par carte, gestion des cours collectifs, séances privées des coachs, suivi sportif de base, boutique simple, inventaire des équipements suivi de trésorerie et outils marketing simples.** Les autres rubriques du menu sont indicatives et ne sont pas encore implémentées. Ce projet ne constitue pas encore une application complète de gestion de salle.

## Prérequis

PHP 8.0+ avec extensions PDO MySQL, mbstring et zlib, MySQL 8 ou MariaDB compatible, Apache (XAMPP/WAMP) ou hébergement PHP. Chart.js, Lucide et les polices sont chargés depuis des CDN et nécessitent Internet. L'application utilise le fuseau Africa/Tunis et affiche les montants en DT à trois décimales.

## Installation

1. Copier le projet dans `htdocs/prime-fit` (XAMPP) ou `www/prime-fit` (WAMP). Sur un hébergement mutualisé, placer le projet hors du dossier public et configurer le document root sur `public/` lorsque possible. Ne pas exposer `app/` ni `database/` sur Internet.
2. Créer la base et les tables en important `database/schema.sql` dans phpMyAdmin ou avec `mysql -u root -p < database/schema.sql`. Si l'hébergeur interdit `CREATE DATABASE`, créer la base via son panneau et retirer les lignes `CREATE DATABASE` et `USE` avant import.
3. Sur une installation existante antérieure au module cours, importer `database/courses.sql` une seule fois ; une nouvelle installation utilisant le schéma actuel contient déjà ces tables.
4. Sur une installation existante antérieure au module coach, importer également `database/coaches.sql` ; les nouvelles installations disposent déjà de ces tables.
5. Sur une installation existante antérieure au suivi sportif, importer `database/fitness.sql` ; les nouvelles installations disposent déjà de ces tables.
6. Sur une installation existante antérieure à la boutique, importer `database/shop.sql` ; les nouvelles installations disposent déjà de ces tables.
7. Sur une installation existante antérieure aux équipements, importer `database/equipment.sql` ; les nouvelles installations disposent déjà de ces tables.
8. Sur une installation existante antérieure aux dépenses, importer `database/finance.sql` ; les nouvelles installations disposent déjà de cette table.
9. Sur une installation existante antérieure au marketing, importer `database/marketing.sql` ; les nouvelles installations disposent déjà de cette table.
10. Pour tester, importer une seule fois `database/demo.sql` sur une base vierge. Les dates du jeu de démonstration sont relatives au jour d'import.
11. Copier `app/config/local.example.php` vers `app/config/local.php` et adapter les identifiants. Ce fichier est ignoré par Git.
12. Démarrer Apache et MySQL et ouvrir `http://localhost/prime-fit/public/` ou, si le document root pointe sur `public/`, l'URL du site.
13. Compte de démonstration : `admin@primefit.tn` / `PrimeFit2026!`. **Ne jamais utiliser ce compte en production** : supprimer le jeu de démo et créer un administrateur avec un mot de passe robuste et `password_hash()` avant mise en ligne.

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

Le tableau de bord calcule les membres ayant au moins un abonnement actif non expiré, les encaissements du jour et du mois, les passages acceptés, les échéances à 7 jours et la répartition des formules. Les sommes correspondent uniquement aux paiements d'abonnements enregistrés, hors ventes boutique et dépenses, sans calcul de TVA ni de timbre. Le graphique des encaissements couvre les six derniers mois. Les données de démonstration ne représentent pas une activité réelle.

Les requêtes utilisent PDO et les entrées SQL sont préparées ; l'affichage est échappé, les formulaires sensibles utilisent un jeton CSRF et les mots de passe sont vérifiés avec `password_verify`. La limitation des tentatives est **par session uniquement** et n'est pas suffisante contre une attaque distribuée : ajouter un contrôle côté serveur/IP et une politique de mots de passe avant la production. Les rôles contrôlent les principaux modules ; la gestion complète des utilisateurs et des permissions fines reste à développer. Activer HTTPS, sauvegardes, supervision et durcissement serveur avant déploiement. Le tableau de bord masque les encaissements aux Coachs et les passages aux Comptables, et n'interroge pas ces données pour les rôles concernés. Les autres données et permissions des différents modules restent régies par leurs règles propres.

## Étapes suivantes

Fiches membres enrichies (photo et certificat), amélioration des abonnements, facturation fiscale, contrôle d'accès physique, enrichissement des cours, coachs, du suivi sportif, de la boutique et des équipements, comptabilité réglementaire, application des promotions, parrainage, rapports avancés, paramètres avancés et portail membre. Les exports PDF, notifications, TVA/timbre, gestion multi-salles et arabe/RTL restent à développer.

## Membres et abonnements

Les rôles Admin, Gérant et Réceptionniste peuvent créer et modifier les membres, ajouter des formules et abonnements et changer leur statut. Coach et Comptable disposent ici d’un accès en lecture seule. La liste des membres est recherchable et paginée. À la création, le serveur attribue un numéro de carte unique automatiquement ; il ne peut pas être modifié depuis la fiche. Les anciens numéros restent inchangés (y compris les éventuelles fiches sans numéro). Un abonnement créé ne génère aucun encaissement. Les nouveaux abonnements et renouvellements sont contrôlés contre les chevauchements. Le gel est enregistré et peut prolonger la date de fin lors de la réactivation ; voir la section dédiée. La liste noire est prise en compte lors du pointage. Une fiche récapitulative accessible depuis la liste affiche les coordonnées autorisées et les dix derniers abonnements, paiements d'abonnement et passages, avec leur nombre total. Les paiements ne sont pas visibles par les Coachs ; les passages ne sont pas visibles par les Comptables. L'e-mail et la date de naissance sont réservés aux rôles qui peuvent modifier la fiche. Le QR lui-même n'est pas exposé dans ce récapitulatif. Ces historiques affichent les données actuelles ; la photo, les certificats et un historique complet paginé restent à développer. Aucune migration n'est nécessaire pour la fiche récapitulative.

## Paiements et caisse

Admin, Gérant, Réceptionniste et Comptable peuvent accéder à la caisse et enregistrer un paiement partiel sur un abonnement. Le prix fixé à la création de l’abonnement est utilisé pour calculer le solde ; un verrou SQL empêche deux paiements simultanés de dépasser ce montant. Les moyens de paiement tunisiens sont proposés. La caisse affiche les encaissements d’une date, ventilés par moyen, et chaque paiement possède un reçu imprimable (ou enregistrable en PDF via le navigateur). Ce reçu n’est **pas une facture fiscale** : TVA, timbre, numérotation fiscale et informations légales ne sont pas implémentés. Les prix historiques non vérifiés doivent être régularisés avant de nouveaux encaissements ; voir la section dédiée. Les annulations, remboursements et les ventes hors abonnement restent à développer.

## Accès et présences

Admin, Gérant et Réceptionniste peuvent enregistrer un passage à partir du numéro de carte ; Coach consulte le journal en lecture seule. L’accès est refusé si le membre est inactif ou sur liste noire, ou s’il ne possède pas d’abonnement actif couvrant la date du jour (les abonnements gelés, annulés, futurs et expirés ne sont pas valides). Les passages de membres connus, acceptés comme refusés, sont historisés ; une carte inconnue ne peut pas être associée à un membre et n'est donc pas enregistrée. Le tableau de bord compte les passages acceptés, et non les membres uniques. Une carte QR personnelle peut être imprimée ou téléchargée et scannée par la caméra ou un lecteur USB. Le système ne pilote pas de contrôle d’accès physique et n’applique pas de limite de passages ou de décompte de séances.

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

Les ventes boutique sont séparées des paiements d'abonnement : elles ne figurent pas encore dans la caisse des abonnements ni dans les chiffres du tableau de bord. Le panier multi-produits, les annulations/remboursements, les reçus boutique et le calcul de TVA/timbre restent à développer. Les montants et quantités sont limités par les champs SQL et les validations ; ce module nécessite des tests PHP/MySQL avant toute utilisation réelle.

## Équipements et maintenance

Admin et Gérant peuvent créer des équipements et enregistrer une panne, un entretien ou une réparation, en choisissant explicitement le nouveau statut et la prochaine échéance. L'inventaire permet la recherche et signale les appareils hors service, en maintenance ou dont l'échéance est passée ou dans les sept jours. Tous les rôles peuvent le consulter ; les coûts sont visibles seulement par Admin, Gérant et Comptable. Les actions sont journalisées. Une intervention peut être datée au plus tard du jour courant ; l'échéance peut être passée pour signaler un retard.

Admin et Gérant peuvent modifier le nom, la référence, l'emplacement et la date d'achat d'une fiche ; le statut et l'échéance restent modifiés par une intervention. Pour éviter d'écraser un état récent, une nouvelle intervention ne peut pas être antérieure à la dernière et elle est refusée si une autre intervention a modifié la fiche entre-temps. L'historique affiche les 100 dernières interventions par équipement ; il ne conserve pas l'ancien statut ou l'ancienne échéance. L'inventaire est filtrable par statut ou échéance et exportable en CSV avec ces filtres, toutes pages comprises et sans les coûts.

Le coût est informatif et ne crée pas de dépense comptable. La suppression ou l'archivage des fiches, l'édition des interventions, les pièces jointes et les notifications automatiques restent à développer. Les alertes ne sont affichées que dans ce module. Aucune nouvelle migration n'est nécessaire pour ces améliorations.

## Dépenses et trésorerie

Admin, Gérant et Comptable peuvent enregistrer des dépenses payées (loyer, salaires, électricité, eau, maintenance, fournitures, marketing ou autre) et consulter une synthèse mensuelle : paiements d’abonnement, ventes boutique, dépenses et différence. L'export CSV contient toutes les dépenses du mois sélectionné, avec protection des champs texte contre les formules de tableur. Les dépenses sont enregistrées et journalisées dans une transaction.

Cette synthèse est un suivi de trésorerie simplifié, **pas un bilan comptable ou fiscal** : TVA, timbre, écritures comptables, salaires détaillés, remboursements et rapprochement bancaire ne sont pas implémentés. Les coûts saisis dans le module équipements et les commissions coach ne créent pas automatiquement de dépenses : les saisir ici si effectivement payés. Les séances privées ne produisent pas d'encaissement ; les chiffres du dashboard restent limités aux abonnements. La modification, suppression ou contrepassation des dépenses, les pièces justificatives et l'export PDF/Excel natif restent à développer.

## Marketing et relances

Admin et Gérant peuvent créer, activer ou désactiver des codes promotionnels en pourcentage. La Réception peut consulter le catalogue et préparer les relances. Les codes sont **informatifs uniquement** : ils ne sont ni appliqués ni vérifiés lors de la création d'un abonnement ou d'un paiement. Aucun suivi d'utilisation, plafond ou parrainage n'est encore disponible.

Les anniversaires du mois et abonnements expirant sous sept jours sont affichés pour les membres actifs, jusqu'à 100 lignes par liste. Un numéro tunisien de 8 chiffres, avec ou sans indicatif +216, permet d'ouvrir WhatsApp avec un message prérempli ; le membre du personnel vérifie et envoie lui-même le message. Il n'y a aucun envoi automatique, SMS, historique de contact ni contrôle de consentement dans l'application. Vérifiez les autorisations et préférences de communication avant toute relance ; les membres inactifs ou blacklistés ne figurent pas dans ces listes.

## Cartes QR et pointage par caméra

**Migration obligatoire pour une base existante** : sauvegarder la base puis importer `database/member_qr.sql` une seule fois. Ne pas importer cette migration sur une nouvelle installation créée avec le `schema.sql` actuel, qui comprend déjà la colonne. Les membres déjà présents (y compris ceux de démonstration) reçoivent un QR depuis « Membres → Carte QR → Générer ».

Pour chaque nouveau membre, un jeton aléatoire unique est créé automatiquement et sa carte s'ouvre après l'enregistrement. L'équipe Admin, Gérant ou Réceptionniste peut l'imprimer, l'enregistrer en PDF depuis le navigateur ou télécharger le QR en PNG. Depuis « Accès & présences », cliquer sur « Scanner avec la caméra », autoriser l'accès à la caméra et présenter le QR ; la validation d'abonnement, le refus éventuel et le journal de présence restent identiques au pointage manuel. Le numéro de carte et les lecteurs USB qui saisissent le contenu du QR restent utilisables. Les bibliothèques QR sont servies localement et ne transmettent pas les codes à un service externe.

La caméra exige un navigateur compatible et un contexte sécurisé **HTTPS** (ou localhost), ainsi que les autorisations du navigateur ; sur une prévisualisation intégrée, l'autorisation de caméra doit aussi être accordée à l'iframe. Les codes sont des identifiants réutilisables, pas une preuve d'identité : leur copie permet de pointer à la place du membre. Ne pas partager les QR publiquement ; en cas de perte, utiliser « Renouveler le QR » pour invalider l'ancien et remettre le nouveau. Le pointage ne pilote pas de serrure physique et ne constitue pas un système antifraude.

### Dépannage : ajout de membre indisponible après la mise à jour QR

Une installation existante peut encore avoir la table `members` sans la colonne `qr_token`. Le nouveau code ne peut alors pas enregistrer le membre. Après sauvegarde, ouvrez phpMyAdmin, sélectionnez **la base réellement configurée** dans `app/config/local.php` (par défaut `prime_fit`), puis importez `database/member_qr.sql` une seule fois. Réessayez l'ajout. Une installation neuve créée avec le `schema.sql` actuel n'a pas besoin de cette migration. Ne réimportez pas `schema.sql` ou `demo.sql` sur une base utilisée pour corriger ce problème.

Si la colonne existe déjà ou si l'erreur persiste, consultez les journaux PHP/Apache pour connaître l'erreur exacte : une erreur de connexion, de droits ou une autre migration manquante peut produire le même message général. Ne publiez pas les identifiants de la base.

## Envoi de la carte QR par e-mail

Une adresse e-mail valide est obligatoire pour chaque **nouveau** membre. Après la création et la validation en base, l'application prépare automatiquement un e-mail contenant son QR personnel en pièce jointe PNG. Depuis « Membres → Carte QR », le personnel autorisé peut également utiliser « Envoyer le QR par e-mail » pour réessayer ; le message est adressé à l'adresse actuelle de la fiche. Après renouvellement du QR, renvoyez la nouvelle carte : l'ancienne est invalide.

**Configuration indispensable :** dans `app/config/local.php` (copié depuis `local.example.php`), renseignez une adresse d'expéditeur de votre domaine et activez l'envoi :

```php
'mail' => ['enabled' => true, 'from' => 'contact@votre-domaine.tn'],
```

Conservez également la configuration `db` du fichier. L'application utilise la fonction PHP native `mail()` : votre hébergement doit disposer d'un transport sortant correctement configuré (sendmail ou relais SMTP géré par le serveur). Sur XAMPP/WAMP, ce transport n'est généralement pas configuré par défaut ; renseignez-vous auprès de votre hébergeur ou configurez-le sur le serveur. L'extension PHP **zlib** est nécessaire pour créer l'image PNG. Configurez aussi les enregistrements SPF/DKIM/DMARC de votre domaine selon votre fournisseur et effectuez un envoi de test réel.

Un message « accepté par le serveur de messagerie » ne garantit pas la livraison dans la boîte du client. Si l'envoi est désactivé, mal configuré ou échoue, le membre reste enregistré et l'interface l'indique : corrigez la configuration puis renvoyez la carte. Aucun service d'envoi externe n'est appelé pour générer le QR. L'e-mail n'est pas chiffré de bout en bout ; le QR est copiable et ne doit être envoyé qu'à l'adresse confirmée du membre. Bibliothèque QR PHP : PHP QR Code, licence LGPL-3.0, incluse dans `app/vendor/phpqrcode` (cache désactivé et adaptations de compatibilité PHP 8).

## Rapports et statistiques

Admin, Gérant et Comptable peuvent consulter un rapport mensuel : encaissements d’abonnements et ventes boutique, dépenses, solde, passages acceptés et membres distincts. Le tableau quotidien s’exporte en CSV (toutes les dates du mois) et peut être imprimé ou enregistré en PDF via le navigateur. Les montants proviennent des enregistrements existants ; aucune nouvelle migration n’est nécessaire.

La rétention est une **estimation** : parmi les membres dont le statut est actuellement actif et dont un abonnement actuellement marqué actif couvre au moins un jour du mois précédent, proportion ayant également un abonnement actif couvrant au moins un jour du mois sélectionné. La fréquentation estimée est la proportion des membres actuellement actifs avec un abonnement actif couvrant au moins un jour du mois ayant enregistré au moins un passage accepté durant ce mois. Les statuts historiques et gels ne sont pas reconstitués ; les résultats des mois passés peuvent donc évoluer quand les statuts changent. L'indicateur de fréquentation ne mesure pas la capacité ni le taux d'occupation. Les séances privées sans paiement enregistré sont exclues, et le solde ne constitue pas une comptabilité fiscale. Le graphique requiert Chart.js ; les données restent disponibles dans le tableau sans celui-ci.

## Renouvellement et gel des abonnements

Sur une installation existante, sauvegarder puis sélectionner la base utilisée dans phpMyAdmin et importer `database/subscription_freezes.sql`. Les nouvelles installations créées avec `schema.sql` contiennent déjà cette table.

Depuis la liste des abonnements, Admin, Gérant et Réceptionniste peuvent renouveler avec la même formule. Le renouvellement commence le lendemain de la fin de l'abonnement si celle-ci est future, sinon le jour courant ; aucun paiement n'est créé. Le membre et la formule doivent être actifs. Les abonnements annulés ou gelés ne peuvent pas être renouvelés directement. Les nouvelles périodes chevauchant un abonnement actif sont refusées ; un gel en cours empêche aussi une nouvelle adhésion.

Un abonnement actif en cours peut être gelé à partir du jour courant. À sa réactivation, la date de fin est prolongée du nombre de jours calendaires entre la date du gel et le jour de reprise : une reprise le même jour ajoute zéro jour, et la reprise le lendemain ajoute un jour. La reprise est refusée si la prolongation chevauche une autre adhésion active ; annulez ou régularisez d'abord le chevauchement. Les opérations sont transactionnelles, verrouillent le membre et sont journalisées. L'annulation d'un abonnement gelé clôt son gel sans prolongation et ne crée pas de remboursement.

Les gels enregistrés par l'ancienne version n'avaient pas de date de début : ils ne peuvent pas être prolongés automatiquement. Leur régularisation nécessite de vérifier les dates réelles avec un administrateur ; ne pas inventer de durée. L'annulation reste possible mais ne crédite pas les jours passés. Les changements de formule lors du renouvellement, les paiements automatiques et les limites de gel restent à développer.

## Paramètres de la salle

Pour une installation existante, sauvegardez votre base et importez `database/gym_settings.sql` dans la base configurée. Avec Laragon et une base nommée `prime_fit`, la commande est :

```bash
mysql -u root -p prime_fit < database/gym_settings.sql
```

Les nouvelles installations utilisant `schema.sql` possèdent déjà cette table. Admin et Gérant peuvent enregistrer le téléphone, l'adresse, l'e-mail, le matricule fiscal, les horaires et les paramètres indicatifs de TVA et de timbre ; Comptable dispose d'un accès en lecture seule. Les coordonnées et le matricule actuellement configurés apparaissent sur les reçus, y compris les reçus anciens : ils ne sont pas figés par transaction.

Le nom affiché en lecture seule est défini dans la configuration ; le changement dynamique de la marque, le logo, les sauvegardes et la gestion multi-salles restent à développer. Les champs TVA et timbre sont **informatifs uniquement** : ils n'entraînent aucun calcul ni ajout aux paiements, et les reçus ne sont pas des factures fiscales.

### Produits et corrections de stock

Sur une base existante antérieure à l'inventaire correctif, sauvegardez puis importez la migration une seule fois :

```bash
mysql -u root -p prime_fit < database/shop_inventory.sql
```

Remplacez `prime_fit` par le nom de votre base si nécessaire. Ne l'importez pas si `stock_movements.note` existe déjà : les schémas `schema.sql` et `shop.sql` actuels incluent ces changements.

Depuis les détails d'un produit, Admin et Gérant peuvent modifier son nom, sa référence, son prix et son seuil, le désactiver ou le réactiver, et corriger le stock à partir d'un comptage physique avec un motif obligatoire. L'écart et son auteur sont enregistrés dans l'historique ; les 100 derniers mouvements sont consultables. Une correction est refusée si le stock a changé depuis l'ouverture du formulaire. Le produit est verrouillé pendant la transaction, et une erreur d'audit annule la modification. Un produit inactif ne peut pas être vendu. Les prix des ventes existantes restent figés mais les noms affichés dans l'historique reprennent le nom actuel du produit. Les ajustements ne génèrent pas automatiquement de dépense, perte comptable ou remboursement.

## Sécurité du compte

Chaque utilisateur connecté peut changer son propre mot de passe via **Mon compte**, avec son mot de passe actuel et confirmation. Le nouveau mot de passe doit comprendre entre 12 et 72 octets (un caractère accentué peut compter pour plusieurs octets). Le changement et son journal sont enregistrés ensemble ; aucun mot de passe n'est enregistré dans le journal. La session courante et son jeton CSRF sont renouvelés. Les autres sessions sont invitées à se reconnecter à leur prochaine requête ; les requêtes déjà en cours ne sont pas interrompues. Les comptes inactifs sont bloqués lors de leur prochaine requête. Après cette mise à jour, les sessions existantes doivent se reconnecter une fois. Aucune migration SQL n'est nécessaire.

La limitation des essais de connexion et de changement de mot de passe reste **par session uniquement**, contournable en créant une nouvelle session ; elle doit être complétée côté serveur avant production. La réinitialisation d'un mot de passe oublié, l'expiration d'inactivité, la double authentification et une gestion complète des comptes restent à développer. Utilisez HTTPS et remplacez le compte de démonstration avant tout déploiement.

## Prix des abonnements et reprise historique

**Sur une base existante**, sauvegardez d’abord la base puis, depuis la racine du projet, importez une seule fois :

```bash
mysql -u root -p prime_fit < database/subscription_prices.sql
```

Remplacez `prime_fit` par le nom de votre base si nécessaire. Les nouvelles installations utilisant le `schema.sql` actuel possèdent déjà la colonne : ne relancez pas cette migration si `subscriptions.agreed_price` existe. L'application mise à jour nécessite cette migration pour la gestion des abonnements et des paiements.

Pour tout nouvel abonnement, le prix courant de la formule est figé lors de l'enregistrement. Un renouvellement utilise le prix et la durée actuels de la formule, et crée un nouvel abonnement sans paiement automatique. Les paiements sont contrôlés contre le prix figé, non contre le prix actuel du catalogue. Les prix ne se modifient pas depuis l'application après leur fixation.

**Les anciens prix n'étaient pas historisés : ils ne peuvent pas être reconstitués automatiquement.** La migration les laisse indéterminés et les paiements supplémentaires sur ces abonnements sont bloqués. Admin ou Gérant doit consulter les contrats/justificatifs puis ouvrir « Abonnements → Vérifier les prix historiques » ou le lien équivalent dans la caisse, et saisir le prix convenu pour chaque abonnement. Le prix actuel de la formule est seulement une indication, pas une preuve du prix d'origine. Le prix est fixé une seule fois et journalisé ; si les paiements existants le dépassent, l'application refuse la validation et une vérification manuelle est nécessaire. Les paiements existants et leurs reçus ne sont ni supprimés ni modifiés par la migration.

La correction d'un prix déjà fixé, les remboursements et la régularisation des trop-perçus ne sont pas encore implémentés dans l'interface. Testez le processus et les verrous de concurrence sur MySQL avant utilisation réelle.

### Gestion des formules

Dans **Abonnements → Formules disponibles → Gérer**, Admin et Gérant peuvent modifier le nom, la durée et le prix d’une formule, ou la désactiver/réactiver. La création reste accessible à la Réception. Les changements sont journalisés. Une formule inactive ne peut pas être utilisée pour une nouvelle adhésion ou un renouvellement, mais les abonnements existants restent inchangés.

Le prix et les dates déjà fixés sur les abonnements ne changent pas ; un renouvellement utilise le tarif et la durée affichés pour la formule active. Si la formule change pendant l'ouverture du formulaire, la souscription ou le renouvellement est refusé pour permettre une nouvelle vérification. Le nom de la formule n'est pas historisé : les anciens abonnements et reçus affichent le nom actuel. La modification ne corrige pas les prix des abonnements anciens non vérifiés. Aucune migration supplémentaire n'est nécessaire ; la migration `subscription_prices.sql` reste requise sur les bases existantes qui ne l'ont pas encore reçue.

### Recherche et historique des abonnements

La liste des abonnements est recherchable par nom, numéro de carte ou formule et filtrable par situation : en cours, à venir, expiré, gelé ou annulé. Le statut tient compte des dates actuelles pour les abonnements actifs. Tous les rôles ayant accès aux abonnements peuvent ouvrir **Détails** pour voir la période actuelle, le prix fixé et l'historique paginé des gels : début, fin, état, jours crédités et auteurs lorsqu'ils sont connus. La fin d'un gel annulé correspond à l'annulation de l'abonnement, pas à une reprise.

Les anciens gels sans date ne sont pas reconstitués ; un avertissement apparaît lorsqu'un abonnement gelé n'a pas de gel ouvert. Les dates et le nom de formule affichés reflètent l'état actuel, pas un instantané historique. Cette fonctionnalité ne nécessite pas de nouvelle migration, mais les installations existantes doivent avoir reçu les migrations `subscription_freezes.sql` et `subscription_prices.sql` si elles ne sont pas déjà présentes.

### Tableau de bord et rôles

Les indicateurs financiers du tableau de bord sont réservés à Admin, Gérant, Réceptionniste et Comptable ; les passages acceptés du jour sont réservés à Admin, Gérant, Réceptionniste et Coach. Les échéances affichées concernent les abonnements actifs **déjà commencés** et se terminant dans les sept jours ; les membres actifs sont décomptés une seule fois. La répartition par formule compte des membres distincts par formule : si des données anciennes comportent des abonnements actifs chevauchants dans des formules différentes, son total peut dépasser le nombre global. Les noms et statuts des dernières adhésions reflètent les données actuelles. Le tableau de bord n'est pas un bilan comptable.

Aucune migration n'est nécessaire. Cette restriction concerne le tableau de bord ; d'autres rubriques, notamment la liste des abonnements et des formules, peuvent encore afficher des tarifs au Coach. Une politique de permissions fines reste à développer.
