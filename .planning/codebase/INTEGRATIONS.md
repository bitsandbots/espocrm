# EspoCRM External Integrations & Data Flows

**Generated:** June 1, 2026  
**Codebase:** EspoCRM 9.3.7

## NEXUS Platform Integration

**Status:** Integrated in-tree  
**Module:** `application/Espo/Modules/Nexus/`  
**Configuration:** `data/config.php` lines 4–8

| Setting | Example Value | Purpose |
|---------|---------------|---------|
| `nexusUrl` | `http://potpie.local:8000` | NEXUS platform base URL |
| `nexusUsername` | `admin` | Auth credential |
| `nexusPassword` | `NexusAdmin2026` | Auth credential |
| `nexusEnabled` | `true` | Feature flag |
| `nexusRagEnabled` | `true` | RAG (retrieval-augmented generation) flag |

**Integration Points:**

| Service | File | Purpose |
|---------|------|---------|
| **NexusService** | `Services/NexusService.php` | Facade over NEXUS HTTP clients (health check, auth, lazy initialization) |
| **QueueClient** | `Services/QueueClient.php` | Job queue submission to NEXUS |
| **AgentClient** | `Services/AgentClient.php` | Agent reasoning calls |
| **RagClient** | `Services/RagClient.php` | RAG memory queries & indexing |
| **NexusAuth** | `Services/NexusAuth.php` | Token management & session handling |
| **QueuePoller** | `Jobs/QueuePoller.php` | Async job for polling NEXUS queue status |
| **Webhook Handler** | `Hooks/Common/AfterSave.php` | Triggers NEXUS on entity events |
| **NexusGateway Controller** | `Controllers/NexusGateway.php` | HTTP endpoints for NEXUS callbacks |

**Authentication:** HTTP Basic Auth (username + password in config). Session tokens stored in NEXUS responses.

**Health endpoint:** `GET /api/health` (unauthenticated, JSON response with `status: "ok"`).

---

## Email & Calendar Integration

### IMAP/SMTP

| Aspect | Details |
|--------|---------|
| **IMAP Client** | `directorytree/imapengine` (^1.19) via `ImapParams` class |
| **SMTP** | `symfony/mailer` (^7) + custom SMTP params handler |
| **Mail Parsing** | `zbateson/mail-mime-parser` (^3.0) |
| **OAuth2 Support** | xoauth mechanism for Gmail/Outlook (line 37–38 in `SmtpParams.php`) |

**Configuration** (`data/config.php` lines 37–41):
- `smtpServer`, `smtpPort` (587 default TLS), `smtpAuth`, `smtpSecurity`, `smtpUsername`
- `outboundEmailFromAddress`, `outboundEmailFromName`
- Credentials encrypted in `data/config.php`

**Mail Accounts:** Stored as `EmailAccount` entities; supports group accounts for shared inboxes.

### Calendar Integration

- **Full Calendar** (frontend UI, ^6.1.8)
- **Moment.js & Moment Timezone** for date handling
- **ICS Parser** (`johngrogg/ics-parser` ^3.0) for calendar import/export
- **Google Calendar OAuth2** via `ExternalAccount` and `OAuthAccount` entities

---

## OAuth2 & External Accounts

**Registry:** `application/Espo/Entities/OAuthAccount.php` & `ExternalAccount.php`

| Provider | Client | Purpose |
|----------|--------|---------|
| **Google** | `Espo\Core\ExternalAccount\Clients\Google` | Gmail, Calendar, Contacts |
| **Generic OAuth2** | `Espo\Core\ExternalAccount\Clients\OAuth2Abstract` | Extensible base for other providers |

**Token Management:**
- Tokens stored in `OAuthAccount` entity
- `TokensProvider` class handles token refresh (auto-refresh before expiry, 30-second buffer, line 58–65 in `XeroService.php`)
- Refresh token attempts capped at 20 per 1-day period (`ClientManager.php` line 52)

**External Account Service:** `Espo\Services\ExternalAccount` – handles listing, connecting, disconnecting accounts.

---

## Third-Party Accounting Integrations

### Xero

**Module:** `custom/Espo/Modules/Xero/`  
**API Base:** `https://api.xero.com/api.xro/2.0` (line 19 in `XeroService.php`)  
**OAuth Endpoint:** `https://identity.xero.com/connect/token`

