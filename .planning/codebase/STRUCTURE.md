# EspoCRM Directory Structure

## Root Level

| Directory | Purpose |
|-----------|---------|
| `/application/Espo/` | PHP backend code; namespaced as `Espo\*` |
| `/client/` | JavaScript frontend; AMD modules, views, styles |
| `/custom/` | Customer/extension customizations; mirrors `/application/` structure |
| `/data/` | Runtime data: SQLite database, file attachments, cache, logs |
| `/dev/` | Development utilities, testing setup |
| `/frontend/` | (Legacy?) build/bundling configuration |
| `/public/` | Web document root; entry points and static assets |
| `/vendor/` | Composer dependencies (Composer autoload at top) |
| `bootstrap.php` | Initial setup: includes autoloader, sets include paths |
| `index.php` | Fallback; shows setup instructions if `/public/index.php` not configured |
| `cron.php`, `daemon.php`, `command.php` | CLI entry points for background jobs |

## `/application/Espo/` (Backend Code)

### Top-level Directories

| Path | Purpose |
|------|---------|
| `Core/` | Framework code: DI, routing, ORM, services, hooks, job queue |
| `Modules/` | Built-in modules: Crm, Nexus |
| `Classes/` | Utility classes (not part of main MVC) |
| `Controllers/` | HTTP action handlers (RESTful endpoints) |
| `Entities/` | ORM entity models (one per entity type) |
| `Repositories/` | Custom repository logic per entity (overrides ORM defaults) |
| `Services/` | Business logic services (one per entity or feature) |
| `ORM/` | Object-Relational Mapping: Entity, Repository, QueryBuilder, Database |
| `Hooks/` | Global hooks (beforeSave, afterSave, etc.); per-entity hooks in metadata |
| `Tools/` | Specialized tools: Email, Layout, Import, etc. (invoked by Services) |
| `Resources/` | Metadata, i18n, routes (applied to all modules) |
| `Binding.php` | Interface→Implementation bindings for DI |

### `/application/Espo/Core/` (Framework)

| Path | Purpose |
|------|---------|
| `Application.php` | Main entry point; orchestrates container, runners, preloads |
| `ApplicationRunners/` | Runners: Client (HTML), EntryPoint (REST API), Install |
| `Container/` | DI container: `ContainerBuilder`, `Container`, config loader |
| `InjectableFactory.php` | Factory for creating instances with auto-wired dependencies |
| `Api/` | REST API routing: `RouteProcessor`, `ControllerActionProcessor`, `Request`, `Response` |
| `ORM/` | Entity, Repository, QueryBuilder, Database access, Relations |
| `Record/` | Base `Service` for CRUD, field validation, access control |
| `Acl/` | Access Control List: enforces permissions on entities, fields |
| `HookManager.php` | Hook dispatcher; runs beforeSave, afterSave, etc. |
| `Job/` | Job queue management, execution, scheduling |
| `Utils/` | Utilities: Config, Metadata, Autoload, File manager, Module loader |
| `Authentication/` | Auth providers, tokens, login/logout |
| `Mail/` | Email sending, IMAP/SMTP clients |
| `Notification/`, `Webhook/`, `WebSocket/` | Notification and real-time channels |

### `/application/Espo/ORM/` (Database Access)

| Path | Purpose |
|------|---------|
| `Entity.php`, `BaseEntity.php` | Base entity class; property getters/setters, dirty tracking |
| `Repository.php`, `RDBRepository.php` | Base repository; CRUD, query methods |
| `EntityManager.php` | Main interface for fetching, saving, removing entities |
| `QueryBuilder.php`, `Query/` | SQL query composition (SELECT, WHERE, JOIN, etc.) |
| `Mapper/` | Maps ORM results to Entity objects |
| `DB/` | Low-level database access: PDO wrappers, statement execution |
| `Defs/`, `Metadata.php` | Entity metadata (fields, relations, indexes) |

