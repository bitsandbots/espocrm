# EspoCRM Architecture

**Overview:** EspoCRM is a layered monolith with modular extension points. It separates backend (PHP) and frontend (JavaScript/AMD) into distinct architectures that communicate via REST API.

## Core Pattern: Layered Architecture

### Backend (PHP) — Dependency Injection + Modular

**Entry:** `/public/index.php` → `bootstrap.php` → `Application::run()`

1. **Application Layer** (`/application/Espo/Core/Application.php`)
   - Central bootstrap orchestrator
   - Initializes Container (DI), runs ApplicationRunners (Client, EntryPoint, etc.)
   - Preloads services marked with `preload: true` in metadata

2. **Container/DI** (`/application/Espo/Core/Container/`)
   - `ContainerBuilder` constructs service container from configuration
   - `Container` holds singleton services retrieved by name or class
   - Binding system (`/application/Espo/Core/Binding/`) allows interface→implementation mapping
   - `InjectableFactory` creates instances with auto-wired dependencies

3. **Module System** (`/application/Espo/Core/Utils/Module.php`)
   - **Discovery:** Scans `application/Espo/Modules/` (internal) and `custom/Espo/Modules/` (custom)
   - Each module is a directory with optional `Resources/module.json` (defines `order` priority)
   - Modules are loaded in order, allowing later modules to override earlier metadata
   - `/application/Espo/Modules/Crm` — built-in CRM entities (Lead, Contact, Account, etc.)
   - `/application/Espo/Modules/Nexus` — NEXUS integration (queue, RAG, agent services)

4. **Request Routing** (`/application/Espo/Core/Api/`)
   - `Starter` → `RouteProcessor` → `ControllerActionProcessor`
   - Route resolution: `/{entity}/{id}/{action}` → Controller class → action method
   - Controllers are instantiated via `InjectableFactory` (dependencies auto-injected)
   - Example: `Api\Lead\post` calls `Controllers\Lead::postAction()`

5. **Data Layer**
   - **Repositories** (`/application/Espo/Repositories/`, `/application/Espo/ORM/Repository/`)
     - CRUD operations on entities
     - Custom repositories override ORM defaults (e.g., `Repositories/Email.php` for email-specific logic)
   - **Entities** (`/application/Espo/Entities/`, `/application/Espo/ORM/Entity.php`)
     - Represent database records
     - Base: `Entity` (property getters/setters, dirty tracking, validation hooks)
   - **ORM** (`/application/Espo/ORM/`)
     - Query builder, EntityManager, DBExecutor
     - Relations: Relationships defined in metadata, lazy-loaded
     - Database: PDO-backed, supports MySQL/MariaDB/PostgreSQL

6. **Services** (`/application/Espo/Services/`, `/application/Espo/Core/Record/`)
   - Business logic layer
   - Base: `RecordService` (generic CRUD with field validation, defaults, access control)
   - Controllers delegate to Services → Services use Repositories
   - Example flow: `NexusGateway` Controller → `NexusService` → `AgentClient` → HTTP to agent

7. **Hooks & Events** (`/application/Espo/Core/HookManager.php`, `/application/Espo/Hooks/`)
   - Execution points: `beforeSave`, `afterSave`, `beforeRemove`, `afterRemove`, etc.
   - Per-entity and global hooks (e.g., `Hooks/Common/AfterSave.php`)
   - Nexus module hooks at `/application/Espo/Modules/Nexus/Hooks/Common/` trigger queue events

8. **Jobs & Async** (`/application/Espo/Core/Job/`, `/application/Espo/Core/Schedule/`)
   - Scheduled jobs run via cron.php or daemon.php
   - Job classes in `/application/Espo/Jobs/` or module `/Jobs/` (e.g., `Nexus/Jobs/QueuePoller.php`)
   - Queue polling for NEXUS messages on a scheduled interval

### Frontend (JavaScript/AMD) — Module Loader + View Stack

**Entry:** `Client` ApplicationRunner → HTML template → `client/src/app.js`

1. **Module Loader** (`client/src/loader.js`)
   - Implements AMD (Asynchronous Module Definition) standard
   - Loads JS/CSS modules on demand with caching
   - Module paths mapped in loader config (built from server metadata)
   - Supports: `require()`, `define()` with dependencies resolved
   - Internal modules at `client/src/`, `client/modules/{crm,nexus}/src/`

2. **View Architecture** (`client/src/view.ts`, `client/src/View*.js`)
   - Views are AMD modules (e.g., `views/views/lead/detail.js`)
   - Base: `View` class (template rendering, event binding, lifecycle)
   - View hierarchy: `ListView` → `BaseLayoutView` → `View` (composition pattern)
   - Views injected with: `model`, `collection`, `helper`, `acl`, `metadata`, `ui`

3. **Models & Collections** (`client/src/model.ts`, `client/src/collection.ts`)
   - `Model` — Single entity, synced with REST API
   - `Collection` — Array of models, queryable, paginable
   - Fetch, save, remove trigger API calls (AJAX)
   - Dirty state tracking; attribute change events (`on('change:attr')`)

