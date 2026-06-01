# EspoCRM Coding Conventions

**Last updated:** 2026-06-01

## PHP Style & Structure

### Namespaces & Class Naming
- **Namespaces:** Fully qualified, mirroring directory structure: `Espo\Controllers\Integration`, `Espo\Modules\Nexus\Services\NexusService`, `Espo\Modules\Inventory\Services\CcInventoryDbService`
- **Classes:** PascalCase (e.g., `NexusService`, `AfterSave`, `CcInventoryDbService`)
- **Interfaces:** PascalCase with suffix (e.g., `AfterSaveInterface` in `/Hooks/Common/AfterSave.php`)
- **Methods:** camelCase with descriptive prefixes: `isEnabled()`, `checkHealth()`, `getSettings()`, `testConnection()`

### Type Hints & Declarations
- **Strict types:** `declare(strict_types=1);` on all new files (seen in recent code: NexusService, NexusAuth)
- **Return types:** Required on all public methods (`: void`, `: bool`, `: array`, `: string`)
- **Parameter types:** Fully typed (no mixed unless unavoidable; use `?Type` for nullable)
- **PHPStan level:** 8 (see `phpstan.neon`) — static analysis enforced on all `application/` code

### Error Handling
- **Throw custom exceptions:** Use `Espo\Core\Exceptions\*` (Forbidden, NotFound, Error, BadRequest)
- **Document with @throws:** Every method that throws must declare it (`@throws Forbidden`, `@throws Error`)
- **Error messages:** Always provide context: `"CC Inventory integration is not enabled."`, `"could not connect to database — " . $e->getMessage()`
- **Never catch and swallow:** Always re-throw or log with context
- **Logging:** Use injected `Log` service: `$this->log->error("message")` (see CcInventoryDbService)

### Constructor Injection
- **Pattern:** Private readonly properties, injected in constructor signature
  ```php
  public function __construct(
      private Config $config,
      private ConfigWriter $configWriter,
      private Log $log,
  ) {}
  ```
- **Order:** List dependencies in logical groups (config, writers, services, helpers)

### Comments & Documentation
- **File headers:** Full AGPL header (see all Espo files)
- **Class docblocks:** Describe purpose and behavior (see NexusService, AfterSave)
- **Method docblocks:** Parameter and return types with @throws tags
- **Comment style:** Multi-line `/** ... */` for documentation, `//` for inline code notes

### Module Structure
- **Location:** `application/Espo/Modules/{ModuleName}/` (built-in) or `custom/Espo/Modules/{ModuleName}/` (custom)
- **Directories:** Services, Controllers, Hooks, Jobs, Resources/metadata, Resources/i18n
- **Services:** Business logic, dependencies injected (NexusService, AgentClient, QueueClient)
- **Hooks:** Entity lifecycle (Common/ for all types, EntityType/ for specific types) — implement hook interface
- **Controllers:** API endpoints, validate authorization first (check `@throws` in constructor)
- **Jobs:** Background tasks (implement JobInterface)

## JavaScript/AMD Module Conventions

### AMD Module Naming & IDs
- **Named module IDs:** All views and helpers should have explicit names
  - Pattern: `/path/to/ModuleName.js` → ID is `views/path/to/module-name` (lowercase-kebab)
  - Example: `client/src/views/nexus/settings.js` → `views/nexus/settings` (named ID)
- **Default export:** Always export class as default (`export default MyClass`)
- **Require pattern:** Use array-based require to load dependencies
  ```js
  require(['module-a', 'module-b'], (ModuleA, ModuleB) => {
      // EspoCRM AMD loader auto-unwraps .default, so ModuleA is the class
      const instance = new ModuleA();
  });
  ```

### Class & Method Naming
- **Classes:** PascalCase (e.g., `HomeView`, `EmailHelper`, `NexusAssistant`)
- **Methods:** camelCase (e.g., `setup()`, `parseNameFromStringAddress()`, `getReplyAttributes()`)
- **Private fields:** Use `#` syntax or prefix `_` for older code (class properties use `=` declaration)
  ```js
  class MyView extends View {
      template = 'my-template'
      scope = null  // property with default
      
      setup() { }   // lifecycle method
  }
  ```