### `/application/Espo/Modules/Crm/` (Built-in CRM Module)

**Mirrors main structure:** Controllers/, Services/, Entities/, Repositories/, Resources/, Hooks/, etc.

Contains core CRM entities: Lead, Contact, Account, Opportunity, Case, EmailTemplate, etc.

### `/application/Espo/Modules/Nexus/` (NEXUS Integration Module)

**Location:** `/application/Espo/Modules/Nexus/`

| Path | Purpose |
|------|---------|
| `Controllers/NexusGateway.php` | HTTP API: POST /Nexus/chat, /Nexus/queue, /Nexus/rag endpoints |
| `Services/` | Business logic: `NexusService`, `AgentClient`, `QueueClient`, `RagClient`, `NexusAuth` |
| `Hooks/Common/AfterSave.php` | On entity save (Lead, Contact, etc.), emit to NEXUS queue if enabled |
| `Jobs/QueuePoller.php` | Scheduled job to fetch queue messages, update EspoCRM records |
| `Resources/module.json` | Module metadata (order: 20) |
| `Resources/routes.json` | REST routes (inherited by main router) |
| `Resources/metadata/` | Adds features to entities: Lead.json, Contact.json, Account.json, Case.json (Nexus chat, queue) |
| `Resources/i18n/` | Translations: Global.json, Admin.json |

### `/application/Espo/` File Naming Conventions

| Pattern | Example | Scope |
|---------|---------|-------|
| `Controllers/Entity.php` | `Controllers/Lead.php` | HTTP action handlers for Lead entity |
| `Services/Entity.php` | `Services/User.php` | Business logic for User entity |
| `Repositories/Entity.php` | `Repositories/Email.php` | Custom query/save logic for Email |
| `Entities/Entity.php` | `Entities/Contact.php` | ORM entity definition for Contact |
| `Hooks/Common/Hook.php` | `Hooks/Common/BeforeSave.php` | Global hook (all entities) |
| `Hooks/Entity/Hook.php` | `Hooks/Contact/BeforeSave.php` | Entity-specific hook |
| `ORM/Type/Field.php` | `ORM/Type/DateType.php` | Field type handler (date, text, etc.) |

## `/client/` (Frontend Code)

### Top-level Directories

| Path | Purpose |
|------|---------|
| `src/` | Core AMD modules: views, models, controllers, helpers |
| `modules/` | Feature modules: `crm/`, `nexus/` (mirrors src structure per module) |
| `css/` | Stylesheets (Hazyblue theme, custom themes) |
| `lib/` | Third-party JS libraries (jQuery, underscore, moment, etc.) |
| `fonts/`, `img/`, `sounds/` | Static assets |
| `res/` | Template files (HTML snippets for views) |
| `custom/` | Customer customizations (views, etc.) |

### `/client/src/` (Core Frontend Framework)

| File/Dir | Purpose |
|----------|---------|
| `app.js` | Main app initialization; sets up router, loads modules |
| `loader.js` | AMD module loader; implements `require()`, `define()` |
| `view.ts` | Base `View` class; template rendering, event binding, lifecycle |
| `model.ts`, `collection.ts` | `Model` (single entity), `Collection` (array of entities) |
| `controller.js` | Route controller; loads views for each route |
| `router.js` | Backbone-style routing; `/#entity/view/id` |
| `metadata.js`, `language.js` | Metadata/i18n loaded from server |
| `field-manager.js` | Field type registry and rendering |
| `layout-manager.js` | Layout (detail, list, create) definitions |
| `acl.js`, `acl-manager.js` | Client-side access control checks |
| `views/` | View modules (detail, list, create, etc.); organized by entity |
| `helpers/` | Helper modules (html, record, field, etc.) |
| `handlers/`, `controllers/` | Event handlers, dynamic logic |
| `models/`, `collections/` | Model/Collection per entity type |

### `/client/modules/crm/src/` (CRM Module Frontend)