4. **Router** (`client/src/router.js`)
   - Backbone-style routing: `/#entity/view/id`, `/#entity/list`
   - Controller loads appropriate views for each route
   - Ensures navigation state syncs with browser back/forward

5. **Modules on Frontend** (`client/modules/`)
   - `crm/src/views/` — CRM-specific views (Lead, Contact, Account, etc.)
   - `nexus/src/views/` — NEXUS UI views (chat panel, queue, RAG results)
   - Module views are loaded by the view loader after metadata lookup

6. **Metadata & Language** (`client/src/metadata.js`, `client/src/language.js`)
   - Server sends metadata as JSON (entity defs, field types, layouts)
   - Field manager interprets field metadata to render UI (text, date, select, link, etc.)
   - Language file map for internationalization (i18n)

### Data Flow: Request → Response

**Synchronous HTTP Request:**
```
Browser Request (GET/POST/PUT/DELETE)
  ↓
/public/index.php (route via .htaccess)
  ↓
Application::run(EntryPoint | Client)
  ↓
RouteProcessor (match REST route)
  ↓
ControllerActionProcessor (resolve Controller::action)
  ↓
InjectableFactory (inject dependencies)
  ↓
Controller (business logic dispatch)
  ↓
Service (validation, processing, access control)
  ↓
Repository (ORM CRUD)
  ↓
ORM Mapper → SQL → Database
  ↓
JSON Response (API) | HTML (Client)
```

**Frontend Async Flow:**
```
User Action (click, form submit)
  ↓
View Event Handler
  ↓
Model.save() / Collection.fetch()
  ↓
AJAX (XMLHttpRequest)
  ↓
[HTTP Request flow above]
  ↓
JSON Response
  ↓
Model.set() / Collection.reset()
  ↓
View re-renders (template + data binding)
```

## Key Abstractions

### Dependency Injection
- **Container** holds all services; DI by name or interface
- **InjectableFactory** reads constructor parameters via reflection, matches via binding or type-hint
- Services declared in metadata (`app > containerServices`) or via binding config
- Allows swapping implementations without code changes (e.g., custom Repository for Lead)

### Modularity
- **Internal Modules** (`application/Espo/Modules/`) — Core functionality (Crm, Nexus)
- **Custom Modules** (`custom/Espo/Modules/`) — Client extensions
- Modules loaded in order, later modules can override metadata of earlier ones
- Each module has: Controllers, Services, Hooks, Jobs, metadata (`Resources/`), i18n

### Services vs. Repositories
- **Repository** — Data access (fetch, save, delete, query)
- **Service** — Business logic (validation, authorization, processing)
- Services call Repositories; Controllers call Services
- Separation allows testing and reuse

## NEXUS Module Integration

**Location:** `/application/Espo/Modules/Nexus/`

**Architecture:**
- **Controllers** (`Controllers/NexusGateway.php`) — HTTP routes for chat, queue, RAG endpoints
- **Services** (`Services/`)
  - `NexusService` — Main dispatcher (chat, queue, RAG operations)
  - `AgentClient` — WebSocket/HTTP to NEXUS agent (at Pi:5000)
  - `QueueClient` — Queue message operations
  - `RagClient` — Retrieval-Augmented Generation queries
  - `NexusAuth` — Token-based auth for agent communication
- **Hooks** (`Hooks/Common/AfterSave.php`) — On record save, emit to NEXUS queue (if enabled)
- **Jobs** (`Jobs/QueuePoller.php`) — Periodic job to fetch queue messages, update EspoCRM
- **Frontend** (`/client/modules/nexus/src/views/`) — Chat panel, queue UI, RAG results panel
- **Metadata** (`Resources/metadata/`) — Enables Nexus features on Lead, Contact, Account, Case

**Data Flow (Example: Chat Message):**
```
User types in chat view
  ↓
frontend: NexusChat view emits message
  ↓
AJAX POST /Nexus/chat (with message, context)
  ↓
NexusGateway::postActionChat()
  ↓
NexusService::sendMessage()
  ↓
AgentClient::sendChat() → WebSocket to Pi:5000
  ↓
Agent processes, returns response
  ↓
Response saved to Message record, ACL enforced
  ↓
JSON returned to frontend
  ↓
View updates chat UI
```

## Metadata System

All structural metadata is declared in `Resources/metadata/` (YAML or JSON):
- **Entity defs** (`entityDefs/Entity.json`) — field types, relations, access control
- **Client defs** (`clientDefs/Entity.json`) — UI layouts (detail, list, create)
- **App metadata** (`app/`) — global settings, admin panel config, scheduled jobs
- Loaded and cached by `Metadata` service
- Modules can extend existing entity defs (e.g., Nexus adds fields to Lead via metadata)

## Why This Architecture?

1. **Modularity:** Modules (Crm, Nexus) are loaded independently, can be toggled
2. **Testability:** DI allows mocking services, repositories
3. **Extensibility:** Custom modules in `custom/` don't modify core
4. **Separation of Concerns:** Controllers → Services → Repositories → ORM
5. **Frontend Isolation:** AMD modules loaded on-demand, no global state pollution
