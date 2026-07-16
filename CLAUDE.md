# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

EspoCRM 9.x with a custom **Nexus** module that integrates the NEXUS agentic platform. The Nexus module lives entirely in two places:

- `application/Espo/Modules/Nexus/` — PHP backend
- `client/modules/nexus/` — JS frontend (AMD views)

Everything else is upstream EspoCRM. Touch only the Nexus module unless fixing an EspoCRM bug that blocks NEXUS functionality.

## Commands

### JS tests (Jasmine browser runner)
```bash
# Full suite (requires built client/lib/ — run npm run build-dev first if stale)
npx jasmine-browser-runner runSpecs --config=frontend/test/jasmine-browser.json
```
JS tests require `client/lib/espo.js` and `client/lib/transpiled/` to be current. Run `npm run build-dev` if tests fail to load AMD modules.

### Build
```bash
php command.php rebuild  # clear caches + reload metadata after PHP module changes
```

## Architecture

### PHP backend (`application/Espo/`)

EspoCRM uses a DI container (`Espo\Core\Container`) and constructor injection throughout. The request lifecycle:

1. `index.php` → `Application.php` → router matches `routes.json`
2. Route dispatches to a **Controller** (`action*` methods, e.g. `actionHealth`)
3. Controllers call **Services** for business logic
4. Services access the DB via **Repositories** (ORM layer in `Espo\ORM`)
5. **Hooks** fire on entity lifecycle events (afterSave, beforeSave, etc.)
6. **Jobs** (in `Jobs/`) run async via the scheduler

Metadata (field definitions, layouts, entity defs) lives in `Resources/metadata/` JSON files inside each module. After adding/changing metadata, always run `php command.php rebuild`.

### JS frontend (`client/`)

EspoCRM's frontend is a custom AMD module system built on Backbone. Key facts:

- `client/src/` contains source JS/TS files
- `grunt dev` transpiles TS → JS into `client/lib/transpiled/`
- `client/lib/espo.js` is the bundled loader + lib
- Views extend `Espo.View` (Backbone-based); loaded via AMD `require()`
- Module paths use `moduleName:path/to/view` syntax (e.g. `nexus:views/panels/nexus-assistant`)
- **AMD loader unwraps default exports**: in `require([id], Cls => ...)` callbacks, `Cls` is already the class — never use `.default`

### Nexus module structure

```
application/Espo/Modules/Nexus/
├── Controllers/NexusGateway.php   # API actions: health, settings, chat, submit, status, result
├── Services/
│   ├── AgentClient.php            # HTTP client to NEXUS /chat
│   ├── QueueClient.php            # HTTP client to NEXUS /submit + polling
│   ├── RagClient.php              # HTTP client to NEXUS RAG ingestion
│   ├── NexusAuth.php              # Shared auth header builder
│   └── NexusService.php           # Orchestrates the above
├── Hooks/Common/AfterSave.php     # Triggers RAG push on entity save
├── Jobs/QueuePoller.php           # Scheduled job for async queue polling
└── Resources/
    ├── routes.json                # /api/v1/nexus/* route definitions
    ├── module.json                # order: 20
    └── metadata/                  # Admin panel field defs (adminDefs, etc.)

client/modules/nexus/src/views/
├── admin/nexus-settings.js        # Admin settings panel view
└── panels/nexus-assistant.js      # Inline chat panel on record detail views
```

### Config

Runtime config lives in `data/config.php` (PHP array, not committed but present locally). Nexus-specific keys: `nexusUrl`, `nexusUsername`, `nexusPassword`, `nexusEnabled`, `nexusRagEnabled`. The `nexusEnabled` check uses `!== false` — null/missing is treated as enabled.

## Test patterns

**PHP**: PHPUnit with `ContainerMocker` helper (`tests/unit/ContainerMocker.php`) for dependency injection in unit tests. Test classes under `tests/unit/Espo/Modules/Nexus/` mirror the module namespace.

**JS**: Jasmine specs in `frontend/test/spec/test.*.js`. Each spec loads its target via the AMD loader in a `beforeAll` block:
```js
beforeAll(done => {
    require(['nexus:views/panels/nexus-assistant'], ViewClass => {
        NexusAssistantView = ViewClass;
        done();
    });
});
```
Stub files in `frontend/test/stubs/` provide minimal DOM/dependency mocks for views that need them. Register stubs in `frontend/test/jasmine-browser.json` under `srcFiles` before the module under test.