| Component | File | Purpose |
|-----------|------|---------|
| **XeroService** | `Services/XeroService.php` | API client, token refresh, data sync |
| **OAuth Callback** | `EntryPoints/XeroOauthCallback.php` | OAuth redirect handler |
| **ReconcileXero Job** | `Jobs/ReconcileXero.php` | Two-pass Account reconciliation |
| **SyncFromXero Job** | `Jobs/SyncFromXero.php` | Bi-directional sync |
| **Hooks** | `Hooks/Account/`, `Hooks/Invoice/`, `Hooks/Contact/` | Entity write-through sync triggers |

**Token Storage:** `Integration` entity with fields `accessToken`, `accessTokenExpiresAt`, `refreshToken`.

**Conflict Resolution:** `Tools/ConflictResolver.php` for handling sync conflicts.

---

### QuickBooks

**Module:** `custom/Espo/Modules/QuickBooks/` (extracted to standalone `~/espocrm-quickbooks/`)  
**Status:** 39/39 tests passing  
**API Base:** Intuit QuickBooks Online (OAuth2 flow)

**Components:**
- `Services/` – QuickBooks API client
- `Jobs/` – Async sync jobs
- `Hooks/` – Entity write-through triggers
- `Controllers/` – Admin panel & OAuth callback

---

### Inventory Module

**Module:** `custom/Espo/Modules/Inventory/` (extracted to standalone `~/espocrm-inventory/`)  
**Bridge:** `cc-inventory` service (CoreConduit custom system)

**Components:**
- `Services/CcInventoryDbService.php` – SQL bridge to cc-inventory DB
- `Services/CcInventorySyncService.php` – Sync coordination
- `Jobs/SyncFromCcInventory.php` – Async sync jobs
- `Hooks/` – Entity write-through to inventory system

---

## Authentication & Authorization

### Authentication Methods

| Method | Implementation |
|--------|-----------------|
| **Espo (Native)** | `Espo\Core\Authentication\Logins\Espo` – username/password |
| **API Key** | `ApiKey.php` – API token auth (HMAC signature) |
| **HMAC** | `Hmac.php` – Request signing |
| **LDAP** | `Ldap/LdapLogin.php` – Directory server auth (Laminas LDAP) |

### Authorization

- **ACL:** `Espo\Core\Acl` – Entity-level access control
- **Portal ACL:** `Espo\Core\Acl\Portal` – Portal user restrictions
- **Metadata-driven:** Roles & teams define field/entity access

### Two-Factor Auth

- `robthree/twofactorauth` (^1.8) – TOTP code generation
- Hooks in `Espo/Hooks/User/` handle token management

---

## APIs & Webhooks

### REST API

- **Framework:** Slim 4 PSR-7 implementation
- **Route:** `/api/v1/` (default entry point)
- **Response Format:** JSON with standardized error codes
- **Middleware:** Global & per-route middleware system (API middleware provider in `Core/Api/MiddlewareProvider.php`)

### Webhooks (Outbound)

**Entities:** `Webhook`, `WebhookQueueItem`, `WebhookEventQueueItem`

| Aspect | Details |
|--------|---------|
| **Manager** | `Espo\Core\Webhook\Manager` – registers event triggers |
| **Queue** | Database queue (`WebhookQueueItem`) with retry logic |
| **Processing** | Job system processes queue asynchronously |
| **Payload** | Entity data with forbidden attribute filtering |

**Configuration:** Admin UI allows defining webhooks on entity events (afterSave, afterDelete, etc.).

---

## Message Queues & Async Processing

### Job Queue

**Database Tables:** `job` (main queue), `WebhookQueueItem`, `WebhookEventQueueItem`, `EmailQueueItem`

| Component | File | Purpose |
|-----------|------|---------|
| **JobManager** | `Core/Job/JobManager.php` | Queue processing orchestration |
| **QueueUtil** | `Core/Job/QueueUtil.php` | Utility functions |
| **ProcessJobQueue*** | `Core/Job/Job/Jobs/ProcessJobQueue*.php` | Parallel queue processors (E0, Q0, etc.) |
| **AbstractQueueJob** | `Core/Job/Job/Jobs/AbstractQueueJob.php` | Base class for queue jobs |

**Configuration** (`data/config.php` lines 10–11):
- `jobMaxPortion` – batch size per job run (default 15)
- `jobRunInParallel` – parallel execution flag (false = serial)
- `jobPoolConcurrencyNumber` – max concurrent processes (default 8)

