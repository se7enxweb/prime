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
