# Hangar à Sucre

Site vitrine PHP avec agenda des événements et interface d'administration. Ce guide explique comment l'installer sur **votre propre serveur** et comment le déplacer vers un autre VPS.

## Prérequis

- Un VPS Debian ou Ubuntu avec accès `sudo`.
- Un nom de domaine pointant vers le VPS, si le site doit être accessible publiquement.
- Apache 2.4, PHP 8.1 ou supérieur et MariaDB/MySQL.
- Les extensions PHP `pdo_mysql` et `mbstring`.
- Un accès SSH au VPS et une copie autorisée des fichiers du site.

Les exemples ci-dessous utilisent Apache avec `mod_php`. Adaptez les chemins et les commandes si votre hébergeur utilise une autre configuration.

## Installation sur le VPS

### 1. Installer Apache, PHP et MariaDB

```bash
sudo apt update
sudo apt install apache2 mariadb-server php libapache2-mod-php php-cli php-mysql php-mbstring
sudo systemctl enable --now apache2 mariadb
```

Vérifiez que PHP et ses extensions sont disponibles :

```bash
php -v
php -m | grep -E 'mbstring|PDO|pdo_mysql'
```

### 2. Copier les fichiers du site

Placez **tout le contenu du projet** dans `/var/www/hangar-a-sucre` (par exemple avec SFTP ou `scp`). Si vous utilisez Git, clonez le dépôt auquel vous avez accès à cet emplacement.

Vérifiez que la structure ressemble à ceci :

```text
/var/www/hangar-a-sucre/
├── app/
├── assets/
├── css/
├── database/
├── js/
├── index.php
├── admin.php
└── .htaccess
```

Apache doit pouvoir lire les fichiers. Seuls le dossier des images ajoutées depuis l'admin et le dossier de stockage doivent être accessibles en écriture :

```bash
sudo chown -R "$USER":www-data /var/www/hangar-a-sucre
sudo find /var/www/hangar-a-sucre -type d -exec chmod 750 {} \;
sudo find /var/www/hangar-a-sucre -type f -exec chmod 640 {} \;
sudo install -d -o www-data -g www-data -m 750 /var/www/hangar-a-sucre/storage
sudo install -d -o www-data -g www-data -m 750 /var/www/hangar-a-sucre/assets/event_uploads
```

Ne donnez pas les droits d'écriture à tout le projet ou à tous les utilisateurs (`chmod -R 777`).

### 3. Créer la base de données et son utilisateur

Ouvrez MariaDB en tant qu'administrateur :

```bash
sudo mariadb
```

Dans l'invite MariaDB, remplacez `MOT_DE_PASSE_FORT` par un mot de passe unique et exécutez :

```sql
CREATE DATABASE events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hangar_app'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON events.* TO 'hangar_app'@'localhost';
GRANT CREATE ON *.* TO 'hangar_app'@'localhost';
EXIT;
```

L'application crée automatiquement la table `events` au premier accès. Le droit `CREATE` global est nécessaire parce que le code vérifie/crée la base au démarrage ; utilisez un compte dédié à cette application, jamais le compte `root`.

Le fichier [`database/provision.sql`](database/provision.sql) ne contient que la création de la base.

### 4. Configurer les paramètres du site

Créez une configuration Apache locale au serveur :

```bash
sudo nano /etc/apache2/conf-available/hangar-a-sucre.conf
```

Ajoutez les variables suivantes et remplacez les valeurs d'exemple :

```apache
SetEnv DB_HOST "localhost"
SetEnv DB_PORT "3306"
SetEnv DB_NAME "events"
SetEnv DB_USER "hangar_app"
SetEnv DB_PASSWORD "REMPLACER_PAR_LE_MOT_DE_PASSE_MARIADB"
SetEnv EVENT_ADMIN_PASSWORD_HASH "REMPLACER_PAR_UN_HASH_BCRYPT"
SetEnv EVENT_ADMIN_ALLOWED_IPS "ADRESSE_IP_PUBLIQUE_DE_L_ADMIN"
```

Générez le hash du mot de passe d'administration sur le VPS. La saisie est masquée et le mot de passe ne figure pas dans la commande :

```bash
read -s -p "Nouveau mot de passe admin : " ADMIN_PASSWORD; echo
printf '%s' "$ADMIN_PASSWORD" | php -r '$password = stream_get_contents(STDIN); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
unset ADMIN_PASSWORD
```

Copiez le hash affiché dans `EVENT_ADMIN_PASSWORD_HASH`. N'utilisez pas le mot de passe par défaut (`admin123`). Protégez le fichier de configuration, qui contient le mot de passe de la base :

```bash
sudo chown root:root /etc/apache2/conf-available/hangar-a-sucre.conf
sudo chmod 640 /etc/apache2/conf-available/hangar-a-sucre.conf
sudo a2enconf hangar-a-sucre
```

