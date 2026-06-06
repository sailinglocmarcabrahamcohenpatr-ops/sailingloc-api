# SailingLoc API

API REST pour la plateforme de location de bateaux **SailingLoc**.  
Construite avec **Symfony 8**, **PHP 8.4**, **PostgreSQL 16**, authentification **JWT** et conteneurisée avec **Docker**.

---

## Prérequis

| Outil | Version minimale |
|---|---|
| Docker | 24+ |
| Docker Compose | v2 (plugin) |
| OpenSSL | pour la génération des clés JWT |

> Sur Windows : utilisez **Docker Desktop** avec WSL2 ou **Git Bash** pour les commandes Unix.

---

## Installation et démarrage (développement)

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-compte/sailingloc-api.git
cd sailingloc-api
```

### 2. Créer le fichier d'environnement local

```bash
cp .env .env.local
```

Éditez `.env.local` et renseignez vos valeurs :

```dotenv
APP_SECRET=changez_ce_secret_32_caracteres
DATABASE_URL=postgresql://sailingloc:sailingloc_password@database:5432/sailingloc?serverVersion=16&charset=utf8

JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase_jwt
```

### 3. Générer les clés JWT

```bash
mkdir -p config/jwt

# Sur Linux/macOS/WSL :
openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:votre_passphrase_jwt
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:votre_passphrase_jwt
```

> **Windows (PowerShell)** : utilisez WSL ou Git Bash pour exécuter ces commandes `openssl`.

### 4. Démarrer les conteneurs

```bash
docker compose up -d --build
```

Cela démarre 3 services :
- **app** — PHP 8.4-FPM (Symfony, mode dev avec hot-reload via volumes)
- **nginx** — serveur web sur `http://localhost:8080`
- **database** — PostgreSQL 16 sur `localhost:5433`

### 5. Exécuter les migrations

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Vérifier que l'API fonctionne

```bash
curl http://localhost:8080/api/auth/login \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test"}'
```

---

## Documentation de l'API (Swagger)

Une fois l'API démarrée, ouvrez votre navigateur sur :

```
http://localhost:8080/api-doc.html
```

La spec JSON OpenAPI 3.0 est disponible sur :

```
http://localhost:8080/api/doc.json
```

Pour tester les routes protégées dans Swagger :
1. Cliquez sur **Authorize** (icône cadenas)
2. Collez votre JWT Bearer token obtenu via `POST /api/auth/login`

---

## Créer un premier compte

```bash
curl http://localhost:8080/api/auth/register \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@sailingloc.fr",
    "password": "monMotDePasse123",
    "nom": "Admin",
    "prenom": "SailingLoc"
  }'
```

Pour promouvoir un utilisateur en `ROLE_ADMIN`, connectez-vous à la base de données :

```bash
docker compose exec database psql -U sailingloc -d sailingloc -c \
  "UPDATE utilisateur SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'admin@sailingloc.fr';"
```

---

## Lancer les tests

Les tests fonctionnels utilisent une base de données dédiée `sailingloc_test`.

### 1. Créer la base de test et migrer

```bash
docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Exécuter la suite de tests

```bash
# Tous les tests
docker compose exec -e APP_ENV=test app php bin/phpunit --testdox

