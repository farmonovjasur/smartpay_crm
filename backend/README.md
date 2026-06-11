# SmartPay CRM Backend

Symfony 7.1 + API Platform 3.x REST API for SmartPay CRM.

## Requirements

- PHP 8.2+ with extensions: ctype, iconv, json, mbstring, openssl, pdo_mysql, intl, bcmath, gd, zip, xml
- MySQL 8.0+
- Composer 2.x

## Bootstrap

Task 1 of the implementation plan provisions the project skeleton without running `composer install`. To finish the bootstrap on your machine:

```bash
cd backend
composer install
cp .env.local.example .env.local
# edit .env.local with real DB credentials, JWT passphrase, CORS origin, and admin password
bin/console lexik:jwt:generate-keypair
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate -n
bin/console app:seed:initial
```

> **Note**: Migrations and the `app:seed:initial` command are added by tasks 2.x and 2.3 of the implementation plan. Running them now will fail until those tasks land.

## Project layout

```
backend/
├── bin/console                # Symfony CLI entry point
├── config/
│   ├── packages/              # bundle config (framework, security, doctrine, lexik_jwt, …)
│   ├── bundles.php            # registered bundles
│   ├── routes.yaml            # controller routing
│   └── services.yaml          # service-container config
├── public/index.php           # HTTP front controller
├── src/
│   ├── Kernel.php
│   ├── Controller/            # added by tasks 5+, 8–13
│   ├── Entity/                # added by tasks 2.1, 2.2
│   ├── Service/               # added by tasks 3, 5–14
│   └── …
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Functional/
│   ├── Property/
│   └── bootstrap.php
├── var/uploads/temp/          # transient Excel uploads (git-ignored)
├── composer.json
├── phpunit.xml.dist
├── .env                       # safe defaults (committed)
├── .env.local.example         # template for prod overrides
└── .env.test                  # test-environment defaults
```

## Test suites

```bash
composer test                 # run everything
composer test:unit            # tests/Unit
composer test:integration     # tests/Integration
composer test:functional      # tests/Functional
composer test:property        # tests/Property (PBT)
```

## Timezone

The entire stack runs on `Asia/Tashkent`:

- `APP_TIMEZONE` env var (set in `.env`, `.env.test`, and `.env.local.example`)
- Enforced in `public/index.php` and `bin/console` via `date_default_timezone_set()`
- `phpunit.xml.dist` sets `date.timezone = Asia/Tashkent` for the test process
- `framework.yaml` sets `default_locale: uz`

## Security notes

- Never commit `.env.local` or `config/jwt/*.pem` (already in `.gitignore`)
- `INITIAL_ADMIN_PASSWORD` is consumed once by `app:seed:initial`; rotate immediately after the first login
