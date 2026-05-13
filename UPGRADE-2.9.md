UPGRADE FROM 2.8 TO 2.9 (7x Prime)
=====================================

7x Prime 2.9 is the first release of the 7x fork of Symfony 2.8.52.
It targets PHP 8.0+ and hardens several security areas.  Most existing
Symfony 2.8 applications will work with only minor adjustments.

---

### PHP Requirement

 * **PHP ^8.0 is now required.**  PHP 5.x and PHP 7.x are no longer
   supported.  PHP 8.5.6 is the tested and recommended runtime.

   **Before upgrading**, ensure your server runs PHP 8.0 or newer:

   ```
   php -v
   ```

### Composer / Dependencies

 * Remove any of the following from your `require` if they appear there —
   they are no-ops on PHP 8 and have been removed from the framework:

   ```json
   "symfony/polyfill-php54": "*",
   "symfony/polyfill-php55": "*",
   "symfony/polyfill-php56": "*",
   "symfony/polyfill-php70": "*",
   "symfony/polyfill-apcu": "*",
   "symfony/polyfill-util": "*"
   ```

 * `twig/twig` has been replaced by `se7enxweb/twig`.  If your project
   directly requires `twig/twig`, replace it:

   ```json
   "se7enxweb/twig": "~1.34|~2.4"
   ```

### HttpFoundation — Cookie

 * `Cookie::__construct()` has a new last parameter `$sameSite`
   (default `'Lax'`):

   ```php
   // Before
   new Cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);

   // After (backward-compatible; $sameSite defaults to 'Lax')
   new Cookie($name, $value, $expire, $path, $domain, $secure, $httpOnly, 'Lax');
   ```

 * **Cookies now include `SameSite=Lax` by default.**  If your application
   relies on cookies being sent with cross-site POST requests (e.g. for
   OAuth callbacks or third-party form submissions), you must explicitly
   set `$sameSite = 'None'` **and** `$secure = true`:

   ```php
   new Cookie($name, $value, 0, '/', null, true, true, 'None');
   ```

### HttpFoundation — Headers

 * `HeaderBag::set()` now strips `\r` and `\n` from header values.
   If any header value in your application intentionally contained
   newlines (which would itself be a security issue), that content will
   now be silently cleaned.

### Session

 * `NativeSessionStorage::start()` now calls `ini_set()` to enforce
   `session.cookie_httponly = 1`, `session.cookie_samesite = Lax`, and
   `session.use_strict_mode = 1` before calling `session_start()`.
   If your `php.ini` or application code already sets these values to
   something different, the framework values take precedence for the
   session started by this storage class.

### YAML — Object Deserialisation

 * `Yaml::parse()` with `objectSupport: true` and `!php/object:` /
   `!!php/object:` tags now passes `allowed_classes: false` to
   `unserialize()`.  **PHP objects can no longer be deserialised from
   YAML**, even with `objectSupport` enabled.  This closes a PHP object
   injection vector (CVE-class: CWE-502).

   If you relied on round-tripping PHP objects through YAML (rare in
   production), you must replace that workflow with a safe serialisation
   format such as JSON.

### Validator Constraints — Reserved Keywords

 * The classes `Symfony\Component\Validator\Constraints\False`,
   `Symfony\Component\Validator\Constraints\Null`, and
   `Symfony\Component\Validator\Constraints\True` were reserved keywords
   in PHP 8 and their class declarations have been replaced with
   `class_alias()` calls.

   **If you reference these by string class name** (e.g. in XML/YAML
   constraint config), no change is needed — the aliases preserve full
   backward compatibility.

   **If you reference them in PHP code**, prefer the canonical names:

   ```php
   // Deprecated (still works via alias)
   use Symfony\Component\Validator\Constraints\False;
   use Symfony\Component\Validator\Constraints\Null;
   use Symfony\Component\Validator\Constraints\True;

   // Preferred
   use Symfony\Component\Validator\Constraints\IsFalse;
   use Symfony\Component\Validator\Constraints\IsNull;
   use Symfony\Component\Validator\Constraints\IsTrue;
   ```

### Implicit Nullable Types

 * Hundreds of method signatures across the framework have had their
   nullable parameters made explicit (`Type $p = null` → `?Type $p = null`).
   This is required by PHP 8.4+.  No behaviour change, but if you
   **extend** framework classes and **override** affected methods, your
   override signatures must match:

   ```php
   // Before (PHP 8.3 deprecation, PHP 8.4+ error)
   public function setExpires(\DateTime $date = null) {}

   // After
   public function setExpires(?\DateTime $date = null) {}
   ```

   Run `php -l` on your own extension classes and update accordingly.

---

PHP 8.4 / PHP 8.5 Vendor Compatibility Notes
---------------------------------------------

The following vendor libraries shipped with 7x Prime 2.9 have been patched
for PHP 8.1–8.5 compatibility.  No action is required for applications that
use these libraries only through the Symfony/7x API.  The notes below are
relevant if you **directly extend or instantiate** the patched vendor classes.

### ZendFramework Bridge — ParameterReflection

`ParameterReflection::getType()` and `ParameterReflection::getClass()` no
longer call the PHP 8.1-deprecated `ReflectionParameter::isArray()`,
`isCallable()`, or `getClass()` methods.  If you subclass
`Zend\Code\Reflection\ParameterReflection` and override these methods, ensure
your overrides also avoid the deprecated calls.

`ParameterReflection::getClass()` delegates to `parent::getType()` (the native
`ReflectionParameter`) to avoid infinite recursion.  Any subclass that
previously relied on `$this->getType()` calling back into `getClass()` will
see different (corrected) behaviour.