### Daemon Process

- **Interval:** `daemonInterval` (10 seconds default)
- **Max Processes:** `daemonMaxProcessNumber` (default 5)
- **Timeout:** `daemonProcessTimeout` (36000 seconds = 10 hours)

**Implementation:** Uses `symfony/process` & `spatie/async` for parallel task execution.

---

## Data Import/Export

### Spreadsheet Support

| Format | Library | Purpose |
|--------|---------|---------|
| **Excel** | `phpoffice/phpspreadsheet` (^5.7) | XLSX/XLS import/export |
| **OpenSpout** | `openspout/openspout` (^5.0) | Fast XLSX/CSV/ODS handling |
| **CSV** | Native PHP | CSV import from files |

### PDF Generation

- **DomPDF** (^3.1) – HTML-to-PDF rendering for reports, invoices, proposals

### Barcode & QR Code

- **PHP Barcode Generator** (`picqer/php-barcode-generator` ^3.2) – 1D/2D barcodes
- **PHP QR Code** (`chillerlan/php-qrcode` ^5.0) – QR code generation

---

## Maps & Geolocation

### Google Maps

| Component | File | Purpose |
|-----------|------|---------|
| **Hook** | `Espo/Hooks/Integration/GoogleMaps.php` | Integration config hook |
| **Helper** | `Espo/Classes/TemplateHelpers/GoogleMaps.php` | Template rendering |
| **CSP Header** | `data/config-internal.php` line 30 | Allow `https://maps.googleapis.com` |

**Configuration:** API key stored in `data/config.php` (user-editable).

---

## Phone Number & SMS

### Phone Number Handling

- **Library:** `brick/phonenumber` (^0.5.0) – Phone number parsing & validation
- **intl-tel-input** (^18.2.1) – Frontend JS widget for phone input
- **Entity:** `PhoneNumber` entity with country code support

### SMS

- **Placeholder:** `SmsPhoneNumber` link converter exists but no outbound SMS implementation in base
- **Extensible:** Custom SMS integrations (e.g., Twilio) can hook into entity save events

---

## Security Headers & CSP

**Content-Security-Policy Customization:**
- `clientCspDisabled` – flag to disable CSP (default false)
- `clientCspScriptSourceList` – allowed script sources (includes `https://maps.googleapis.com`)
- `clientStrictTransportSecurityHeaderDisabled` – HSTS control (set to `true` in current config)

---

## Language & Localization

- **Default:** `en_US` (`data/config.php` line 43)
- **Timezone:** `America/Chicago` (`data/config.php` line 22)
- **Translation files:** `application/Espo/Resources/i18n/` per language (JSON format)
- **Pluralization:** Handled by `symfony/translation`

---

## Data Encryption & Keys

**Stored in `data/config-internal.php` (lines 35–36):**
- `cryptKey` – 32-char hex key for sensitive fields (passwords, API keys)
- `hashSecretKey` – 32-char hex key for integrity verification

**Implementation:** Custom `Espo\Core\Utils\Crypt` utilities; passwords are salted and hashed via `PasswordHash`.

---

## Version & Instance Tracking

**Internal Config (`data/config-internal.php`):**
- `instanceId` – UUID for this deployment (line 43)
- `actualDatabaseType` – Detected type (e.g., 'mariadb', line 41)
- `actualDatabaseVersion` – Version string (e.g., '10.11.14', line 42)
- `isInstalled` – Flag indicating setup completion (line 33)
- `microtimeInternal` – Timestamp of last config write

---

## External Services Summary

| Service | Type | Auth | Status |
|---------|------|------|--------|
| NEXUS | Agentic platform | HTTP Basic | Integrated, core |
| Google (Maps, Calendar, Gmail) | SaaS | OAuth2 | Native support |
| Xero | Accounting API | OAuth2 | Custom module |
| QuickBooks | Accounting API | OAuth2 | Custom module |
| Inventory (cc-inventory) | Internal bridge | Custom | Custom module |
| LDAP | Directory | LDAP protocol | Native support |
| SMTP/IMAP | Email | SMTP/IMAP auth | Native support |

**No built-in integrations for:** Stripe, PayPal, Twilio, SendGrid, Slack, HubSpot (extensible via custom modules or webhooks).
