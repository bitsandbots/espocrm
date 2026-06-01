# EspoCRM Testing Guide

**Last updated:** 2026-06-01

## Overview

EspoCRM uses **PHPUnit** for backend tests and **Jasmine** for frontend/module tests. Both frameworks are integrated into the build system via Grunt and npm.

| Framework | Coverage | Location | Command |
|-----------|----------|----------|---------|
| PHPUnit | PHP backend | `tests/unit/`, `tests/integration/` | `npm run unit-tests`, `vendor/bin/phpunit` |
| Jasmine | JavaScript (AMD) | `frontend/test/spec/` | `npm run build-test` (builds then runs via browser-runner) |

**Current test counts:**
- PHP unit tests: 182 test files in `tests/unit/Espo/`
- JS frontend tests: 16 spec files, 101+ individual `it()` specs in `frontend/test/spec/`
- CI: GitHub Actions on push/PR to master, runs on PHP 8.3, 8.4, 8.5

---

## PHP Testing

### PHPUnit Configuration

**File:** `phpunit.xml`
```xml
<phpunit bootstrap="vendor/autoload.php" defaultTestSuite="unit">
  <testsuites>
    <testsuite name="unit">
      <directory>tests/unit</directory>
    </testsuite>
    <testsuite name="integration">
      <directory>tests/integration</directory>
    </testsuite>
```

**Bootstrap:** Auto-loads Composer dependencies; test classes are NOT auto-loaded (use explicit require or namespace)

### Running Tests

**Unit tests only:**
```bash
npm run unit-tests
# or
vendor/bin/phpunit ./tests/unit
```

**Integration tests:**
```bash
npm run integration-tests
# or
vendor/bin/phpunit ./tests/integration
```

**Both unit and integration:**
```bash
npm run build && npm run unit-tests && npm run integration-tests
# or via Grunt
npm run run-tests
```

**Single test file:**
```bash
vendor/bin/phpunit ./tests/unit/Espo/Modules/Nexus/NexusServiceTest.php
```

### Test File Naming & Structure

- **Naming:** `{Class}Test.php` (e.g., `NexusServiceTest.php`, `EmailTest.php`)
- **Namespace:** `tests\unit\Espo\{ModulePath}` mirrors source namespace `Espo\{ModulePath}`
  - Source: `Espo\Modules\Nexus\Services\NexusService`
  - Test: `tests\unit\Espo\Modules\Nexus\NexusServiceTest`

- **Base class:** Extend `PHPUnit\Framework\TestCase`
- **Method naming:** `testDescriptionOfWhatItDoes()` (PascalCase, no underscores)

### Mocking Approach

**Using `createMock()`:** PHPUnit's built-in mocking for simple substitution
```php
class NexusServiceTest extends TestCase {
    private function makeService(Config $config, ?ConfigWriter $writer = null): NexusService {
        return new NexusService(
            $config,
            $writer ?? $this->createMock(ConfigWriter::class),
            $this->createMock(Log::class),
        );
    }
    
    public function testIsEnabledReturnsFalseByDefault(): void {
        $config = $this->createMock(Config::class);
        $config->method('get')->with('nexusEnabled', false)->willReturn(false);
        $this->assertFalse($this->makeService($config)->isEnabled());
    }
}
```

**Prophecy:** Not yet seen in main codebase; PHPUnit's mock builder is preferred

**Helper utilities:** 
- `tests/unit/ReflectionHelper.php` — reflection-based property access
- `tests/unit/ContainerMocker.php` — mocks DI container for service instantiation
- `tests/integration/Core/BaseTestCase.php` — base for integration tests with DB setup

### Test Conventions

1. **Arrange-Act-Assert:** Organize each test clearly
2. **Focused scope:** One assertion per test (or logically grouped assertions)
3. **Setup methods:** Use helper methods to reduce duplication
   ```php
   function createModel(attrs = []): Object { ... }
   function createHelper(userAttrs = []): EmailHelper { ... }
   ```
4. **Documentation comments:** Each test should explain what it verifies
   ```php
   /** Parses name from "John Doe <john@example.com>" format */
   public function testParseNameFromFormattedAddress(): void { ... }
   ```

### Testing Entity Attributes

**Example:** `tests/unit/Espo/Entities/EmailTest.php`
- Defines fixture data in `protected $defs = [...]` (entity metadata)
- Creates mock repositories for relationships
- Tests entity methods and attribute access

---

## JavaScript / Frontend Testing

### Jasmine Configuration

- **Framework:** Jasmine 5.2.0 (installed via npm)
- **Runner:** `jasmine-browser-runner` (v2.5.0) — runs specs in browser environment
- **Test entry:** `frontend/test/init.js` sets up AMD loader params and aliases
- **Spec files:** `frontend/test/spec/test.{feature}.js` naming convention

### Test Initialization

**File:** `frontend/test/init.js`
```js
// Sets up AMD loader with:
// - basePath: '../../' (root of project)
// - internalModuleList: ['nexus'] (internal modules to load)
// - libsConfig: jQuery, Backbone, underscore, etc. global exports
// - aliasMap: module name shortcuts (e.g., 'jquery' -> 'lib!jquery')
```

This init file is loaded before all tests; it initializes the AMD loader environment.

### Running Tests

**Build and run tests:**
```bash
npm run build-test
# Equivalent to: grunt test
```

This task:
1. Compiles frontend assets (transpiles, bundles)
2. Launches jasmine-browser-runner
3. Opens browser spec runner on localhost
4. Runs all specs in `frontend/test/spec/`

**Individual test file:** Not easily runnable in isolation (depends on full build)

### Test File Naming & Structure

