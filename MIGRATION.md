# Migrating from Symfony 2.x to 7x Prime (v2.9)

> This guide is for developers who have an existing Symfony 2.x application and want to run it
> on **7x Prime (v2.9)** — the PHP 8.x-compatible fork of Symfony 2.8.52 maintained by
> [7x (se7enx.com)](https://se7enx.com/).
>
> 7x Prime preserves the Symfony 2.x API almost entirely. The vast majority of applications
> require only the changes listed in this document — most of which are already required by
> PHP 8.x itself regardless of which framework fork you use.

---

## Table of Contents

1. [What is 7x Prime?](#1-what-is-7x-prime)
2. [Migration At a Glance](#2-migration-at-a-glance)
3. [Step 1 — PHP Version](#3-step-1--php-version)
4. [Step 2 — Update composer.json](#4-step-2--update-composerjson)
5. [Step 3 — Nullable Parameter Types](#5-step-3--nullable-parameter-types)
6. [Step 4 — Cookies and SameSite](#6-step-4--cookies-and-samesite)
7. [Step 5 — Session Hardening](#7-step-5--session-hardening)
8. [Step 6 — CRLF / Header Injection](#8-step-6--crlf--header-injection)
9. [Step 7 — YAML Object Deserialisation](#9-step-7--yaml-object-deserialisation)
10. [Step 8 — Validator Constraint Names](#10-step-8--validator-constraint-names)
11. [Step 9 — Form Type API](#11-step-9--form-type-api)
12. [Step 10 — Twig Dependency](#12-step-10--twig-dependency)
13. [Step 11 — Removed Polyfills](#13-step-11--removed-polyfills)
14. [Step 12 — Deprecated Symfony 2.x APIs](#14-step-12--deprecated-symfony-2x-apis)
15. [Step 13 — Run the Test Suite](#15-step-13--run-the-test-suite)
16. [Step 14 — Web Server and Front Controller](#16-step-14--web-server-and-front-controller)
17. [Step 15 — Deployment and Security Checklist](#17-step-15--deployment-and-security-checklist)
18. [Quick Reference — What Changed](#18-quick-reference--what-changed)
19. [Getting Help](#19-getting-help)
20. [PHP 8.5 & PHPUnit 11 — Compatibility Timeline](#20-php-85--phpunit-11--compatibility-timeline)
21. [After Installation — What To Do Next](#21-after-installation--what-to-do-next)
22. [PrimeBundleMigration Helper Commands](#22-primebundlemigration-helper-commands)

---

## 1. What is 7x Prime?

7x Prime is an independent fork of **Symfony 2.8.52** (the final upstream release) maintained
by [7x (se7enx.com)](https://se7enx.com/). The fork:

- Raises the PHP requirement to **^8.0** and has been tested on PHP 8.5.6.
- Fixes all PHP 8.x deprecations and fatal errors across the framework source.
- Adds security hardening (SameSite cookies, CRLF protection, YAML injection prevention,
  session hardening).
- Replaces `twig/twig` with `se7enxweb/twig` and removes dead polyfill packages.
- Is published as `se7enxweb/prime` on Packagist.

The Symfony 2.x API is **preserved**. If your application already runs on Symfony 2.8.52
without deprecation warnings, the migration to 7x Prime is minimal.

---

## 2. Migration At a Glance

| Area | Change required | Effort |
|------|----------------|--------|
| PHP version | Upgrade server to PHP ^8.0 | Server config |
| `composer.json` | Change package name and Twig dep | 2 lines |
| Nullable parameters | Add `?` to your method overrides | Automated or per-file |
| Cookies | Audit cross-site cookie usage | Low–medium |
| Sessions | No code change (framework enforces) | None |
| Header values | Remove intentional `\r\n` in headers | Rare |
| YAML | Remove PHP object serialisation round-trips | Rare |
| Validator constraints | Rename `False`/`Null`/`True` uses | Low |
| Form types | Use FQCN instead of string aliases | Low–medium |
| Polyfills | Remove from `composer.json` | 1 minute |
| Deprecated APIs | Fix any `E_USER_DEPRECATED` notices | Varies |

---

## 3. Step 1 — PHP Version

7x Prime requires **PHP 8.0 or higher**. PHP 8.5.6 is the tested and recommended runtime.

```bash
# Check your current PHP version
php -v

# Check that required extensions are loaded
php -m | grep -E 'mbstring|xml|intl|curl|pdo|opcache'
```

If your server runs PHP 5.x or PHP 7.x, you must upgrade PHP before proceeding.
The framework will not boot on PHP < 8.0.

> **PHP 8.x deprecations in your own code** — even before touching the framework, run your
> existing test suite under PHP 8.x and fix any `E_DEPRECATED` notices in your own classes.
> The most common are: `strlen()` on arrays, `stripos()` on non-strings, removed functions
> (`each()`, `create_function()`, `get_magic_quotes_gpc()`), and implicit nullable types
> (covered in Step 3).

---

## 4. Step 2 — Update composer.json

### 4.1 — Change the Package Name

The package has moved from `symfony/symfony` to `se7enxweb/prime`.

**Before:**

```json
"require": {
    "symfony/symfony": "2.8.*"
}
```

**After:**

```json
"require": {
    "se7enxweb/prime": "~2.9"
}
```

If your project required individual Symfony component packages (e.g. `symfony/http-foundation`,
`symfony/routing`) instead of the monolithic `symfony/symfony`, replace each one with the
corresponding path inside `se7enxweb/prime`. Because 7x Prime ships all components as one
repository (same as upstream), you only need the single `se7enxweb/prime` entry.

### 4.2 — Update the Twig Dependency

`twig/twig` has been replaced by `se7enxweb/twig`.

**Before:**

```json
"require": {
    "twig/twig": "~1.34|~2.0"
}
```

**After:**

```json
"require": {
    "se7enxweb/twig": "~1.34|~2.4"
}
```

### 4.3 — Update the PHP Constraint

```json
"require": {
    "php": "^8.0"
}
```

### 4.4 — Install

```bash
composer update
# or for a clean install from scratch:
composer install
```

---

## 5. Step 3 — Nullable Parameter Types

This is the most widespread change and the one most likely to affect your own code.

### Why it matters

PHP 8.4 deprecated implicit nullable types and PHP 8.x emits `E_DEPRECATED` notices for them.
The pattern is:

```php
// PHP < 8.4 accepted this silently:
public function setExpires(\DateTime $date = null) {}

// PHP 8.4+ requires explicit nullability:
public function setExpires(?\DateTime $date = null) {}
```

7x Prime has already fixed **~330 occurrences** in the framework source. You must fix the same
pattern in **any class of yours that extends or overrides a framework class**.

### How to find affected methods

```bash
# Find all implicit nullable params in your src/ directory
grep -rn '[,(]\s*[A-Za-z_\\][A-Za-z0-9_\\]*\s\+\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*null' src/

# More precise: only in method signatures (function keyword on same or previous line)
grep -rPn '(function\s+\w+\s*\(.*|,\s*)(?<!\?)[A-Za-z_\\][A-Za-z0-9_\\]+\s+\$\w+\s*=\s*null' src/
```

### Fix pattern

```php
// Before
public function foo(SomeClass $obj = null, string $name = null): void {}

// After
public function foo(?SomeClass $obj = null, ?string $name = null): void {}
```

### Automated fix (optional)

The `fix_nullable.php` script in the project root was used to apply this fix to the framework
source. You can run it against your own `src/` directory:

```bash
# Back up first
git stash  # or git commit your current state

# Run from the project root, pointing at your src directory
php fix_nullable.php
# (The script walks src/ recursively — move your code under src/ first if needed)
```

Review the diff with `git diff` and commit the result.

### Overriding framework methods

If your class extends a framework class and overrides a method, your signature must match the
updated (nullable) framework signature exactly. Mismatches produce `TypeError` on PHP 8+.

**Common framework methods you may have overridden:**

| Class | Method | Updated signature excerpt |
|-------|--------|--------------------------|
| `HttpFoundation\Cookie` | `__construct()` | `?string $domain = null`, `?string $sameSite = 'Lax'` |
| `HttpFoundation\Response` | `setExpires()` | `?\DateTimeInterface $date = null` |
| `HttpKernel\Exception\HttpException` | `__construct()` | `?\Throwable $previous = null` |
| `Security\Core\Exception\*` | `__construct()` | `?\Throwable $previous = null` |
| `DependencyInjection\*` | various setters | `?string $…`, `?array $…` |
| `Form\AbstractType` | `configureOptions()` | `OptionsResolver $resolver` (no change, but check parent calls) |
| `Routing\Route` | `setDefault()` | `mixed $default` |

Run `php -n -l YourClass.php` on each file after editing to confirm there are no syntax errors.

---

## 6. Step 4 — Cookies and SameSite

### What changed

`Cookie::__construct()` now has a new final parameter `$sameSite` defaulting to `'Lax'`:

```php
// Full updated signature
new Cookie(
    string  $name,
    ?string $value   = null,
    mixed   $expire  = 0,
    string  $path    = '/',
    ?string $domain  = null,
    bool    $secure  = false,
    bool    $httpOnly = true,
    ?string $sameSite = 'Lax'   // ← NEW (default: 'Lax')
);
```

### Impact on your application

**No change required** if your cookies are:
- Set on the same site only (normal session, CSRF, preferences cookies).
- The default `SameSite=Lax` behaviour is correct for same-site navigation and top-level
  POST requests from the same origin.

**Change required** if you use cookies that must be sent with cross-site requests, for example:
- OAuth 2.0 callback flows where the redirect originates from a third-party domain.
- Embedded widgets that issue cross-origin POST requests with credentials.
- Any third-party integration that reads your cookies from a different domain.

For those cookies, explicitly set `$sameSite = 'None'` **and** `$secure = true`:

```php
// Before (implicitly no SameSite attribute — browser defaults varied)
new Cookie('oauth_state', $token, 0, '/', null, false, true);

// After — cross-site cookie (requires HTTPS)
new Cookie('oauth_state', $token, 0, '/', null, true, true, 'None');
```

### Session cookie

The session cookie SameSite attribute is controlled by `NativeSessionStorage` (see Step 5),
not by `Cookie` directly. You do not need to change session cookie construction.

### Audit command

```bash
# Find all Cookie constructor calls in your codebase
grep -rn 'new Cookie(' src/ app/
```

---

## 7. Step 5 — Session Hardening

`NativeSessionStorage::start()` now calls `ini_set()` before `session_start()` to enforce:

| Setting | Enforced Value |
|---------|---------------|
| `session.cookie_httponly` | `1` |
| `session.cookie_samesite` | `Lax` |
| `session.use_strict_mode` | `1` |

### No code change needed

These are secure defaults. For the vast majority of applications no code change is required.

### When you may need to adjust

If your application intentionally requires a different session cookie `SameSite` value
(for example an SSO provider that needs `None`), configure it explicitly in `app/config/config.yml`:

```yaml
framework:
    session:
        cookie_samesite: "None"
        cookie_secure:   true    # required when SameSite=None
```

Or override `NativeSessionStorage` and call `ini_set()` yourself before delegating to `start()`.

---

## 8. Step 6 — CRLF / Header Injection

`HeaderBag::set()` now strips `\r` (carriage return) and `\n` (newline) characters from all
header values before storing them.

### No code change needed in normal applications

This is a security hardening measure. Stripping CRLF from header values is always correct
behaviour. The only case where you would need to act is if your code (incorrectly) placed
`\r\n` sequences into header values — which is itself a security vulnerability (HTTP response
splitting). Fix such code to never embed line endings in header values.

```php
// WRONG — was silently allowed before; now stripped:
$response->headers->set('X-Custom', "value\r\nX-Injected: evil");

// CORRECT — plain string, no line endings:
$response->headers->set('X-Custom', 'value');
```

---

## 9. Step 7 — YAML Object Deserialisation

### What changed

`Yaml::parse()` with `$objectSupport = true` and `!php/object:` / `!!php/object:` tags now
passes `['allowed_classes' => false]` to `unserialize()`. PHP objects **can no longer be
deserialised from YAML**, even when object support is explicitly enabled.

### Who is affected

Only applications that round-trip PHP objects through YAML files are affected. This pattern
is rare in production code. Typical YAML usage (configuration files, routing, fixtures with
scalar/array data) is completely unaffected.

### Fix

Replace PHP-object YAML serialisation with a safe data format.

**Before:**

```php
// Writing
$yaml = Yaml::dump(['obj' => $myObject]);   // used !php/object: tag

// Reading
$data = Yaml::parse($yaml, true);           // $objectSupport = true
$obj  = $data['obj'];                        // returned a PHP object
```

**After — use JSON:**

```php
// Writing
$json = json_encode(['obj' => $myObject->toArray()]);

// Reading
$data = json_decode($json, true);
$obj  = MyClass::fromArray($data['obj']);
```

**After — use a dedicated DTO / value object:**

```php
// Store only serialisable scalar data in YAML
$yaml = Yaml::dump(['title' => $myObject->getTitle(), 'id' => $myObject->getId()]);

// Reconstruct the object after parsing
$data = Yaml::parse($yaml);
$obj  = new MyClass($data['id'], $data['title']);
```

---

## 10. Step 8 — Validator Constraint Names

### What changed

Three Validator constraint class names collide with PHP 8 reserved keywords:

| Old class name | Status in PHP 8 | 7x Prime replacement |
|---------------|-----------------|---------------------|
| `Symfony\Component\Validator\Constraints\False` | Fatal — `false` is a keyword | `class_alias` to `IsFalse` |
| `Symfony\Component\Validator\Constraints\Null`  | Fatal — `null` is a keyword  | `class_alias` to `IsNull`  |
| `Symfony\Component\Validator\Constraints\True`  | Fatal — `true` is a keyword  | `class_alias` to `IsTrue`  |

7x Prime adds `class_alias()` so the old names continue to work as aliases. However, using
them in PHP code triggers deprecation notices.

### No change needed for YAML / XML / annotation config

```yaml
# This continues to work unchanged:
constraints:
    - NotNull: ~
    - IsTrue: ~
    - IsFalse: ~
    - "Symfony\\Component\\Validator\\Constraints\\True": ~  # alias still works
```

### Fix PHP-code usages

**Before:**

```php
use Symfony\Component\Validator\Constraints\True;
use Symfony\Component\Validator\Constraints\False;
use Symfony\Component\Validator\Constraints\Null;

$constraints = [new True(), new False(), new Null()];
```

**After:**

```php
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsNull;

$constraints = [new IsTrue(), new IsFalse(), new IsNull()];
```

### Find affected usages

```bash
grep -rn 'Constraints\\True\|Constraints\\False\|Constraints\\Null' src/ app/
```

---

## 11. Step 9 — Form Type API

This change was already deprecated in Symfony 2.8 upstream. If you are migrating from
Symfony 2.3–2.7 you must address it now.

### 11.1 — Use FQCN Instead of String Type Names

**Before (string aliases — deprecated since 2.8):**

```php
$form = $this->createFormBuilder()
    ->add('name',   'text')
    ->add('age',    'integer')
    ->add('active', 'checkbox')
    ->add('file',   'file')
    ->getForm();
```

**After (fully-qualified class names):**

```php
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

$form = $this->createFormBuilder()
    ->add('name',   TextType::class)
    ->add('age',    IntegerType::class)
    ->add('active', CheckboxType::class)
    ->add('file',   FileType::class)
    ->getForm();
```

### Common type name → FQCN mapping

| String alias | FQCN |
|-------------|------|
| `'text'` | `TextType::class` |
| `'textarea'` | `TextareaType::class` |
| `'integer'` | `IntegerType::class` |
| `'number'` | `NumberType::class` |
| `'money'` | `MoneyType::class` |
| `'email'` | `EmailType::class` |
| `'password'` | `PasswordType::class` |
| `'url'` | `UrlType::class` |
| `'checkbox'` | `CheckboxType::class` |
| `'radio'` | `RadioType::class` |
| `'choice'` | `ChoiceType::class` |
| `'date'` | `DateType::class` |
| `'datetime'` | `DateTimeType::class` |
| `'time'` | `TimeType::class` |
| `'file'` | `FileType::class` |
| `'hidden'` | `HiddenType::class` |
| `'submit'` | `SubmitType::class` |
| `'button'` | `ButtonType::class` |
| `'collection'` | `CollectionType::class` |
| `'repeated'` | `RepeatedType::class` |
| `'entity'` | `EntityType::class` (DoctrineBundle) |
| `'form'` | `FormType::class` |

All FQCN classes live under `Symfony\Component\Form\Extension\Core\Type\` except `EntityType`
which is in `Symfony\Bridge\Doctrine\Form\Type\`.

### 11.2 — Rename `getName()` to `getBlockPrefix()` in Custom Form Types

**Before:**

```php
class MyFormType extends AbstractType
{
    public function getName(): string
    {
        return 'my_form';
    }
}
```

**After:**

```php
class MyFormType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return 'my_form';
    }
}
```

If you do not define `getBlockPrefix()`, the prefix defaults to the class name without
`Type` suffix in snake_case (e.g. `MyFormType` → `my_form`).

### 11.3 — Remove DI Tag `alias` Attribute

**Before:**

```xml
<service id="app.form.my_type" class="AppBundle\Form\MyFormType">
    <tag name="form.type" alias="my_form" />
</service>
```

**After:**

```xml
<service id="app.form.my_type" class="AppBundle\Form\MyFormType">
    <tag name="form.type" />
</service>
```

### 11.4 — `configureOptions()` Instead of `setDefaultOptions()`

**Before:**

```php
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

public function setDefaultOptions(OptionsResolverInterface $resolver): void
{
    $resolver->setDefaults(['data_class' => MyEntity::class]);
}
```

**After:**

```php
use Symfony\Component\OptionsResolver\OptionsResolver;

public function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefaults(['data_class' => MyEntity::class]);
}
```

---

## 12. Step 10 — Twig Dependency

`twig/twig` has been replaced by `se7enxweb/twig`. The API is identical for the versions
targeted (`~1.34|~2.4`).

**composer.json change (see also Step 2):**

```json
"se7enxweb/twig": "~1.34|~2.4"
```

**No Twig template changes required.** All `{% %}`, `{{ }}`, and `{# #}` syntax, all built-in
filters, functions, and tags remain unchanged.

**Check for Twig extension classes that extend Twig internals:**

If you wrote a Twig extension that extends `\Twig_Extension` (Twig 1.x API), it will continue
to work via the compatibility shim in `se7enxweb/twig`. However, migrating to the Twig 2.x API
is recommended:

```php
// Twig 1.x API (still works via shim)
class MyExtension extends \Twig_Extension {}

// Twig 2.x API (preferred)
use Twig\Extension\AbstractExtension;
class MyExtension extends AbstractExtension {}
```

---

## 13. Step 11 — Removed Polyfills

The following Composer packages have been removed from `se7enxweb/prime`'s own `require`
because they are no-ops on PHP 8 (the functions/constants they polyfill are built into PHP 8):

```
symfony/polyfill-php54
symfony/polyfill-php55
symfony/polyfill-php56
symfony/polyfill-php70
symfony/polyfill-apcu
symfony/polyfill-util
```

**Action required:** Remove any of the above from your application's own `composer.json`
`require` section. They will generate a Composer warning on PHP 8 if left in place and
provide zero value.

```bash
composer remove symfony/polyfill-php54 symfony/polyfill-php55 \
                symfony/polyfill-php56 symfony/polyfill-php70 \
                symfony/polyfill-apcu  symfony/polyfill-util
```

> If you depend on other polyfills such as `symfony/polyfill-mbstring` or
> `symfony/polyfill-intl-*`, keep those — they are not removed.

---

## 14. Step 12 — Deprecated Symfony 2.x APIs

If you are upgrading from **Symfony 2.3–2.7** (not 2.8), you should also address the
deprecations introduced between those versions. They are fully documented in:

- [UPGRADE-2.4.md](UPGRADE-2.4.md)
- [UPGRADE-2.5.md](UPGRADE-2.5.md)
- [UPGRADE-2.6.md](UPGRADE-2.6.md)
- [UPGRADE-2.7.md](UPGRADE-2.7.md)
- [UPGRADE-2.8.md](UPGRADE-2.8.md)

The highest-impact items for most applications are:

### Security — `DigestAuthenticationEntryPoint` / encoder changes

The `MessageDigestPasswordEncoder` is still present but the MD5 and SHA1 algorithms are
considered insecure. Migrate to bcrypt or sodium if you have not done so already:

```yaml
# app/config/security.yml
security:
    encoders:
        AppBundle\Entity\User:
            algorithm: bcrypt
            cost: 12
```

### Console — removed helpers

`TableHelper` was removed in Symfony 3.0. If you are upgrading from pre-2.8, replace it now:

**Before:**

```php
$table = $app->getHelperSet()->get('table');
$table->setHeaders(['ID', 'Name'])->setRows([['1', 'Alice']]);
$table->render($output);
```

**After:**

```php
use Symfony\Component\Console\Helper\Table;

$table = new Table($output);
$table->setHeaders(['ID', 'Name'])->setRows([['1', 'Alice']]);
$table->render();
```

`ProgressHelper` was similarly replaced by `ProgressBar`:

```php
// Before
$h = new ProgressHelper();
$h->start($output, 10);
$h->advance();
$h->finish();

// After
$bar = new ProgressBar($output, 10);
$bar->start();
$bar->advance();
$bar->finish();
```

### DependencyInjection — `ContainerAware` trait

`ContainerAwareTrait` and `ContainerAwareInterface` still exist in 7x Prime.
However, the preferred approach for new code is constructor injection via the DI container:

```php
// Legacy (still works)
class MyService extends ContainerAware
{
    public function doSomething(): void
    {
        $router = $this->container->get('router');
    }
}

// Preferred (explicit dependency)
class MyService
{
    public function __construct(
        private readonly \Symfony\Component\Routing\RouterInterface $router
    ) {}

    public function doSomething(): void
    {
        $url = $this->router->generate('homepage');
    }
}
```

---

## 15. Step 13 — Run the Test Suite

After completing the steps above, run your application's own tests and then the 7x Prime test
suite to confirm everything is working.

### Run 7x Prime's own tests

```bash
cd /path/to/your/prime-checkout
./phpunit -c phpunit.xml.dist --no-coverage
```

All tests should pass. If you see failures related to your environment or local configuration,
check the output for the component name and consult the relevant `Tests/` directory.

### Run your application's tests

```bash
# PHPUnit
./vendor/bin/phpunit

# With filter
./vendor/bin/phpunit --filter MyControllerTest
```

### Fix common PHP 8 test failures

| Symptom | Cause | Fix |
|---------|-------|-----|
| `TypeError: Argument 1 passed to …` | Type mismatch (strict typing in PHP 8) | Audit argument types |
| `Deprecated: Implicit nullable` | Missing `?` on nullable param | Apply Step 3 |
| `Fatal error: Cannot use 'true'/'false'/'null' as class name` | Validator constraint name | Apply Step 8 |
| `Warning: … expects parameter 1 to be array, null given` | NULL passed where array expected | Add null guard |
| `Deprecated: Required parameter $x follows optional` | Parameter order issue in PHP 8 | Reorder parameters |

---

## 16. Step 14 — Web Server and Front Controller

### No changes required to `web/app.php` or `web/app_dev.php`

The front controllers in 7x Prime are identical to Symfony 2.8.52. Point your web server
`DocumentRoot` at the `web/` directory (not the project root):

```apacheconf
DocumentRoot /var/www/myapp/web
```

```nginx
root /var/www/myapp/web;
```

### If you are still on `web/app_dev.php` with IP restriction

The default `app_dev.php` allows access only from `127.0.0.1` and `::1`. Verify this
restriction is still in place on any public-facing server:

```php
// web/app_dev.php — confirm this block is present
if (isset($_SERVER['HTTP_CLIENT_IP'])
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || !(in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || PHP_SAPI === 'cli-server')
) {
    header('HTTP/1.0 403 Forbidden');
    exit('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}
```

---

## 17. Step 15 — Deployment and Security Checklist

Run this checklist after completing all migration steps:

```bash
# 1. All tests pass
./phpunit -c phpunit.xml.dist --no-coverage

# 2. PHP syntax check on your source
find src/ -name '*.php' -exec php -n -l {} \; 2>&1 | grep -v 'No syntax errors'

# 3. No implicit nullable warnings remaining
php -d error_reporting=E_ALL -r "
\$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src/'));
foreach (\$files as \$f) {
    if (\$f->getExtension() === 'php') include_once \$f;
}
" 2>&1 | grep -i 'deprecated'

# 4. Production build — no dev deps, optimised autoloader
composer install --no-dev --optimize-autoloader

# 5. Clear the application cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 6. Security audit of installed packages
composer audit

# 7. Verify DocumentRoot is web/, not project root
curl -Is http://yoursite.com/../composer.json | head -3
# Should be 403 or 404 — never 200

# 8. Verify HTTPS and SameSite=Lax cookies on all session responses
curl -sI http://yoursite.com/ | grep -i 'set-cookie'
# Should include HttpOnly, SameSite=Lax
```

---

## 18. Quick Reference — What Changed

| Category | Upstream Symfony 2.8 | 7x Prime 2.9 |
|----------|---------------------|-------------|
| Package name | `symfony/symfony` | `se7enxweb/prime` |
| PHP requirement | `>=5.3.9` | `^8.0` |
| Twig | `twig/twig` | `se7enxweb/twig` |
| Polyfills | php54, php55, php56, php70, apcu, util | Removed |
| Nullable params | ~330 implicit (`Type $p = null`) | All explicit (`?Type $p = null`) |
| Cookie SameSite | No attribute | Default `SameSite=Lax` |
| Session defaults | php.ini dependent | httponly=1, samesite=Lax, strict=1 enforced |
| CRLF in headers | Passed through | Stripped |
| YAML `!php/object:` | Deserialised into objects | `allowed_classes: false` (blocked) |
| Validator `True`/`False`/`Null` | Class names | `class_alias` → `IsTrue`/`IsFalse`/`IsNull` |
| Form type names | String aliases accepted | FQCN required (aliases still work but deprecated) |
| Branch | `master` / `2.8` | `2.9` |
| Repository | github.com/symfony/symfony | github.com/se7enxweb/prime |

---

## 19. Getting Help

| Resource | URL |
|----------|-----|
| Repository | github.com/se7enxweb/prime |
| Issue Tracker | github.com/se7enxweb/prime/issues |
| Discussions | github.com/se7enxweb/prime/discussions |
| Upgrade Notes | UPGRADE-2.9.md |
| Changelog | CHANGELOG-2.9.md |
| 7x Corporate | se7enx.com |
| Support | support@se7enx.com |

If you encounter a regression — behaviour that worked in Symfony 2.8 but is broken in
7x Prime — please open a GitHub issue with a minimal reproduction case.

---

## 20. PHP 8.5 & PHPUnit 11 — Compatibility Timeline

This section records every change made to bring the 7x Prime codebase and all vendored
libraries to a clean PHPUnit 11.5 pass under PHP 8.5.6. It is a practical reference for
anyone performing a similar Symfony 2.8-era upgrade to a modern PHP runtime and for anyone
wanting to understand the full scope of work that 7x Prime represents beyond the upstream
Symfony 2.8.52 baseline.

### Starting State (Symfony 2.8.52 fork, before 7x Prime work)

| Metric | Count |
|--------|-------|
| PHPUnit deprecations | 130 |
| PHPUnit warnings | 13 |
| Test errors | 26 |
| Test failures | 8 |
| Notices | 2 |
| Parse-error noise in output | 1 (inline in progress dots) |

### Ending State (7x Prime 2.9.0)

| Metric | Count |
|--------|-------|
| PHPUnit deprecations | 1 (unfixable — PHPUnit-generated `MockObject` serialisation) |
| PHPUnit warnings | 0 |
| Test errors | 0 |
| Test failures | 0 |
| Notices | 0 |
| Parse-error noise in output | 0 |
| Incomplete tests | 8 (genuine upstream TODO markers — accepted as non-trivial) |

---

### Phase 1 — Framework Source: Implicit Nullable Types

**Files changed:** ~330 PHP files across all components, bundles, and bridges.

PHP 8.4 introduced a deprecation for implicit nullable types — parameters typed as
`SomeClass $param = null` without an explicit `?`. PHP 8.5 elevates this to a hard
deprecation that fills the PHPUnit deprecation log. All ~330 occurrences in the framework
source were converted from the implicit form to the explicit `?` form:

```php
// Before — implicit nullable (PHP 8.4+ deprecation, PHP 8.5 hard deprecation)
public function setExpires(\DateTime $date = null): static {}

// After — explicit nullable (correct form since PHP 7.1)
public function setExpires(?\DateTime $date = null): static {}
```

Affected components: HttpFoundation, HttpKernel, Security (Core & Http),
DependencyInjection, Config, Console, Filesystem, Finder, Form, Routing, Translation,
VarDumper, and Yaml.

---

### Phase 2 — Validator: Reserved-Keyword Class Names

PHP 8 made `false`, `null`, and `true` illegal as class names. Three Validator constraint
classes directly collided with these reserved words:

| Original class | PHP 8 status | 7x Prime solution |
|---------------|--------------|-------------------|
| `Constraints\False` | Fatal — `false` is a keyword | `class_alias(IsFalse::class, …)` |
| `Constraints\Null`  | Fatal — `null` is a keyword  | `class_alias(IsNull::class, …)`  |
| `Constraints\True`  | Fatal — `true` is a keyword  | `class_alias(IsTrue::class, …)`  |

The original constraint files were replaced with `class_alias()` calls. All existing
references to the old names continue to work; PHP code using `use … as True` should migrate
to `use … as IsTrue`.

---

### Phase 3 — VarDumper: $GLOBALS Pass-by-Reference

PHP 8.1 forbids passing `$GLOBALS` by reference. `CliDumperTest` was updated to use a local
copy of the superglobal instead of passing `$GLOBALS` directly to helper functions.

---

### Phase 4 — ZendFramework Bridge (zend-code)

**File:** `vendor/zendframework/zend-code/src/Reflection/ParameterReflection.php`

Two methods were broken by PHP 8.1 deprecations:

1. **`getType()`** called `ReflectionParameter::isArray()`, `isCallable()`, and `getClass()`
   — all deprecated in PHP 8.1. Fixed to delegate to `parent::getType()` (the native
   `ReflectionParameter::getType()`).

2. **`getClass()`** called `$this->getType()`, which called `$this->getClass()` →
   infinite recursion at runtime. Root cause: `getType()` was looking for a class name
   and calling `getClass()` to get it. Fixed by changing to `parent::getType()` (the
   native reflection method).

**File:** `vendor/zendframework/zend-code/src/Reflection/ClassReflection.php`

`getStartLine()` had a covariant return-type mismatch with the PHP 8.1 `Reflector`
interface. Fixed with `#[\ReturnTypeWillChange]`.

---

### Phase 5 — OcramiusProxyManager

**File:** `vendor/ocramius/proxy-manager/src/ProxyManager/Generator/ParameterGenerator.php`

- `extractParameterType()` replaced deprecated `isArray()` / `isCallable()` / `getClass()`
  calls with `getType()`-based introspection for PHP 8.1+ compatibility.
- `getGeneratedType()` now correctly handles the nullable `?` prefix when generating
  proxy method signatures from reflected parameter types.

**Files:** `SetProxyInitializer.php` in both `LazyLoadingGhost` and `LazyLoadingValueHolder`

Changed `setType('Closure')` to `setType('?Closure')`. PHP 8 enforces that a property
declared as `\Closure` cannot hold `null`; the proxy initialiser is nullable by design
(uninitialised proxies have a `null` initialiser). Generated proxy code that declared a
non-nullable `\Closure` caused a PHP 8 `TypeError` on class load.

---

### Phase 6 — DoctrineBridge: ProxyGenerator

**File:** `vendor/doctrine/common/lib/Doctrine/Common/Proxy/ProxyGenerator.php`

The PHP template used to generate Doctrine proxy classes declared `$initializer` and
`$cloner` as `\Closure`. Changed to `?\Closure` to match PHP 8 property-type strictness.
Uninitialised proxies set these properties to `null` before the first method call — a
type violation under PHP 8's strict property type enforcement.

**Action required when upgrading:** Delete the Doctrine proxy cache so stale proxies
are regenerated with the corrected type declarations:

```bash
rm -rf app/cache/*/doctrine/orm/Proxies/*
```

---

### Phase 7 — SensioFrameworkExtraBundle: ParamConverterListener

**File:** `vendor/sensio/framework-extra-bundle/EventListener/ParamConverterListener.php`

All calls to `ReflectionParameter::getClass()` — deprecated in PHP 8.1 and removed in
PHP 9 — were replaced with `getType()`-based logic. The replacement correctly handles
`ReflectionNamedType`, built-in types, and union types introduced in PHP 8.0.

---

### Phase 8 — DoctrineDBAL: SQLite Driver

**File:** `vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/PDOSqlite/Driver.php`

PHP 8.4 split the monolithic `PDO` class into dedicated driver subclasses (`Pdo\Sqlite`,
`Pdo\Mysql`, etc.). The SQLite driver previously assumed any `PDO` instance would accept
`sqliteCreateFunction()`. Under PHP 8.4+, only `Pdo\Sqlite` instances have this method.

Fixed by checking `instanceof \Pdo\Sqlite` (PHP 8.4+) first, with a fallback using
`@$pdo->sqliteCreateFunction()` for older PHP where the method exists on the base `PDO`
class. The `@` suppresses a `TypeError` if the connection is not SQLite.

---

### Phase 9 — DoctrineORM: SqlWalker

**File:** `vendor/doctrine/orm/lib/Doctrine/ORM/Query/SqlWalker.php`

**Root cause of 26 test errors.** `$resultAlias` was being coerced to an empty string
(`''`) before use. This caused every lookup into `$scalarResultAliasMap` to use key `''`,
corrupting result-set assembly across the entire Doctrine test suite.

Fixed by keeping `$resultAlias = … ?: null` (preserving `null` when no alias is present)
and applying `?? ''` only at the specific array-access sites that require a non-null key.

---

### Phase 10 — DoctrineORM: UnitOfWork

**File:** `vendor/doctrine/orm/lib/Doctrine/ORM/UnitOfWork.php`

A `continue` statement inside a `switch` nested in a `foreach` was changed to `continue 2`
during the PHP 8 migration pass. `continue 2` in this context targets the outer `foreach`,
skipping the remainder of the loop body — which was not the intended behaviour. The `switch`
is the final statement of the `foreach` body, making `break` and `continue` semantically
equivalent at that position. Reverted to `break`.

---

### Phase 11 — Process Component: UnixPipes PTY Noise

**File:** `src/Symfony/Component/Process/Pipes/UnixPipes.php`

When process tests run in PTY (pseudo-terminal) mode, PHP 8.5 emits an `E_NOTICE` when
`fread()` is called on a file descriptor that closes during process teardown (errno=5 /
EIO). The notice appeared in the PHPUnit notice log but caused no test failure. Prefixed
with `@fread()` to suppress the noise; the return value is already guarded against `false`
and empty strings.

---

### Phase 12 — Finder Component Tests: SortableIteratorTest

**File:** `src/Symfony/Component/Finder/Tests/Iterator/SortableIteratorTest.php`

`file_get_contents(self::toAbsolute('.git'))` was used to create the `.git` test fixture.
`file_get_contents()` on a directory path triggers a warning/deprecation in PHP 8.5 because
reading a directory as a stream is not meaningful. The test only needed the path to exist
(as a fixture for sort-order comparison), not its contents. Replaced with
`touch(self::toAbsolute('.git'))`.

---

### Phase 13 — Translation Component Tests: JsonFileDumperTest

**File:** `src/Symfony/Component/Translation/Tests/Dumper/JsonFileDumperTest.php`

An `if (PHP_VERSION_ID < 50400) { $this->markTestIncomplete(…); }` guard caused the
entire test body to be skipped on any PHP 8.x installation (since PHP 8 is always ≥ 5.4).
The guard protected against missing `JSON_PRETTY_PRINT` support, which has been available
since PHP 5.4.0. Removed the guard. The test now runs and passes cleanly.

---

### Phase 14 — PropertyInfo Component Tests: PhpDocExtractorTest

**File:** `src/Symfony/Component/PropertyInfo/Tests/Extractors/PhpDocExtractorTest.php`
**New file:** `src/Symfony/Component/PropertyInfo/Tests/Fixtures/OmittedParamTagTypeDocBlock.php`

**Symptom:** The text `Parse Error: Syntax error, unexpected T_NS_SEPARATOR, expecting
T_FUNCTION on line 33` appeared inline between PHPUnit progress dots — not as a test
failure, just as raw stdout noise from within the test run.

**Root cause:** `OmittedParamTagTypeDocBlock` was defined inline at the bottom of the test
file. When `testParamTagTypeIsOmitted` ran, the `PhpDocExtractor` used the
phpdocumentor/reflection library to parse the test file's source to locate the class's
docblock. That library uses a PHP 5-era `PHPParser` (v1.x) which does not understand PHP
8.0 attribute syntax (`#[…]`). The test file contains:

```php
#[\PHPUnit\Framework\Attributes\DataProvider('typesProvider')]
public function testExtract(…) {}
```

The old parser caught a `PHPParser_Error` and the `Traverser::traverse()` catch block
echoed it directly to stdout — appearing between PHPUnit progress dots.

**Fix:** Moved `OmittedParamTagTypeDocBlock` into its own dedicated fixture file
(`Tests/Fixtures/OmittedParamTagTypeDocBlock.php`) containing only PHP 5-compatible
syntax. The phpdocumentor parser is now directed at the clean fixture file and parses it
without error.

---

## 21. After Installation — What To Do Next

This section guides you through the immediate steps after a fresh 7x Prime installation
or after migrating an existing Symfony 2.8 application to the `se7enxweb/prime` package.

### 21.1 — Verify the PHP Environment

```bash
# Confirm PHP version — must be 8.0 or higher
php -v

# Confirm required extensions are present
php -m | grep -E '^(mbstring|xml|intl|curl|pdo|opcache)$'

# Run the Symfony environment checker
php bin/check_configuration.php
```

If `check_configuration.php` reports any missing requirements, install the missing PHP
extensions and retry before proceeding.

### 21.2 — Install Composer Packages

```bash
# Full install (all packages including dev/test dependencies)
composer install

# Production install (no dev packages, optimised autoloader)
composer install --no-dev --optimize-autoloader
```

### 21.3 — Run the Framework Test Suite

The quickest way to confirm your environment is correctly configured is to run the bundled
7x Prime test suite:

```bash
# Quick smoke test — HttpFoundation + Routing only (fast, ~15 seconds)
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist \
    src/Symfony/Component/HttpFoundation/ \
    src/Symfony/Component/Routing/

# Full suite (all components — approximately 5–6 minutes)
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist
```

Expected result: `OK (NNNN tests, NNNN assertions)`. If you see failures, consult
[INSTALL.md — Troubleshooting](INSTALL.md#21-troubleshooting).

### 21.4 — Scan Your Application with PrimeBundleMigration

Before writing new code against 7x Prime, scan your existing application source for
compatibility issues:

```bash
# Full read-only compatibility scan
php bin/console prime:migrate:check --dir=src/

# Generate a detailed report for team review
php bin/console prime:migrate:report --dir=src/ --format=html --output=migration-report.html
```

See [Section 22 — PrimeBundleMigration Helper Commands](#22-primebundlemigration-helper-commands)
for the full command reference.

### 21.5 — Configure Your Database Connection

```bash
# Copy the parameters template
cp app/config/parameters.yml.dist app/config/parameters.yml

# Edit with your database credentials and application secret
nano app/config/parameters.yml
```

```yaml
# app/config/parameters.yml
parameters:
    database_host:     127.0.0.1
    database_port:     3306
    database_name:     myapp_db
    database_user:     myapp_user
    database_password: "your-strong-password-here"
    mailer_transport:  smtp
    mailer_host:       127.0.0.1
    mailer_user:       ~
    mailer_password:   ~
    secret:            "change-this-to-a-unique-random-string"
```

### 21.6 — Generate the Database Schema

```bash
# Preview the SQL that will be generated (dry run)
php bin/console doctrine:schema:update --dump-sql

# Apply the schema to the database
php bin/console doctrine:schema:update --force
```

### 21.7 — Clear the Cache

```bash
# Development environment
php bin/console cache:clear

# Production environment (clear + warm up)
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### 21.8 — Configure the Web Server

Point your web server `DocumentRoot` (Apache) or `root` (Nginx) at the **`web/`**
subdirectory — never the project root. This keeps `app/`, `src/`, `vendor/`,
`composer.json`, and all framework internals off the public web.

See [INSTALL.md — Section 5](INSTALL.md#5-web-server-configuration) for complete
virtual-host configuration examples for both Apache and Nginx.

### 21.9 — Create Your First Bundle

```bash
php bin/console generate:bundle \
    --namespace=AppBundle \
    --dir=src/ \
    --format=yaml \
    --no-interaction
```

This scaffolds the bundle structure and registers it in `app/AppKernel.php`:

```
src/AppBundle/
├── AppBundle.php
├── Controller/
│   └── DefaultController.php
├── DependencyInjection/
│   ├── AppExtension.php
│   └── Configuration.php
└── Resources/
    ├── config/
    │   ├── routing.yml
    │   └── services.yml
    └── views/
        └── Default/
            └── index.html.twig
```

### 21.10 — Write and Run Your First Tests

```bash
# Run only your application's tests
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist src/AppBundle/Tests/

# Run your tests together with the framework tests
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist
```

### 21.11 — Post-Install Security Checklist

```bash
# 1. Check installed packages for known CVEs
composer audit

# 2. Verify no secrets are committed to version control
git log --follow -p app/config/parameters.yml 2>/dev/null
# Should return nothing (file should be gitignored)

# 3. Confirm the project root is not publicly accessible
curl -Is http://localhost/../composer.json | head -3
# Must return 403 Forbidden or 404 — never 200 OK

# 4. Verify cookies include SameSite=Lax
curl -sI http://localhost/ | grep -i 'set-cookie'
# Should include: HttpOnly; SameSite=Lax

# 5. Confirm debug mode is off in production
grep 'AppKernel' web/app.php | grep 'false'
# Should show: new AppKernel('prod', false)
```

---

## 22. PrimeBundleMigration Helper Commands

7x Prime ships a set of **read-first, fix-optional helper commands** under the
`prime:migrate:*` namespace to assist in auditing and partially automating the migration
from Symfony 2.x on PHP 5.x/7.x to 7x Prime on PHP 8.x.

> **Helpers only.** These commands assist the migration process — they do not replace
> careful manual review. Always commit your code before running any `--fix` command
> and review the diff carefully before pushing.

All commands operate on your application source directory (`src/` by default) — not on
the framework source.

### Available Commands

| Command | Purpose |
|---------|---------|
| `prime:migrate:check` | Comprehensive compatibility scan — runs all checks |
| `prime:migrate:nullable` | Implicit nullable type scanner and auto-fixer |
| `prime:migrate:forms` | String-based form type scanner and auto-fixer |
| `prime:migrate:constraints` | Reserved-keyword Validator constraint name scanner |
| `prime:migrate:yaml` | YAML `!php/object:` usage scanner |
| `prime:migrate:twig` | Twig 1.x `Twig_*` legacy class reference scanner |
| `prime:migrate:report` | Generate a full migration status report (text/HTML/JSON) |

---

### `prime:migrate:check`

Runs all available compatibility scans in sequence and prints a consolidated summary.

```bash
# Scan the default src/ directory
php bin/console prime:migrate:check

# Scan a specific directory
php bin/console prime:migrate:check --dir=src/MyBundle

# Verbose — show every affected file and line
php bin/console prime:migrate:check --dir=src/ --verbose
```

**Example output:**

```
7x Prime Migration Check — PHP 8.5 Compatibility
=================================================
Directory: src/

 [SCAN] Implicit nullable types ................... 14 issues found
 [SCAN] String form type names ....................  3 issues found
 [SCAN] Reserved constraint names .................  0 issues found
 [SCAN] YAML !php/object usage ....................  0 issues found
 [SCAN] Twig_* class references ...................  1 issue  found

Total: 18 issues across 6 files.

Run `php bin/console prime:migrate:report` for full detail.
Run `php bin/console prime:migrate:nullable --fix --dir=src/` to auto-fix nullable types.
```

---

### `prime:migrate:nullable`

Scans your source for implicit nullable parameter declarations (`SomeType $param = null`
without a leading `?`) and optionally rewrites them to the explicit nullable form.

```bash
# Read-only scan — lists every affected file and line
php bin/console prime:migrate:nullable --dir=src/

# Preview changes without writing them (dry run)
php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run

# Apply the fix (writes files)
php bin/console prime:migrate:nullable --dir=src/ --fix
```

**Options:**

| Option | Description |
|--------|-------------|
| `--dir=PATH` | Directory to scan (default: `src/`) |
| `--fix` | Write the corrected files |
| `--dry-run` | Show what would change without writing |
| `--verbose` / `-v` | Print every affected line in the output |

**Example scan output:**

```
Implicit Nullable Type Scanner
==============================
Scanning: src/

 src/MyBundle/Controller/ArticleController.php
   Line 42:  public function setExpires(\DateTime $date = null)
   Line 67:  public function setAuthor(Author $author = null)

 src/MyBundle/Entity/Post.php
   Line 33:  public function setCategory(Category $cat = null)

Found 3 implicit nullable declarations in 2 files.

To fix automatically:
  php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run   (preview first)
  php bin/console prime:migrate:nullable --dir=src/ --fix              (apply)

Always review the diff with `git diff src/` after applying.
```

**What the fixer produces:**

```php
// Before
public function setExpires(\DateTime $date = null): static {}
public function setAuthor(?string $name = null, Author $author = null): void {}

// After
public function setExpires(?\DateTime $date = null): static {}
public function setAuthor(?string $name = null, ?Author $author = null): void {}
```

> The fixer handles simple cases reliably. Review parameters involving union types
> (`int|string $p = null`), intersection types, or `mixed` manually — those require
> thoughtful decisions about intent, not mechanical `?` insertion.

---

### `prime:migrate:forms`

Scans PHP source files for string-based form type names — the Symfony 2.3–2.7 API
deprecated in Symfony 2.8 and unavailable in Symfony 3.0+. 7x Prime retains the string
aliases for backward compatibility but emits deprecation notices.

The command operates in three modes, mirroring `prime:migrate:nullable`:

```bash
# Read-only scan — lists every affected file and line (nothing is written)
php bin/console prime:migrate:forms --dir=src/

# Preview the fix without writing (dry run)
php bin/console prime:migrate:forms --dir=src/ --fix --dry-run

# Apply the fix (writes files + injects use statements)
php bin/console prime:migrate:forms --dir=src/ --fix
```

**Options:**

| Option | Description |
|--------|-------------|
| `--dir=PATH` | Directory to scan (default: `src/`) |
| `--fix` | Write the corrected files and inject `use` statements |
| `--dry-run` | Show what would change without writing |

**What the fixer does:**

1. Replaces each string alias with the FQCN class constant (e.g. `'text'` → `TextType::class`)
2. Injects the corresponding `use` statements alphabetically (no duplicates)
3. Never touches files that are already clean

**Example scan output:**

```
Form Type String Alias Scanner
==============================
Scanning: src/

 src/MyBundle/Form/ArticleType.php
   Line 22:  'text'  → TextType::class
   Line 23:  'textarea'  → TextareaType::class
   Line 24:  'collection'  → CollectionType::class

Found 3 string type issues in 1 file.

To fix automatically:
  php bin/console prime:migrate:forms --dir=src --fix --dry-run   (preview first)
  php bin/console prime:migrate:forms --dir=src --fix              (apply)
```

**Example dry-run output:**

```
 [DRY RUN] src/MyBundle/Form/ArticleType.php — 3 replacements, 3 use statements would be added
    + use Symfony\Component\Form\Extension\Core\Type\CollectionType;
    + use Symfony\Component\Form\Extension\Core\Type\TextType;
    + use Symfony\Component\Form\Extension\Core\Type\TextareaType;
    Line 22:  'text'  → TextType::class
    Line 23:  'textarea'  → TextareaType::class
    Line 24:  'collection'  → CollectionType::class

Dry run complete. 3 replacements in 1 file would be made.
```

**What the fixer produces:**

```php
// Before
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', 'text')
            ->add('body',  'textarea')
            ->add('tags',  'collection')
        ;
    }
}

// After
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('body',  TextareaType::class)
            ->add('tags',  CollectionType::class)
        ;
    }
}
```

> **IMPORTANT:** Commit or stash your changes before running with `--fix`.
> Review the result with `git diff src/` before committing.
>
> The `entity` type uses the Doctrine Bridge namespace:
> `Symfony\Bridge\Doctrine\Form\Type\EntityType`. Verify the Doctrine Bridge
> is installed in your project before using this type.

See [Step 9 — Form Type API](#11-step-9--form-type-api) for the complete string-to-FQCN mapping table.

---

### `prime:migrate:constraints`

Scans for usages of the PHP-reserved Validator constraint names `True`, `False`, and
`Null` under the `Symfony\Component\Validator\Constraints` namespace. These names are PHP
8 keywords; 7x Prime provides `class_alias()` compatibility shims, but PHP code that
`use`s the old names should be updated.

```bash
php bin/console prime:migrate:constraints --dir=src/
```

**Example output:**

```
Validator Reserved Keyword Constraint Scanner
=============================================
Scanning: src/

 src/MyBundle/Form/UserType.php
   Line 45:  use Symfony\Component\Validator\Constraints\True;
   Line 52:  new True()

Found 1 file with reserved-keyword constraint references.

Migrate to:
  Constraints\True  → Constraints\IsTrue
  Constraints\False → Constraints\IsFalse
  Constraints\Null  → Constraints\IsNull

YAML / XML configuration files do not need to be updated.
The old names continue to work via class_alias() in 7x Prime.
```

---

### `prime:migrate:yaml`

Scans YAML files for `!php/object:` and `!!php/object:` tags. In 7x Prime, YAML
deserialisation of PHP objects is blocked by passing `['allowed_classes' => false]` to
`unserialize()`. Any YAML that relied on this feature will no longer produce PHP objects
at parse time.

```bash
# Scan the default app/config and src directories
php bin/console prime:migrate:yaml

# Scan a specific directory
php bin/console prime:migrate:yaml --dir=app/config
php bin/console prime:migrate:yaml --dir=app/fixtures
```

**Example output:**

```
YAML PHP Object Deserialisation Scanner
=======================================
Scanning: app/config/, src/

 app/fixtures/users.yml
   Line 7:  admin: !php/object: "O:4:\"User\":1:{...}"

Found 1 YAML file with PHP object tags.

These values will no longer deserialise to PHP objects in 7x Prime.
Replace with scalar/array data and reconstruct objects in application code.

Reference: MIGRATION.md — Step 7 (YAML Object Deserialisation)
```

---

### `prime:migrate:twig`

Scans PHP source files for references to the Twig 1.x `Twig_*` class naming convention.
The `se7enxweb/twig` package provides a compatibility shim for these names, but migrating
to the `Twig\…` PSR-4 namespace is recommended for forward compatibility.

```bash
php bin/console prime:migrate:twig --dir=src/
```

**Example output:**

```
Twig Legacy Class Reference Scanner
=====================================
Scanning: src/

 src/MyBundle/Twig/AppExtension.php
   Line 7:   class AppExtension extends \Twig_Extension
   Line 28:  return new \Twig_SimpleFilter('myfilter', …)

Found 1 file with Twig legacy class references.

Recommended migration:
  \Twig_Extension           → Twig\Extension\AbstractExtension
  \Twig_SimpleFilter        → Twig\TwigFilter
  \Twig_SimpleFunction      → Twig\TwigFunction
  \Twig_SimpleTest          → Twig\TwigTest
  \Twig_Environment         → Twig\Environment
  \Twig_Loader_Filesystem   → Twig\Loader\FilesystemLoader

Legacy names continue to work via the se7enxweb/twig compatibility layer.
```

---

### `prime:migrate:report`

Generates a comprehensive migration status report combining all available scans.
Supports text (stdout), HTML (file), and JSON (file or stdout) output formats.

```bash
# Text report to stdout (default)
php bin/console prime:migrate:report --dir=src/

# HTML report saved to a file (for sharing with the team)
php bin/console prime:migrate:report --dir=src/ --format=html --output=migration-report.html

# JSON report for CI pipeline integration
php bin/console prime:migrate:report --dir=src/ --format=json --output=migration-report.json
```

**Options:**

| Option | Description |
|--------|-------------|
| `--dir=PATH` | Root directory to scan (default: `src/`) |
| `--format=text\|html\|json` | Output format (default: `text`) |
| `--output=FILE` | Save report to file instead of stdout |
| `--verbose` / `-v` | Include every affected line in the report |

**Example text report:**

```
7x Prime Migration Status Report
=================================
Generated:  2026-05-12 10:00:00
PHP:        8.5.6
Prime:      2.9.0
Directory:  src/

SUMMARY
-------
  Implicit nullable types .....  14 issues in  8 files   [ACTION REQUIRED]
  Form type string aliases .....  3 issues in  2 files   [ACTION REQUIRED]
  Reserved constraint names ....  0 issues               [OK]
  YAML object tags .............  0 issues               [OK]
  Twig legacy classes ..........  1 issue  in  1 file    [ADVISORY]

OVERALL STATUS: MIGRATION INCOMPLETE — 17 issues require attention.

Run `php bin/console prime:migrate:nullable --fix --dry-run --dir=src/` to preview
the auto-fix for nullable types, then `--fix` to apply it.
```

---

### Recommended Migration Workflow

```bash
# 1. Save your current state in version control
git add -A && git commit -m "WIP: before 7x Prime migration scan"

# 2. Run the full compatibility scan to understand the scope
php bin/console prime:migrate:check --dir=src/

# 3. Generate a full report for the team
php bin/console prime:migrate:report --dir=src/ --format=html --output=migration-report.html

# 4. Fix implicit nullable types (most common — can be automated safely)
php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:nullable --dir=src/ --fix              # apply
git diff src/
git add -A && git commit -m "Fix: explicit nullable types for PHP 8.4+ (prime:migrate:nullable)"

# 5. Fix form type string aliases (automated — fixer rewrites files + injects use statements)
php bin/console prime:migrate:forms --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:forms --dir=src/ --fix              # apply
git diff src/
git add -A && git commit -m "Fix: FQCN form type names for Symfony 3.0+ (prime:migrate:forms)"

# 6. Fix Twig legacy class references (if any)
#    Use `prime:migrate:twig` output as your checklist.

# 7. Re-scan to confirm all issues resolved
php bin/console prime:migrate:check --dir=src/
# Target: all checks show [OK]

# 8. Run your application's own tests
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist src/

# 9. Run the full 7x Prime framework test suite
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist

# 10. Deploy
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

---

## Copyright

```
Copyright (C) 2004-2026 7x (se7enx.com). All rights reserved.
Portions copyright (C) 2004-2024 Fabien Potencier <fabien@symfony.com>.
```

## License

Licensed under the **MIT License**. See [LICENSE](LICENSE) for the full text.
