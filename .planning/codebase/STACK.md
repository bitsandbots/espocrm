# EspoCRM Tech Stack Overview

**Generated:** June 1, 2026  
**Codebase:** EspoCRM 9.3.7

## Runtime & Languages

| Component | Version | Source |
|-----------|---------|--------|
| **PHP** | >=8.3.0, <8.6.0 | `composer.json` line 8 |
| **Node.js** | >=20 | `package.json` line 108 |
| **npm** | >=8 | `package.json` line 107 |

## Required PHP Extensions

Enforced via `composer.json` (lines 9–18):
- `ext-openssl`, `ext-json`, `ext-zip`, `ext-gd`, `ext-mbstring`, `ext-xml`, `ext-dom`, `ext-curl`, `ext-exif`, `ext-pdo`, `ext-ctype`

Optional extensions (suggested, line 65–73):
- `ext-pdo_mysql`, `ext-pdo_pgsql`, `ext-bcmath`, `ext-zmq`, `ext-ldap`, `ext-fileinfo`, `ext-pcntl`, `ext-posix`

## PHP Core Frameworks & Libraries

| Package | Version | Purpose |
|---------|---------|---------|
| **Slim Framework** | ^4.15 | HTTP router & PSR-7 request/response (`slim/slim`, `slim/psr7`) |
| **Symfony** | ^7 | HTTP foundation, routing, process spawning, mailer |
| **Doctrine DBAL** | ^3.10 | Database abstraction layer |
| **Monolog** | ^3.10 | Logging (PSR-3 compliant) |
| **Ratchet** | 0.4.x-dev | WebSocket server (`cboden/ratchet` line 33) |
| **React/ZMQ** | ^0.4.0 | ZeroMQ messaging for WebSocket (`react/zmq` line 34) |
| **Guzzle** | ^7.10 | HTTP client for external APIs |
| **PHPOffice** | ^5.7 | Spreadsheet handling (`phpspreadsheet`) |
| **PHPSecLib** | ^3.0 | Cryptography utilities |
| **DomPDF** | ^3.1 | PDF generation |
| **Laminas LDAP** | ^2.20 | LDAP authentication (line 26) |
| **Mail Handling** | Multiple | `symfony/mailer`, `zbateson/mail-mime-parser`, `directorytree/imapengine` |
| **OAuth2** | `league/oauth2-client` ^2.9 | OAuth2 provider clients |

## JavaScript Frontend Stack

Build tool: **Grunt** (task runner)

| Package | Version | Purpose |
|---------|---------|---------|
| **Backbone** | ^1.3.3 | MVC framework (AMD modules, not SPA) |
| **jQuery** | ^3.7.1 | DOM manipulation |
| **Underscore** | ^1.13.8 | Utility library |
| **Handlebars** | ^4.7.7 | Template engine |
| **Full Calendar** | ^6.1.8 | Calendar UI component |
| **Ace Editor** | ^1.4.12 | Code editing widget |
| **Moment & Moment Timezone** | ^2.29.4 | Date/time handling |
| **Bootstrap** | Custom fork (yurikuzn) | CSS framework |
| **Marked** | ^4.0.10 | Markdown parser |
| **DOMPurify** | ^3.3.1 | XSS prevention |

Build dependencies: Rollup, LESS compiler, Uglify, Jasmine 5.2.0 for tests.

## Database

| Aspect | Details |
|--------|---------|
| **Type** | MySQL/MariaDB (production config at `data/config-internal.php` line 10: `'platform' => 'Mysql'`) |
| **Version** | MariaDB 10.11.14 detected (`data/config-internal.php` line 42) |
| **ORM** | Custom Espo ORM (`application/Espo/ORM/`) using Doctrine DBAL |
| **Connection** | PDO (`pdo_mysql` or `pdo_pgsql` supported, line 66–67) |

PostgreSQL is an alternative but MySQL/MariaDB is the primary deployment target.

## WebSocket & Real-Time Messaging