### Inheritance & Views
- **Extend from:** `View` for components, `BaseModel` for models
- **Lifecycle:** `setup()` called on init, `data()` for template context, `onRender()` after template renders
- **Child views:** Use `this.createView('name', 'path/to/view', {selector: '> .selector'})`
- **Model/Collection:** Access via `this.model` or `this.collection`; call `sync()` to save

### Error Handling
- **Try/catch:** Use for async operations and error recovery
- **Promises:** Avoid nested callbacks; use `.then().catch()` chaining
- **User feedback:** Show errors via toast/alert system (depends on context)
- **No silent failures:** Always log or notify on error

### Comments & Documentation
- **File headers:** Full AGPL header (matches PHP)
- **JSDoc:** Document exported classes and public methods
  ```js
  /** @returns {EmailHelper} */
  function createHelper(userAttrs = {}) { ... }
  ```
- **Module comments:** Use `/** @module views/path-to-module */` at top

### DI Container (JavaScript)
- **Pattern:** `diModule.container.set(ClassName, mockOrInstance)` for testing
- **Clear after use:** `diModule.container.clear()` to reset for next test
- **Mocking:** Create plain objects with required methods: `{translate: (key) => key}`

## Common Idioms & Patterns

### PHP
- **Null-safe operator:** `$entity?->getEntityType()` (not yet seen, but PHP 8.0+)
- **Array types:** Use `array<string, mixed>` in docblocks for return types
- **Boolean casts:** `(bool) $value` to convert config values
- **Prepared statements:** Always use PDO prepared queries: `$stmt->execute([$param])`
- **Static properties:** Use for constants or configuration: `public static int $order = 100;`

### JavaScript
- **Spread operator:** `{...defaults, ...attrs}` for object merging
- **Short-circuit evaluation:** `condition && action()` common for event handlers
- **Template literals:** Use for HTML strings, avoid in AMD require calls
- **Object destructuring:** Extract named properties in parameters
- **Ternary chains:** Keep simple; use if/else for complex logic

## What NOT to Do

### PHP Anti-patterns
- ❌ Don't use `$_GET`, `$_POST` directly — use `Request::getRouteParam()`, `Request::getParsedBody()`
- ❌ Don't concatenate SQL with variables — always use prepared statements
- ❌ Don't return untyped arrays — specify keys/values in docblock: `array<string, mixed>`
- ❌ Don't silence errors with `@` operator
- ❌ Don't store passwords/secrets in config files — read from env or integration entity
- ❌ Don't hardcode class names — use dependency injection or `::class` constant

### JavaScript Anti-patterns
- ❌ Don't use inline `<script>` tags in views — use AMD modules
- ❌ Don't create DOM directly — use templates and Backbone views
- ❌ Don't mix named IDs with anonymous modules — be explicit
- ❌ Don't rely on global variables — inject via DI container or require
- ❌ Don't forget to clear DI container in tests — state leaks between tests
- ❌ Don't use `.default` on AMD-loaded modules — loader auto-unwraps

### General
- ❌ Don't commit `.env`, `*_rsa`, `*_ed25519`, `credentials.json`
- ❌ Don't use generic error messages — include context and operation details
- ❌ Don't skip type hints to save time — static analysis catches bugs early
- ❌ Don't modify vendor code — use composition, services, or hooks instead

## Linting Configuration

- **EditorConfig:** `.editorconfig` enforces 4-space indents, CRLF line endings, UTF-8 charset
- **PHPStan:** `phpstan.neon` level 8, analyzes `application/` directory, excludes module vendors
- **Prettier:** No dedicated config in root; package.json dev dependencies include `@types/*` for TypeScript
- **PHP formatting:** Inferred from code samples — PSR-12 style (no explicit config file found)

## Module Metadata & Config

- **Metadata location:** `Resources/metadata/` (JSON defs for UI, entities, views)
- **Translation files:** `Resources/i18n/en_US/*.json` for user-facing strings
- **Routes:** `routes.json` in module Resources for API endpoint mapping
- **module.json:** `{"name": "Nexus"}` declares module name
