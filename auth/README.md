# BrailleStudio authentication

BrailleStudio uses `delight-im/auth` through Composer.

Install dependencies on the server:

```sh
composer require delight-im/auth
```

Import the official PHP-Auth database schema for your database:

```text
vendor/delight-im/auth/Database/MySQL.sql
vendor/delight-im/auth/Database/PostgreSQL.sql
vendor/delight-im/auth/Database/SQLite.sql
```

Copy `auth/config.example.php` outside `public_html`:

```text
/home3/kydjgrmy/braillestudio-auth/config.php
```

The application explicitly checks that Bluehost path. If your host uses another
location later, set `BRAILLESTUDIO_AUTH_CONFIG` to the full path.

Create the first admin from the command line:

```sh
php auth/create-admin.php admin@example.com "strong-password" eric
```

Check the server configuration:

```sh
php auth/check-config.php
```

Roles are mapped to PHP-Auth roles:

```text
admin    -> Delight\Auth\Role::ADMIN
developer -> Delight\Auth\Role::DEVELOPER
docent   -> Delight\Auth\Role::EDITOR
leerling -> Delight\Auth\Role::SUBSCRIBER
```
