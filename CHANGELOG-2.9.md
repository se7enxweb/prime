CHANGELOG for 7x Prime 2.9
==========================

This file documents all notable changes between 7x Prime 2.9 (forked from
Symfony 2.8.52) and the original upstream Symfony 2.8.x series.

7x Prime is an independent project maintained by 7x (se7enx.com).
It is NOT affiliated with Fabien Potencier or the upstream Symfony project.

---

2.9.0 (2026-05-10)
-------------------

### Product / Identity

 * Rebranded from **Symfony (v2)** to **7x Prime (v2.9)**.
 * `composer.json`: package name changed from `symfony/symfony` to
   `se7enxweb/prime`.
 * Homepage set to `https://se7enx.com`.
 * Authors: 7x (se7enxweb) <info@se7enx.com> added as primary author;
   Fabien Potencier and contributors remain credited.

### PHP 8 Compatibility

 * **Minimum PHP raised to ^8.0** (was `>=5.3.9`).  PHP 8.5.6 is the
   tested and recommended runtime.
 * Fixed ~330 implicit nullable parameter declarations across the entire
   `src/` tree (`Type $param = null` → `?Type $param = null`).  Affected
   components include (but are not limited to):
   - HttpFoundation (Cookie, Response, HeaderBag, Session)
   - HttpKernel (all DataCollectors, all HTTP Exceptions)
   - Security (Core & Http exceptions)
   - DependencyInjection, Config, Console, Filesystem, Finder, Form,
     Routing, Translation, VarDumper, Yaml
 * Fixed PHP 8 reserved-keyword class names in the Validator component:
   - `Symfony\Component\Validator\Constraints\False` — replaced with
     `class_alias(IsFalse::class, …)`.
   - `Symfony\Component\Validator\Constraints\Null`  — replaced with
     `class_alias(IsNull::class, …)`.
   - `Symfony\Component\Validator\Constraints\True`  — replaced with
     `class_alias(IsTrue::class, …)`.
 * Fixed `$GLOBALS` pass-by-reference in
   `VarDumper/Tests/CliDumperTest.php` (forbidden in PHP 8.1+).
 * Removed all PHP 5.x version-guard dead code from
   `NativeSessionStorage` (`PHP_VERSION_ID` blocks for PHP < 5.4 / 5.5).

### Dependency Changes

 * Replaced `twig/twig` with `se7enxweb/twig` (`~1.34|~2.4`).
 * Removed obsolete polyfill packages that are no-ops on PHP 8:
   - `symfony/polyfill-php54`
   - `symfony/polyfill-php55`
   - `symfony/polyfill-php56`
   - `symfony/polyfill-php70`
   - `symfony/polyfill-apcu`
   - `symfony/polyfill-util`
 * `branch-alias` updated: `dev-2.9 → 2.9-dev` (was `dev-master → 2.8-dev`).

### Security Fixes

 * **YAML — PHP object injection (CWE-502):**
   `Yaml/Inline.php` now passes `['allowed_classes' => false]` to
   `unserialize()` when deserialising `!php/object:` or `!!php/object:`
   tags.  This prevents gadget-chain exploitation even when
   `$objectSupport` is `true`.

 * **Cookie SameSite attribute (CWE-352 — CSRF):**
   `HttpFoundation/Cookie.php` now accepts a `$sameSite` constructor
   parameter (default `'Lax'`).  The `__toString()` serialisation and the
   `Response` `setcookie()` call both honour this value, including PHP 8's
   options-array form of `setcookie()`.

 * **HTTP response splitting — CRLF injection (CWE-113):**
   `HttpFoundation/HeaderBag::set()` now strips `\r` and `\n` from all
   header values before storage, eliminating HTTP response-splitting
   vectors.

 * **Session hardening:**
   `NativeSessionStorage::start()` now calls `ini_set()` before
   `session_start()` to enforce:
   - `session.cookie_httponly = 1`
   - `session.cookie_samesite = Lax`
   - `session.use_strict_mode = 1`

### PHP 8.5 / PHPUnit 11 Compatibility (test suite — zero failures/errors)

The following fixes were made to vendor libraries and test files to achieve a
clean PHPUnit 11.5 run under PHP 8.5.6 (0 errors, 0 failures, 0 warnings,
0 deprecations attributable to framework code).

