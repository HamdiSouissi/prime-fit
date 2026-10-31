# Prime Fit

Socle MVC PHP natif pour la gestion d'une salle de sport en Tunisie. **Première étape livrée : connexion, déconnexion, journal de connexion et tableau de bord MySQL.** Les autres rubriques du menu sont indicatives et ne sont pas encore implémentées. Ce projet ne constitue pas encore une application complète de gestion de salle.

## Prérequis

PHP 8.0+ avec extensions PDO MySQL et mbstring, MySQL 8 ou MariaDB compatible, Apache (XAMPP/WAMP) ou hébergement PHP. Chart.js, Lucide et les polices sont chargés depuis des CDN et nécessitent Internet. L'application utilise le fuseau Africa/Tunis et affiche les montants en DT à trois décimales.

## Installation

1. Copier le projet dans `htdocs/prime-fit` (XAMPP) ou `www/prime-fit` (WAMP). Sur un hébergement mutualisé, placer le projet hors du dossier public et configurer le document root sur `public/` lorsque possible. Ne pas exposer `app/` ni `database/` sur Internet.
2. Créer la base et les tables en important `database/schema.sql` dans phpMyAdmin ou avec `mysql -u root -p < database/schema.sql`. Si l'hébergeur interdit `CREATE DATABASE`, créer la base via son panneau et retirer les lignes `CREATE DATABASE` et `USE` avant import.
3. Pour tester, importer une seule fois `database/demo.sql` sur une base vierge. Les dates du jeu de démonstration sont relatives au jour d'import.
4. Copier `app/config/local.example.php` vers `app/config/local.php` et adapter les identifiants. Ce fichier est ignoré par Git.
5. Démarrer Apache et MySQL et ouvrir `http://localhost/prime-fit/public/` ou, si le document root pointe sur `public/`, l'URL du site.
6. Compte de démonstration : `admin@primefit.tn` / `PrimeFit2026!`. **Ne jamais utiliser ce compte en production** : supprimer le jeu de démo et créer un administrateur avec un mot de passe robuste et `password_hash()` avant mise en ligne.

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

Gestion des membres et des abonnements, encaissement/facturation, contrôle d'accès, cours, coachs, suivi sportif, boutique, équipements, comptabilité, marketing, rapports, paramètres et portail membre. Les exports PDF, QR codes, notifications, TVA/timbre, gestion multi-salles et arabe/RTL restent à développer.
