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