`EVENT_ADMIN_ALLOWED_IPS` accepte une ou plusieurs adresses IP publiques séparées par des virgules. Par défaut, seule une connexion locale est autorisée : indiquez l'adresse IP publique fixe de la personne qui administrera le site. L'application vérifie l'adresse de connexion directe (`REMOTE_ADDR`), pas les en-têtes proxy. N'ouvrez pas l'administration à toutes les adresses IP.

### 5. Configurer le site Apache

Créez un hôte virtuel :

```bash
sudo nano /etc/apache2/sites-available/hangar-a-sucre.conf
```

Exemple à adapter avec votre nom de domaine :

```apache
<VirtualHost *:80>
    ServerName exemple.fr
    ServerAlias www.exemple.fr
    DocumentRoot /var/www/hangar-a-sucre

    <Directory /var/www/hangar-a-sucre>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/hangar-a-sucre-error.log
    CustomLog ${APACHE_LOG_DIR}/hangar-a-sucre-access.log combined
</VirtualHost>
```

Activez le site et vérifiez la configuration :

```bash
sudo a2ensite hangar-a-sucre
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Configurez ensuite le DNS du domaine vers l'adresse IP du VPS et mettez le site en HTTPS avec un certificat TLS. Avant la mise en ligne, vérifiez que le pare-feu du VPS n'autorise que les ports nécessaires (SSH, HTTP et HTTPS).

### 6. Vérifier le déploiement

- Ouvrez le domaine et vérifiez l'accueil, la galerie, la plaquette et l'agenda.
- Ouvrez `https://exemple.fr/admin.php` depuis l'adresse IP autorisée et connectez-vous avec le mot de passe configuré.
- Créez puis modifiez un événement et vérifiez l'ajout d'une image.
- Consultez les journaux Apache en cas d'erreur : `sudo tail -n 100 /var/log/apache2/hangar-a-sucre-error.log`.

Les images ajoutées depuis l'administration sont enregistrées dans `assets/event_uploads/`. Les données des événements sont dans la base `events`.

## Utilisation

- **Visiteurs :** consulter l'accueil, la galerie, la plaquette PDF et l'agenda.
- **Administration :** ouvrir `/admin.php` pour ajouter, modifier ou supprimer des événements et leurs images. L'accès est limité aux IP définies dans `EVENT_ADMIN_ALLOWED_IPS`.
- **Événements :** ils sont conservés dans MySQL/MariaDB ; les images téléversées sont des fichiers séparés dans `assets/event_uploads/`.

## Changer de VPS / migrer le site

La migration se fait entre les deux serveurs : il n'est pas nécessaire d'héberger le site sur le VPS de la personne qui a fourni le projet. Il faut avoir accès à l'ancien serveur, ou obtenir de son propriétaire une copie des fichiers et une sauvegarde de la base.

### 1. Sauvegarder l'ancien serveur

Sur l'ancien serveur, exportez les données et copiez les fichiers du projet, en particulier `assets/event_uploads/` :

```bash
sudo mariadb-dump --single-transaction --default-character-set=utf8mb4 events > events.sql
```

Téléchargez `events.sql` et une archive du projet par SFTP/SCP vers un emplacement sûr. Excluez les dépendances inutiles, mais conservez les images téléversées. Ne publiez pas et ne transmettez pas le fichier SQL ou les mots de passe : ils peuvent contenir des données privées.

### 2. Préparer le nouveau VPS

Suivez les étapes d'installation ci-dessus sur le nouveau VPS. Créez la base et l'utilisateur, copiez les fichiers sauvegardés et rétablissez les permissions.

Importez ensuite l'export SQL sur le nouveau serveur :

```bash
sudo mariadb events < events.sql
```

Configurez les variables Apache pour le nouvel environnement : identifiants MariaDB, hash du mot de passe admin, IP autorisées et domaine. Les adresses IP d'administration peuvent changer avec le nouveau serveur ou réseau.

### 3. Tester puis basculer le domaine

1. Testez le nouveau VPS avec son adresse IP ou un sous-domaine de test.
2. Vérifiez les pages, la connexion admin, les événements et les images.
3. Activez HTTPS sur le nouveau serveur.
4. Modifiez les enregistrements DNS du domaine pour pointer vers le nouveau VPS.
5. Gardez l'ancien serveur et ses sauvegardes jusqu'à confirmation que le nouveau site fonctionne correctement.

Pour une migration sans perte de modifications, évitez d'ajouter des événements sur l'ancien site pendant la copie finale. Refaites un export/import de la base et une copie des images juste avant le changement DNS.

## Sécurité et maintenance

- Gardez PHP, Apache, MariaDB et le système à jour.
- Faites des sauvegardes régulières de la base et de `assets/event_uploads/`, et testez leur restauration.
- N'ajoutez pas de mots de passe, de dump de base ou de données privées au dépôt Git.
- Changez le hash admin et la liste d'IP autorisées depuis la configuration Apache, puis rechargez Apache.
- Les mentions légales présentes dans le projet indiquent actuellement un éditeur et un hébergeur précis. Avant toute publication sur un autre VPS ou pour un autre responsable, vérifiez et mettez à jour les mentions légales, la politique de confidentialité et les coordonnées pour refléter la situation réelle.