| Path | Purpose |
|------|---------|
| `views/` | CRM-specific views: Lead/detail, Contact/list, Account/create, etc. |
| `handlers/`, `helpers/` | CRM-specific event handlers, helpers |

### `/client/modules/nexus/src/` (NEXUS Module Frontend)

| Path | Purpose |
|------|---------|
| `views/` | NEXUS UI components: chat panel, queue, RAG results, admin panel |

### `/client/` File Naming Conventions

| Pattern | Example | Scope |
|---------|---------|-------|
| `views/Entity/action.js` | `views/lead/detail.js` | View for Lead entity detail page |
| `views/Entity/list.js` | `views/contact/list.js` | List view for Contact |
| `models/Entity.js` | `models/lead.js` | Model for Lead entity |
| `collections/Entity.js` | `collections/contact.js` | Collection for Contact |
| `helpers/Helper.js` | `helpers/record.js` | Helper for common record operations |
| `handlers/Entity/Handler.js` | `handlers/lead/field-action.js` | Field-specific handler |

## `/custom/` (Customizations)

**Structure mirrors `/application/Espo/` and `/client/`:**

```
custom/Espo/Modules/Custom/        # Custom module
  Controllers/
  Services/
  Entities/
  Resources/metadata/
  
custom/views/                       # Custom frontend views
  entity/detail.js
```

Extensions placed here override core code without modifying source.

## `/data/` (Runtime)

| Path | Purpose |
|------|---------|
| `database.sqlite` | SQLite database (if using SQLite; MySQL typically elsewhere) |
| `files/` | Uploaded attachments, file storage |
| `cache/` | Application cache (metadata, data cache) |
| `logs/` | Application logs |
| `uploads/` | Export/import temporary files |

## `/public/` (Web Root)

| File | Purpose |
|------|---------|
| `index.php` | Entry point; routes to `Application::run(Client or EntryPoint)` |
| `install/index.php` | Installation wizard |
| `portal/index.php` | Portal (portal user interface) |
| `.htaccess` | Apache rewrite rules: all requests → `index.php` (except `/client/`, `/public/`) |
| Symlink/copy of `/client/` | Static assets served directly |

## `/vendor/` (Dependencies)

Managed by Composer. Key dependencies:
- `espocrm/php-espo-api-client` — Internal API client library
- Database drivers (mysql, pgsql)
- HTTP client libraries
- Logging, utilities

## Key File Paths by Concern

### To Add a New Entity Type
1. `/application/Espo/Entities/Entity.php` — ORM entity definition
2. `/application/Espo/Services/Entity.php` — Business logic
3. `/application/Espo/Controllers/Entity.php` — HTTP routes
4. `/application/Espo/Resources/metadata/entityDefs/Entity.json` — Field definitions
5. `/client/src/models/entity.js` — Frontend model
6. `/client/src/views/entity/detail.js` — Detail view

### To Add a Module
1. Create `/application/Espo/Modules/ModuleName/` or `/custom/Espo/Modules/ModuleName/`
2. Add Controllers, Services, Hooks, Resources (metadata, routes, i18n)
3. Create `/client/modules/modulename/src/views/` for frontend
4. Module auto-discovered by `Module::getList()` (scans directories)

### To Add a Hook
1. Global: `/application/Espo/Hooks/Common/HookName.php`
2. Per-entity: `/application/Espo/Hooks/Entity/HookName.php` or declare in metadata
3. Module hook: `/application/Espo/Modules/ModuleName/Hooks/Common/HookName.php`
4. Implement `beforeSave()`, `afterSave()`, `beforeRemove()`, `afterRemove()`, etc.

### To Add a Background Job
1. `/application/Espo/Jobs/JobName.php` or `/application/Espo/Modules/ModuleName/Jobs/JobName.php`
2. Implement `run()` method
3. Register in `/application/Espo/Resources/metadata/app/scheduledJobs.json`
4. Run via `cron.php`, `daemon.php`, or CLI `command.php`