#### ZendFramework Bridge (zend-code)

 * **ParameterReflection::getClass()** — eliminated infinite recursion: the
   method previously called `$this->getType()`, which called `$this->getClass()`
   in turn.  Fixed by delegating to the native `parent::getType()` (i.e.
   `ReflectionParameter::getType()`).
 * **ParameterReflection::getType()** — removed calls to the PHP 8.1-deprecated
   `ReflectionParameter::isArray()`, `isCallable()`, and `getClass()` methods.
   Introspection now uses `parent::getType()` exclusively.
 * **ClassReflection::getStartLine()** — added `#[\ReturnTypeWillChange]`
   attribute to suppress the PHP 8.1 covariant-return-type deprecation notice.

#### OcramiusProxyManager

 * **ParameterGenerator::extractParameterType()** — replaced deprecated
   `isArray()` / `isCallable()` / `getClass()` calls with `getType()`-based
   introspection for PHP 8.1+ compatibility.
 * **ParameterGenerator::getGeneratedType()** — corrected handling of the
   nullable-type prefix (`?`) when generating proxy method signatures.
 * **SetProxyInitializer (LazyLoadingGhost)** — changed `setType('Closure')`
   to `setType('?Closure')`: the `$initializer` property is nullable and PHP
   8 enforces that the declared type matches.
 * **SetProxyInitializer (LazyLoadingValueHolder)** — same nullable Closure
   fix as above.

#### DoctrineBridge (doctrine/common — ProxyGenerator)

 * **ProxyGenerator template** — updated the generated proxy class stub to
   declare `$initializer` and `$cloner` as `?\Closure` (nullable) instead of
   `\Closure`, matching PHP 8 property-type strictness for null-initialised
   properties.

#### SensioFrameworkExtraBundle

 * **ParamConverterListener** — replaced all uses of the PHP 8.1-deprecated
   `ReflectionParameter::getClass()` with `getType()`-based logic throughout
   the listener, including handling of union types and built-in types.

#### DoctrineDBAL (doctrine/dbal — PDOSqlite)

 * **PDOSqlite\\Driver** — PHP 8.4 split the monolithic `PDO` class into
   dedicated driver subclasses (e.g. `Pdo\Sqlite`).  The driver now detects
   `instanceof \Pdo\Sqlite` in addition to the legacy path, and falls back
   gracefully via `@$pdo->sqliteCreateFunction()` when the method may not
   exist.

#### DoctrineORM

 * **SqlWalker** — corrected `$resultAlias` handling: the variable correctly
   stays `null` when not set; null-coalescing (`?? ''`) is applied only at
   the array-access points in `scalarResultAliasMap`, preventing lookup
   corruption that caused 26 test errors.
 * **UnitOfWork** — reverted an erroneous `continue 2` (which targeted a
   non-existent outer loop level) back to `break` inside a `switch` statement
   nested within a `foreach`.

#### Process Component

 * **UnixPipes::readPipes()** — prefixed `fread()` with the error-suppression
   operator (`@fread(...)`) to silence the `EAGAIN` / EIO (errno=5) notice
   that PHP 8.5 emits when reading from a closed PTY file descriptor during
   process teardown.

#### Finder Component (tests)

 * **SortableIteratorTest** — replaced `file_get_contents(self::toAbsolute('.git'))`
   with `touch(self::toAbsolute('.git'))`.  Reading a directory path via
   `file_get_contents()` triggers a deprecation/warning in PHP 8.5 and was
   semantically wrong (the test only needed the path to exist, not its
   contents).

#### Translation Component (tests)

 * **JsonFileDumperTest** — removed an obsolete `if (PHP_VERSION_ID < 50400)`
   guard that skipped the entire test on PHP < 5.4.  The `json_encode()`
   flags parameter has been supported since PHP 5.3.0; under PHP 8.5 the
   guard evaluated to `false` and the test body was never reached, causing
   it to be permanently marked incomplete.

#### PropertyInfo Component (tests)

 * **PhpDocExtractorTest / OmittedParamTagTypeDocBlock** — moved the
   `OmittedParamTagTypeDocBlock` class out of the test file and into its own
   dedicated fixture file (`Tests/Fixtures/OmittedParamTagTypeDocBlock.php`).
   The phpdocumentor/reflection library uses a PHP 5-era PHPParser (v1.x) to
   parse source files for docblock extraction.  When it was directed at the
   test file — which contains the PHP 8.0 attribute syntax
   `#[\PHPUnit\Framework\Attributes\DataProvider(...)]` — it caught a
   `PHPParser_Error` and echoed `Parse Error: Syntax error, unexpected
   T_NS_SEPARATOR, expecting T_FUNCTION on line 33` to stdout, polluting the
   PHPUnit progress output.  The new fixture file contains only PHP 5-
   compatible syntax, eliminating the parse-error noise entirely.