### OcramiusProxyManager — Proxy Initializer Properties

Generated proxy classes now declare `$initializer` and `$cloner` as
`?\Closure` (nullable Closure) rather than `\Closure`.  Existing serialised
proxy objects or hand-written stubs that expect a non-nullable `\Closure`
type declaration will fail a strict type check under PHP 8.  Regenerate any
cached proxies after upgrading.

The `ParameterGenerator` class now uses `ReflectionParameter::getType()`
instead of `isArray()` / `isCallable()` / `getClass()`.  Proxy method
signatures for nullable and union-typed parameters are generated correctly
under PHP 8.1+.

### DoctrineBridge — ProxyGenerator

The ProxyGenerator template has been updated: generated proxy stubs declare
`$initializer` and `$cloner` as `?\Closure` (nullable).  Delete your Doctrine
proxy cache directory after upgrading so stale proxies are regenerated:

```
rm -rf var/cache/*/doctrine/orm/Proxies/*
```

### DoctrineDBAL — SQLite Driver

The SQLite PDO driver now detects the PHP 8.4 `Pdo\Sqlite` subclass in
addition to the legacy `PDO` instance.  No application-level change is
needed.  If you use `sqliteCreateFunction()` directly on the connection's PDO
instance, note that the call is now guarded with `@` to suppress errors when
the method is unavailable on non-SQLite connections.

### DoctrineORM — SqlWalker / UnitOfWork

These are internal fixes with no public API impact.  If you extend
`SqlWalker` and access `$resultAlias` directly, note that it is now
correctly `null` (not `''`) when no alias is present — null-coalescing is
applied only at array-access sites.

### SensioFrameworkExtraBundle — ParamConverterListener

`ParamConverterListener` no longer calls the PHP 8.1-deprecated
`ReflectionParameter::getClass()`.  If you implement a custom
`ParamConverterInterface` and rely on `ParamConverter` receiving a
`ReflectionClass` object from `getClass()`, verify your converter still
works correctly — the listener now derives the class from
`ReflectionNamedType::getName()` instead.

### Process Component — PTY Reads

The `UnixPipes` class now uses `@fread()` to suppress EIO (errno=5) notices
on PTY file descriptors.  This is an internal implementation detail.  If you
have custom error handlers that trap `E_NOTICE` / `E_WARNING` for all
`fread()` calls, the PTY-related notices will no longer reach your handler.

### PropertyInfo — PhpDocExtractor Test Fixture

`OmittedParamTagTypeDocBlock` has been moved from the test file into
`Tests/Fixtures/OmittedParamTagTypeDocBlock.php`.  If you reference this
class by its old fully-qualified name
`Symfony\Component\PropertyInfo\Tests\PhpDocExtractors\OmittedParamTagTypeDocBlock`,
update references to
`Symfony\Component\PropertyInfo\Tests\Fixtures\OmittedParamTagTypeDocBlock`.
(This class is a test fixture only; no production code references it.)

---

PrimeBundleMigration — Auto-Fix Commands
-----------------------------------------

7x Prime ships `PrimeMigrateBundle` with auto-fix commands to assist the
migration from Symfony 2.x applications.  All commands are in the
`prime:migrate:*` namespace.

### Available commands

| Command | Scan | `--fix` | Purpose |
|---------|:----:|:-------:|---------|
| `prime:migrate:check` | ✓ | — | Run all checks; print consolidated summary |
| `prime:migrate:nullable` | ✓ | ✓ | Fix implicit nullable types (`Type $p = null` → `?Type $p = null`) |
| `prime:migrate:forms` | ✓ | ✓ | Replace string form type aliases with FQCN class constants |
| `prime:migrate:constraints` | ✓ | ✓ | Replace `Constraints\True/False/Null` with `IsTrue/IsFalse/IsNull` |
| `prime:migrate:twig` | ✓ | ✓ | Replace `Twig_*` legacy class names with `Twig\…` PSR-4 equivalents |
| `prime:migrate:yaml` | ✓ | ✓ | Strip `!php/object:` YAML tags (leaves value as plain string) |
| `prime:migrate:report` | ✓ | — | Generate text / HTML / JSON migration status report |

### General pattern

Every fixable command follows the same three-mode contract:

```bash
# 1. Read-only scan — see what needs fixing
php bin/console prime:migrate:<command> --dir=src/

# 2. Dry run — preview changes without writing any file
php bin/console prime:migrate:<command> --dir=src/ --fix --dry-run

# 3. Apply — write the changes
php bin/console prime:migrate:<command> --dir=src/ --fix
```

Always commit your work-in-progress before running `--fix` and review the
result with `git diff src/` before committing.

### YAML `--fix` note

`prime:migrate:yaml --fix` strips the `!php/object:` tag, leaving each value
as a plain YAML string.  This is the mechanical part of the fix.  After
applying it, also update any application code that previously relied on the
value being a PHP object — those code paths must now call `unserialize()`
explicitly.

### Twig `--fix` note

`prime:migrate:twig --fix` rewrites every `Twig_*` reference that appears in
the 19-entry known-replacement map.  References to names **not** in the map
are left unchanged and reported as requiring manual review.  Run
`prime:migrate:twig --dir=src/` (scan mode) after `--fix` to confirm no
unhandled references remain.

### Forms `--fix` note

`prime:migrate:forms --fix` rewrites string type aliases to FQCN constants
**and** injects the required `use` statements.  The `use` injection anchors
on top-level `use` lines only (column 0), so it will not misfire inside class
bodies that contain `use TraitName;` statements.