- **Naming:** `test.{feature}.js` (e.g., `test.email-helper.js`, `test.nexus-settings.js`)
- **Pattern:** `describe('feature-name', () => { ... })`
- **Lifecycle:** `beforeAll(done => { ... })` to load modules, `it('assertion', () => { ... })` for specs

### Module Loading in Tests

**Pattern:** Async require with callback
```js
describe('email-helper', () => {
    let EmailHelper, diModule;

    beforeAll(done => {
        require(
            ['email-helper', 'di', 'models/user', 'acl-manager'],
            (EmailHelperClass, di, UserClass, AclManagerClass) => {
                // EspoCRM's AMD loader auto-unwraps .default export
                // So EmailHelperClass is the class itself, NOT {default: Class}
                EmailHelper = EmailHelperClass;
                diModule = di;
                done();  // signal test suite to proceed
            }
        );
    });
});
```

### Mocking Approach

**Plain objects:** Create mock objects with required methods/properties
```js
const mockUser = {
    attributes: {emailAddressList: []},
    get: key => null,
};

const mockLanguage = {
    translate: (key) => key,
};

const mockAcl = {
    checkTeamAssignmentPermission: teamId => true,
};
```

**DI container injection:** Set mocks in the shared container
```js
diModule.container.set(LanguageClass, mockLanguage);
diModule.container.set(UserClass, mockUser);

const helper = new EmailHelper();  // constructor injects from container

diModule.container.clear();  // CRITICAL: clear after each test to avoid state leaks
```

**Factory helpers:** Create reusable fixtures
```js
function createHelper(userAttrs = {}, userGetMap = {}) {
    const mockUser = { attributes: {...}, get: key => userGetMap[key] };
    // ... setup DI, create, cleanup
    return helper;
}

function createModel(attrs = {}) {
    return { id: '1', attributes: {...defaults, ...attrs}, get: key => ... };
}
```

### Test Conventions

1. **Named describe blocks:** Each feature/method gets a describe group
   ```js
   describe('#parseNameFromStringAddress', () => {
       it('parses name from formatted address', () => { ... });
       it('returns null when no angle brackets', () => { ... });
   });
   ```

2. **Focused assertions:** One main expectation per `it()`
   ```js
   it('sets repliedId to model id', () => {
       const model = createModel();
       const attrs = helper.getReplyAttributes(model, false);
       expect(attrs.repliedId).toBe('model-id-1');
   });
   ```

3. **Test data isolation:** Use `HARNESS_` prefix for any test-specific data (if persisted)

4. **Cleanup:** Always clear DI container after tests to prevent cross-test contamination

### Current Test Coverage

**Frontend specs:** `frontend/test/spec/`
- `test.email-helper.js` — 16 specs (email address parsing, reply attributes)
- `test.nexus-settings.js` — NEXUS panel UI tests
- `test.nexus-assistant.js` — NEXUS assistant view tests
- `test.file-field-view.js` — File capture tests
- Other core utilities: router, cache, model, collection, metadata, ACL manager, etc.

**Total:** 16 test files, 101+ individual test cases

---

## CI/CD Integration

### GitHub Actions

**File:** `.github/workflows/test.yml`
- **Trigger:** Push to `master`/`fix` branches, or PR with `.php`/`.json`/`.yml` changes
- **Matrix:** PHP 8.3, 8.4, 8.5
- **Steps:**
  1. Checkout code
  2. Setup PHP + Composer v2 (memory_limit=1024M)
  3. Cache vendor (keyed by composer.lock)
  4. Run `vendor/bin/phpstan` (static analysis)
  5. Run `vendor/bin/phpunit ./tests/unit` (unit tests only)

**Note:** Integration tests run separately in `.github/workflows/test-integration.yml` (requires DB setup)

### Static Analysis

**PHPStan:** Configured in `phpstan.neon`
- Level 8 (strict)
- Analyzes `application/` directory
- Excludes module `vendor/` directories
- Uses custom extension for EntityManager return types

**Run locally:**
```bash
npm run sa
# or
vendor/bin/phpstan
```

---

## Testing Best Practices

1. **Mock external dependencies:** Never hit real APIs, databases, or filesystems in tests
2. **Clear container after tests:** In JS, always `diModule.container.clear()` to prevent state pollution
3. **Use helper methods:** Reduce duplication with factory functions (createHelper, createModel, etc.)
4. **Document test intent:** Clear method names and comments explain what is being tested
5. **Avoid test interdependencies:** Each test should run independently
6. **Type hints in PHP tests:** Even though tests, use `@var` annotations for IDE support
7. **Assertion messages:** Include meaningful failure messages in assertions
8. **Build before frontend tests:** Always `npm run build-test` — tests require compiled assets

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| PHPUnit test fails with "Class not found" | Check namespace matches directory structure; phpunit.xml bootstrap must load Composer autoload |
| Jasmine test hangs in `beforeAll` | `require()` callback not calling `done()` — check async module loading |
| DI container state leaks between tests | Forgot `diModule.container.clear()` after previous test — add to test cleanup |
| Frontend tests fail after code change | Run `npm run build-test` to recompile; browser cache may be stale |
| "AMD module not found" in browser | Check module name in aliasMap in `frontend/test/init.js`; ensure named IDs match file paths |

---

## Adding New Tests

**For a new PHP class:**
1. Create `tests/unit/Espo/Path/To/ClassNameTest.php`
2. Extend `PHPUnit\Framework\TestCase`
3. Use `createMock()` for dependencies
4. Follow existing test method naming: `testWhatItDoes()`

**For a new JavaScript module:**
1. Create `frontend/test/spec/test.feature-name.js`
2. Use `describe()` blocks to group related specs
3. Load module via `require(['module-path'], (ModuleClass) => { ... })`
4. Create test fixtures with plain objects
5. Set/clear DI container for isolation
6. Run `npm run build-test` to execute