# Un fichier spécifique
docker compose exec -e APP_ENV=test app php bin/phpunit tests/Controller/BateauControllerTest.php --testdox
```

---

## Structure du projet

```
sailingloc-api/
├── config/
│   ├── packages/          # Configuration des bundles (security, doctrine, jwt…)
│   ├── jwt/               # Clés RSA (non commitées)
│   └── routes.yaml
├── docker/
│   └── nginx/default.conf
├── migrations/            # Migrations Doctrine
├── public/
│   ├── index.php
│   └── api-doc.html       # Interface Swagger UI (CDN)
├── src/
│   ├── Controller/        # 11 contrôleurs REST
│   ├── Entity/            # Entités Doctrine
│   ├── Enum/              # RoleEnum (ROLE_USER, ROLE_PROPRIETAIRE, ROLE_ADMIN)
│   └── Repository/
├── tests/
│   ├── ApiTestCase.php    # Classe de base pour les tests fonctionnels
│   └── Controller/        # Tests fonctionnels (78 tests)
├── compose.yaml           # Docker Compose production
├── compose.override.yaml  # Overrides développement
├── Dockerfile
└── .env.test              # Variables d'environnement pour les tests
```

---

## Routes principales

| Méthode | Route | Accès | Description |
|---|---|---|---|
| POST | `/api/auth/login` | Public | Connexion (retourne un JWT) |
| POST | `/api/auth/register` | Public | Inscription |
| GET | `/api/utilisateurs` | ADMIN | Liste des utilisateurs |
| GET | `/api/utilisateurs/{id}` | Authentifié | Voir un profil |
| GET | `/api/bateaux` | Authentifié | Liste des bateaux |
| POST | `/api/bateaux` | PROPRIETAIRE | Créer un bateau |
| GET | `/api/reservations` | Authentifié | Mes réservations |
| POST | `/api/reservations` | Authentifié | Créer une réservation |
| POST | `/api/photos` | PROPRIETAIRE | Uploader une photo (multipart) |
| GET | `/api/referentiels/roles` | Authentifié | Rôles disponibles |

> Toutes les routes (sauf `/api/auth/*` et `/api-doc.html`) nécessitent un header `Authorization: Bearer <token>`.

---

## Hiérarchie des rôles

```
ROLE_ADMIN
  └── ROLE_PROPRIETAIRE
        └── ROLE_USER
```

Un `ROLE_ADMIN` hérite automatiquement de tous les droits `ROLE_PROPRIETAIRE` et `ROLE_USER`.

---

## Variables d'environnement

| Variable | Exemple | Description |
|---|---|---|
| `APP_ENV` | `prod` | Environnement Symfony (`dev`, `prod`, `test`) |
| `APP_SECRET` | `abc123...` | Clé secrète Symfony (32+ caractères aléatoires) |
| `DATABASE_URL` | `postgresql://user:pass@database:5432/db` | URL de connexion PostgreSQL |
| `JWT_SECRET_KEY` | `%kernel.project_dir%/config/jwt/private.pem` | Chemin vers la clé privée RSA |
| `JWT_PUBLIC_KEY` | `%kernel.project_dir%/config/jwt/public.pem` | Chemin vers la clé publique RSA |
| `JWT_PASSPHRASE` | `votre_passphrase` | Passphrase de la clé RSA |
| `POSTGRES_USER` | `sailingloc` | Utilisateur PostgreSQL |
| `POSTGRES_PASSWORD` | `sailingloc_password` | Mot de passe PostgreSQL |
| `POSTGRES_DB` | `sailingloc` | Nom de la base de données |

---

## Commandes utiles

```bash
# Voir les logs de l'application
docker compose logs -f app

# Accéder au shell PHP
docker compose exec app sh

# Accéder à PostgreSQL
docker compose exec database psql -U sailingloc -d sailingloc

# Vider le cache
docker compose exec app php bin/console cache:clear

# Lister toutes les routes
docker compose exec app php bin/console debug:router

# Créer une nouvelle migration
docker compose exec app php bin/console doctrine:migrations:diff

# Arrêter les conteneurs
docker compose down

# Arrêter et supprimer les volumes (⚠️ supprime les données)
docker compose down -v
```

---

## Déploiement en production

En production, `compose.override.yaml` n'est **pas** chargé automatiquement. Utilisez uniquement `compose.yaml` avec les bonnes variables d'environnement.

```bash
# Construire l'image de production
docker compose -f compose.yaml build

# Démarrer en production
APP_SECRET=secret_solide \
DATABASE_URL=postgresql://user:pass@database:5432/sailingloc \
JWT_PASSPHRASE=passphrase_forte \
docker compose -f compose.yaml up -d

# Migrer la base de données
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction --env=prod
```

> En production, **ne commitez jamais** `.env.local`, `config/jwt/*.pem` ni aucun fichier contenant des secrets. Ces fichiers sont déjà dans `.gitignore`.

---

## Licence

Propriétaire — © 2026 SailingLoc. Tous droits réservés.
