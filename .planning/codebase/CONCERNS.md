# EspoCRM Codebase — Technical Concerns

**Documented:** 2026-06-01

## High Severity

### 1. NEXUS API Routes Missing Authentication Guards
**File:** `application/Espo/Modules/Nexus/Resources/routes.json` (lines 1–58)
**File:** `application/Espo/Modules/Nexus/Controllers/NexusGateway.php` (lines 23–153)
**Issue:** All NEXUS routes (`/nexus/health`, `/nexus/settings`, `/nexus/chat`, `/nexus/submit`, `/nexus/status/:jobId`, `/nexus/result/:jobId`) lack explicit authentication/authorization metadata in routes.json. Routes file contains no `authorization` or `auth` fields. If EspoCRM framework relies on default ACL inheritance, unauthenticated users may access sensitive chat/job submission endpoints.
**Risk:** Unauthorized job submission, information leakage via chat responses, settings modification.
**Action:** Audit EspoCRM's API authentication layer (Core/Api/Auth.php) to confirm default-deny behavior. Add explicit authorization guards if framework doesn't enforce user context by default.

### 2. Ratchet Pinned to Development Version
**File:** `composer.json`, line 33
**Issue:** `cboden/ratchet` pinned to `0.4.x-dev#9ac6a412a26d8bae6541ee0b65ffaf78e316b1a6` (development commit hash). No stable release is available; this is a legacy WebSocket library with known security vulnerabilities (CVE-2021-21289 in versions pre-0.4.4).
**Risk:** WebSocket handler crash on malformed input; potential DoS or privilege escalation if framework uses WebSocket for session/auth.
**Action:** Investigate whether Ratchet is actively used (search codebase for `Ratchet\` namespaces). If yes, evaluate migration to `php-webdriver` or `symfony-http-foundation` for WebSocket fallback. If no, remove the dependency.

### 3. Sparse NEXUS Error Handling and Timeout Risks
**File:** `application/Espo/Modules/Nexus/Services/NexusService.php`, lines 47–74 (checkHealthRaw)
**File:** `application/Espo/Modules/Nexus/Controllers/NexusGateway.php`, lines 57–85 (postActionChat)
**Issue:** 
- `checkHealthRaw()` silently returns empty array on curl failure, making service health opaque.
- `postActionChat()` sets timeout to 200s via `set_time_limit()` but doesn't validate NEXUS response structure before sending to client. Malformed JSON or partial responses propagate directly.
- Curl operations don't set `CURLOPT_SSL_VERIFYPEER` or `CURLOPT_SSL_VERIFYHOST`, risking MITM attacks on Pi-to-NEXUS traffic if not on loopback.
**Risk:** Silent failures hard to debug on Pi. Unvalidated responses sent to client. Credential leakage if NEXUS cert is untrusted and MITM occurs.
**Action:** 
- Add response schema validation before client delivery.
- Log curl errors at warn level (not silently swallow).
- Add `CURLOPT_SSL_VERIFY_PEER => false` comment documenting loopback assumption.

### 4. NexusAuth Token Cache on Shared Filesystem
**File:** `application/Espo/Modules/Nexus/Services/NexusAuth.php`, lines 27–28, 152–162
**Issue:** JWT tokens cached in `/tmp/nexus_espo_token_*.json` with UNIX file permissions (world-readable unless umask is 0077). In multi-user environments or containers, other processes may read the cached token.
**Risk:** Token hijacking, unauthorized NEXUS operations.
**Action:** Cache in a restricted directory (e.g., `data/cache/` with 0600 perms), or store in-memory with short TTL + invalidation on error.

## Medium Severity

### 1. Large Monolithic Classes Difficult to Test
**Files:**
- `application/Espo/ORM/QueryComposer/BaseQueryComposer.php` (3,819 lines)
- `application/Espo/ORM/Mapper/BaseMapper.php` (1,748 lines)
- `application/Espo/Core/Record/Service.php` (1,745 lines)
**Issue:** Classes exceed 1,000 lines, making unit testing and refactoring high-friction. `BaseQueryComposer` has documented TODOs (line 65–66) acknowledging the design debt.
**Risk:** Changes to query building logic have high regression risk. Coverage gaps in edge cases.
**Action:** Defer major refactors to future phase; document test-case patterns for critical branches (JoinType, WhereClause conversions).

### 2. Deprecated Ratchet Legacy + Unclear WebSocket Usage
**File:** `application/Espo/Core/WebSocket/` (6 files)
**Issue:** WebSocket binding in `application/Espo/Binding.php` (lines not shown) indicates WebSocket support, but Ratchet is in dev-only state and no clear fallback strategy if ZeroMQ subscriptions fail.
**Risk:** WebSocket disconnects may leave broadcast listeners stale. No recovery documented.
**Action:** Document ZeroMQ + WebSocket interaction diagram; add metrics to QueuePoller for listener health.

### 3. Missing Tests for Nexus Integration Module
**File:** `application/Espo/Modules/Nexus/`
**Issue:** The NEXUS module (chat, queue, RAG services) has 43 PHP + 27 JS tests per memory, but integration tests between NexusService and NexusGateway are sparse. No mocking of NEXUS HTTP responses in unit tests.
**Risk:** Regression if NEXUS API contract changes (e.g., response schema).
**Action:** Add integration test suite mocking NEXUS responses; validate error handling paths.

### 4. Pinned Package Versions Without Rotation Policy
**File:** `composer.json`; `package.json`
**Issue:** 
- Several dependencies use exact pinned versions (e.g., `bootstrap-colorpicker ^2.5.2`, `autobahn-espo`, custom GitHub forks).
- No maintenance schedule for dependency updates; risk of accumulating security patches lag.
**Risk:** Vulnerable transitive dependencies go unpatched until manual audit.
**Action:** Implement quarterly dependency audit; use Composer security vulnerability checker (`composer audit`).

## Low Severity

### 1. Single TODO in Query Checker
**File:** `application/Espo/Core/Select/Where/Checker.php`, line 240
**Issue:** Comment `// TODO allow alias` suggests incomplete alias resolution in WHERE clause validation.
**Risk:** Edge-case query failures if user-defined aliases are not yet supported.
**Action:** Document whether alias feature is planned; if not, remove TODO.

### 2. Formula Parser Complexity
**File:** `application/Espo/Core/Formula/Parser.php` (1,539 lines)
**Issue:** Large state machine for formula parsing; unclear performance characteristics on deeply nested formulas.
**Risk:** DoS potential if user submits pathologically deep formula.
**Action:** Add depth limit on formula AST; benchmark on large workloads.

### 3. Import Tool Synchronization
**File:** `application/Espo/Tools/Import/Import.php` (1,537 lines)
**Issue:** Complex multi-step import workflow; potential for partial failures if system crashes mid-transaction.
**Risk:** Orphaned import records; data inconsistency if job is retried.
**Action:** Ensure import transactions are atomic or implement idempotent job resumption.

### 4. Curl Usage Without Abstraction
**File:** Multiple files: `NexusService.php`, `NexusAuth.php`
**Issue:** Raw `curl_*` calls throughout; no HTTP client abstraction (no Guzzle or PSR-18 client used).
**Risk:** Inconsistent error handling, timeout tuning scattered across codebase.
**Action:** Consolidate HTTP calls into a dedicated client class or use `guzzlehttp/guzzle` (already in composer.json).

## Known Gaps (Not Bugs)

- **QueuePoller job state persistence:** `application/Espo/Modules/Nexus/Jobs/QueuePoller.php` (line 14) documents that NexusJob entity is planned for v1.1; current poller is stateless.
- **NEXUS RAG memory lifecycle:** No documented cleanup policy for old chat sessions in ChromaDB.
- **PI network resilience:** No circuit-breaker documented for NEXUS unreachability; only health checks in QueuePoller.