| Component | Details |
|-----------|---------|
| **Server** | Ratchet 0.4.x-dev (WebSocket server implementation) |
| **Message Bus** | **ZeroMQ** (default, `data/config-internal.php` line 26: `'webSocketMessager' => 'ZeroMQ'`) |
| **Runner** | `Espo\Core\ApplicationRunners\WebSocket` class |
| **Clients** | `ZeroMQSender` & `ZeroMQSubscriber` (application/Espo/Core/WebSocket/) |
| **DSN Config** | `webSocketZeroMQSubscriberDsn`, `webSocketZeroMQSubmissionDsn` |

WebSocket is used for real-time entity updates and activity stream notifications.

## Build & Deployment Pipeline

| Tool | Purpose | Location |
|------|---------|----------|
| **Grunt** | Build orchestration | `Gruntfile.js` (line 29+) |
| **Rollup** | Module bundling | `@rollup/plugin-commonjs`, `@rollup/plugin-node-resolve` |
| **espo-frontend-build-tools** | Custom build utilities | GitHub fork |
| **LESS** | CSS preprocessing | CSS compiled to `client/css/espo/` |
| **Uglify** | JS minification | `grunt-contrib-uglify` |

Build targets:
- `grunt` – full build
- `grunt dev` – development build
- `grunt test` – test build with Jasmine
- `grunt release` – zipped release with upgrade packages

## File Storage

| Type | Implementation |
|------|-----------------|
| **Local** | File system (`data/upload/`) |
| **AWS S3** | `Espo\Core\FileStorage\Storages\AwsS3` (supported via `league/flysystem-async-aws-s3`) |

## Autoload Configuration

PSR-4 namespaces (composer.json lines 78–86):
- `Espo\` → `application/Espo/`
- `Espo\Custom\` → `custom/Espo/Custom/`
- `Espo\Modules\` → `custom/Espo/Modules/`

## Testing Infrastructure

| Framework | Purpose |
|-----------|---------|
| **PHPUnit** | ^11.5 (PHP unit & integration tests) |
| **Jasmine** | ^5.2.0 (JavaScript browser tests) |
| **PHPStan** | ^2.1 (Static analysis) |

Test directories:
- `tests/unit/` (PHPUnit)
- `tests/integration/` (PHPUnit)
- `tests/unit/Espo/` (unit namespace)
- `tests/integration/Espo/` (integration namespace)

## Modules (In-Tree & Custom)

**Core modules** (`application/Espo/Modules/`):
- **Crm** – CRM core entities (Contacts, Accounts, Leads, Opportunities, Calls, Tasks, etc.)
- **Nexus** – Integration with NEXUS platform (see INTEGRATIONS.md)

**Custom modules** (`custom/Espo/Modules/`):
- **Xero** – Accounting integration (API v2.0)
- **QuickBooks** – Accounting integration
- **Inventory** – Inventory management (`cc-inventory` bridge)

Each module has standard structure: `Services/`, `Controllers/`, `Hooks/`, `Jobs/`, `Entities/`, `Resources/metadata/`.

## Key Configuration Files

| File | Purpose |
|------|---------|
| `data/config.php` | User-editable application config (credentials, API settings) |
| `data/config-internal.php` | Internal system config (DB, logging, instance ID, crypto keys) |
| `composer.json` | PHP dependencies & PSR-4 autoload |
| `package.json` | JS dependencies & build scripts |
| `Gruntfile.js` | Build task definitions |
| `frontend/bundle-config.json` | JS chunk configuration |
| `frontend/libs.json` | Frontend library bundling rules |

## Security & Crypto

- **Password hashing** via custom `PasswordHash` utility
- **Cryptographic key storage** in `data/config-internal.php` (`cryptKey`, `hashSecretKey`)
- **CSRF protection** via token validation
- **Output escaping** via native PHP/Twig escaping
- **Two-factor auth** support via `robthree/twofactorauth` (^1.8)

## Code Style & Formatting

- **PHP** – No explicit linter config; PHPStan for static analysis
- **JavaScript** – No Prettier/ESLint config in main tree; Grunt-based minification
- **Tests** – PHPUnit assertions for PHP; Jasmine specs for JS
