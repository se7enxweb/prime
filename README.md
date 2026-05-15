# 7x Prime (v2.9) — PHP 8.5 Support (Stable; Open Source)

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net/)
[![7x Prime](https://img.shields.io/badge/symfony-2.9-orange)](https://github.com/se7enxweb/prime)
[![License: MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![GitHub issues](https://img.shields.io/github/issues/se7enxweb/prime)](https://github.com/se7enxweb/prime/issues)

> **7x Prime (v2.9)** is the continuing evolution of Symfony 2.8.52,
> now fully compatible with PHP 8.0 through PHP 8.5. Maintained by [7x (se7enx.com)](https://se7enx.com/)
> and the open-source developer community that has relied on the Symfony 2.x component library for over a decade.

---

## Table of Contents

1. [Project Notice](#1-project-notice)
2. [Project Status](#2-project-status)
3. [Who is 7x](#3-who-is-7x)
4. [What is 7x Prime?](#4-what-is-7x-prime)
5. [Architecture Overview](#5-architecture-overview)
6. [Technology Stack](#6-technology-stack)
7. [Requirements](#7-requirements)
8. [Quick Start](#8-quick-start)
9. [Main Features](#9-main-features)
10. [Building Pages, Routes, and DB Results](#10-building-pages-routes-and-db-results)
11. [Your First Bundle](#11-your-first-bundle)
12. [Installation](#12-installation)
13. [Key CLI Reference](#13-key-cli-reference)
14. [Issue Tracker](#14-issue-tracker)
15. [Where to Get More Help](#15-where-to-get-more-help)
16. [How to Contribute](#16-how-to-contribute)
17. [Donate & Support](#17-donate--support)
18. [PHPUnit 11 Test Suite](#18-phpunit-11-test-suite)
19. [Copyright](#19-copyright)
20. [License](#20-license)

---

## 1. Project Notice

> **Please Note:** This project is not associated with the original Symfony project, SensioLabs, or Fabien
> Potencier beyond attribution. It is an independent, 7x + community-driven continuation of the Symfony 2.8.52
> codebase, stewarded and evolved by [7x (se7enx.com)](https://se7enx.com/) to support PHP 8.x in production.
> The upstream Symfony source is MIT-licensed; 7x Prime honours that licence by publishing the full source on GitHub.

---

## 2. Project Status

The Symfony 2.8.52 codebase was the final upstream release of the Symfony 2.x line. Thousands of
production applications built on this component library remained in active use because the architecture —
a composable component system, powerful routing, full DI container, and Twig templating — remained sound
and productive long after the upstream EOL.

**7x Prime (v2.9)** is the first release branch that brings this codebase fully into the PHP 8.x era.

| Item | Status |
|------|--------|
| Upstream base | Symfony 2.8.52 (last upstream release) |
| Branch | `2.9` |
| PHP | ^8.0 — tested on **PHP 8.5.6** |
| Twig | se7enxweb/twig ~1.34\|~2.4 |
| License | MIT |

Work in the `2.9` branch focuses on:

- PHP 8.0 through PHP 8.5 full compatibility and PHPUnit test suite passing
- Security hardening: SameSite cookies, CRLF-injection protection, YAML object injection prevention
- Composer package manager integration
- Documentation and developer experience improvements

---

## 3. Who is 7x

[7x](https://se7enx.com/) is a North American web software corporation with over 24 years of experience
building and maintaining PHP web applications and content platforms. Previously known as Brookins Consulting,
7x took on stewardship of the Symfony 2.8.52 codebase to ensure that the many applications built on it
can continue to run on modern, supported PHP versions.

7x offers:

- Commercial PHP 8.x upgrade consulting for Symfony 2.x applications
- Hosting and infrastructure for PHP 8.x projects
- Custom development, migrations, and training
- Open-source community stewardship

---

## 4. What is 7x Prime?

7x Prime is a **component library and full-stack PHP framework** forked from Symfony 2.8.52. It retains
the original MVC architecture, routing engine, form system, security component, console toolkit, and Twig
templating while adding:

- Full PHP 8.0–8.5.6 compatibility (no deprecations, no fatal errors).
- Security hardening: SameSite cookies, CRLF-injection protection, YAML object
  injection prevention, hardened session defaults.
- A clean composer package (`se7enxweb/prime`) without obsolete polyfills.

---

## 5. Architecture Overview

```
src/Symfony/
├── Bridge/          # Integration bridges (Doctrine, Twig, Swiftmailer, …)
├── Bundle/          # FrameworkBundle, SecurityBundle, TwigBundle, …
└── Component/       # Stand-alone components (can be used independently)
    ├── Console/
    ├── DependencyInjection/
    ├── EventDispatcher/
    ├── HttpFoundation/
    ├── HttpKernel/
    ├── Routing/
    ├── Security/
    ├── Templating/ + Twig bridge
    ├── Translation/
    ├── Validator/
    └── Yaml/
```

### Request Lifecycle

```
HTTP Request
      │
      ▼
   Web Server (Apache / Nginx)  →  DocumentRoot: web/
      │
      ▼
  web/app.php  ──  AppKernel boots — registers bundles, compiles DI container
      │
      ├── Routing component  →  matches URL to _controller service/action
      │
      ├── HttpKernel  →  dispatches kernel.request → controller → kernel.response
      │
      ├── Controller  →  business logic, calls services from DI container
      │
      ├── Twig template  →  view rendering (Resources/views/)
      │
      └── Response  →  returned to web server → browser
```

---

## 6. Technology Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP ^8.0 (8.5.6 recommended) |
| Templating | Twig 1.x / 2.x (via se7enxweb/twig) |
| HTTP | Symfony HttpFoundation / HttpKernel |
| Routing | Symfony Routing component |
| ORM (optional) | Doctrine 2.x (via Bridge) |
| Forms | Symfony Form component |
| Validation | Symfony Validator (with annotations) |
| Console | Symfony Console component |
| Cache | APC / file-based cache |
| Sessions | PHP native session storage (hardened) |
| Testing | PHPUnit 4.x / 5.x + Symfony WebTestCase |

---

## 7. Requirements

- **PHP 8.0 or higher** (8.5.6 recommended and tested)
- PHP extensions: `mbstring`, `xml`, `intl`, `curl`, `pdo`
- Composer 2.x
- A web server: Apache 2.4+ with `mod_rewrite` or Nginx 1.18+

### Requirements Summary

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 8.0 | 8.5+ |
| Apache | 2.4 | 2.4 (event + PHP-FPM) |
| Nginx | 1.18 | 1.24+ |
| MySQL | 8.0 | 8.0+ |
| MariaDB | 10.3 | 10.6+ |
| PostgreSQL | 14 | 16+ |
| SQLite | 3.0 | 3.35+ |
| Composer | 2.0 | latest 2.x |

---

## 8. Quick Start

```bash
# 1 — Clone the repository
git clone https://github.com/se7enxweb/prime.git myapp
cd myapp

# 2 — Install dependencies
composer install

# 3 — Point your web server DocumentRoot to web/
#     (see INSTALL.md for full server config)

# 4 — Open http://localhost/ in your browser
```

---

## 9. Main Features

- **MVC framework** — clean separation of controllers, models, and Twig templates.
- **Powerful Routing** — attribute-style, YAML, XML, or PHP route definitions with
  parameters, requirements, defaults, and host matching.
- **Form System** — form types, data transformers, validation, CSRF protection built-in.
- **Security Component** — firewalls, access control, voters, encoders,
  remember-me, and session management.
- **Dependency Injection Container** — full-featured DI with services, parameters,
  compiler passes, and lazy loading.
- **Console Component** — build CLI commands with styled output, progress bars,
  and interactive helpers.
- **Event Dispatcher** — subscribe/listen to framework and custom events.
- **Translation / i18n** — XLIFF, YAML, PHP translation catalogues; pluralisation;
  locale negotiation.
- **Validator** — constraint-based validation with annotation, YAML, and XML mapping.
- **HttpFoundation** — OO wrappers for Request, Response, Cookie (with SameSite),
  Session, FileUpload, and more.
- **Twig Templating** — fast, secure template engine with inheritance, blocks,
  macros, and filters.
- **Profiler / Web Debug Toolbar** — built-in profiler with data collectors for
  requests, events, DB queries, security, logs, and more.
- **PHP 8.0–8.5 full compatibility** — all breaking changes addressed throughout
  the component library.

---

## 10. Building Pages, Routes, and DB Results

See [INSTALL.md](INSTALL.md) for full step-by-step instructions. Below is the three-minute version.

### Define a route (YAML)

```yaml
# app/config/routing.yml
homepage:
    path:     /
    defaults: { _controller: AppBundle:Default:index }

blog_show:
    path:     /blog/{slug}
    defaults: { _controller: AppBundle:Blog:show }
    requirements:
        slug: "[a-z0-9\-]+"
```

### Write the controller

```php
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class BlogController extends Controller
{
    public function showAction(Request $request, string $slug)
    {
        $post = $this->getDoctrine()
            ->getRepository('AppBundle:Post')
            ->findOneBy(['slug' => $slug]);

        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        return $this->render('blog/show.html.twig', ['post' => $post]);
    }
}
```

### Render with Twig

```twig
{# app/Resources/views/blog/show.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ post.title }}{% endblock %}

{% block body %}
    <h1>{{ post.title }}</h1>
    <p>{{ post.body }}</p>
{% endblock %}
```

---

## 11. Your First Bundle

A **Bundle** is the primary extension point in 7x Prime — a self-contained plugin that contributes
routes, controllers, Twig templates, services, Doctrine entities, console commands, and more to
the application.

### 11.1 Generating the Scaffold

```bash
php bin/console generate:bundle
```

Answer the interactive prompts:

| Prompt | Example value |
|--------|---------------|
| Bundle namespace | `Acme/BlogBundle` |
| Bundle name | `AcmeBlogBundle` |
| Target directory | `src/` |
| Configuration format | `yml` |

This creates:

```
src/Acme/BlogBundle/
├── AcmeBlogBundle.php          ← bundle class (registers itself)
├── Controller/
│   └── DefaultController.php  ← starter controller
├── DependencyInjection/
│   ├── AcmeBlogExtension.php  ← loads services.yml into the DI container
│   └── Configuration.php      ← config tree definition (optional)
├── Resources/
│   ├── config/
│   │   ├── routing.yml         ← bundle-local route definitions
│   │   └── services.yml        ← service definitions
│   └── views/
│       └── Default/
│           └── index.html.twig ← starter template
└── Tests/
    └── Controller/
        └── DefaultControllerTest.php
```

### 11.2 Registering the Bundle

Add the bundle to `app/AppKernel.php`:

```php
// app/AppKernel.php
public function registerBundles(): array
{
    $bundles = [
        // … core bundles …
        new Acme\BlogBundle\AcmeBlogBundle(),
    ];

    return $bundles;
}
```

Mount its routes in `app/config/routing.yml`:

```yaml
acme_blog:
    resource: "@AcmeBlogBundle/Resources/config/routing.yml"
    prefix:   /blog
```

### 11.3 Adding a Service

Define services in `Resources/config/services.yml`:

```yaml
services:
    acme_blog.post_manager:
        class: Acme\BlogBundle\Service\PostManager
        arguments:
            - "@doctrine.orm.entity_manager"
```

Inject it into a controller via `$this->get()` or constructor injection:

```php
namespace Acme\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{
    public function indexAction(): Response
    {
        $posts = $this->get('acme_blog.post_manager')->findLatest(10);

        return $this->render('@AcmeBlog/Blog/index.html.twig', ['posts' => $posts]);
    }
}
```

### 11.4 Customising Templates

**Override a bundle template** by copying it into `app/Resources/`:

```
app/Resources/
└── AcmeBlogBundle/
    └── views/
        └── Blog/
            └── index.html.twig   ← overrides @AcmeBlog/Blog/index.html.twig
```

Twig's lookup order: `app/Resources/BundleName/views/` first, then `src/Bundle/Resources/views/`.
Any file placed under `app/Resources/` wins automatically — no configuration required.

**Extend the global base layout** using Twig block inheritance:

```twig
{# src/AcmeBlogBundle/Resources/views/Blog/index.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Blog{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('bundles/acmeblog/css/blog.css') }}">
{% endblock %}

{% block body %}
    {% for post in posts %}
        <article>
            <h2>{{ post.title }}</h2>
            <p>{{ post.excerpt }}</p>
            <a href="{{ path('acme_blog_show', {slug: post.slug}) }}">Read more</a>
        </article>
    {% endfor %}
{% endblock %}
```

**Publish bundle assets** (CSS, JS, images) to `web/bundles/`:

```bash
php bin/console assets:install --symlink web/
```

This symlinks `src/AcmeBlogBundle/Resources/public/` → `web/bundles/acmeblog/`,
making files available at `/bundles/acmeblog/css/blog.css`.

### 11.5 Full Bundle Directory Reference

```
src/Acme/BlogBundle/
├── AcmeBlogBundle.php
├── Command/                    ← console commands (auto-discovered)
│   └── ImportPostsCommand.php
├── Controller/                 ← HTTP controllers
│   └── BlogController.php
├── DependencyInjection/        ← DI extension + optional configuration tree
│   ├── AcmeBlogExtension.php
│   └── Configuration.php
├── Entity/                     ← Doctrine entities
│   └── Post.php
├── Form/                       ← form type classes
│   └── PostType.php
├── Repository/                 ← Doctrine entity repositories
│   └── PostRepository.php
├── Resources/
│   ├── config/
│   │   ├── doctrine/           ← Doctrine XML/YAML mapping (if not using annotations)
│   │   ├── routing.yml
│   │   ├── routing_dev.yml
│   │   └── services.yml
│   ├── public/                 ← static assets published to web/bundles/acmeblog/
│   │   ├── css/
│   │   └── js/
│   ├── translations/           ← XLIFF / YAML translation catalogues
│   │   └── messages.en.yml
│   └── views/                  ← Twig templates
│       ├── Blog/
│       │   ├── index.html.twig
│       │   └── show.html.twig
│       └── layout.html.twig    ← optional bundle-level base layout
├── Service/                    ← business-logic service classes
│   └── PostManager.php
├── Tests/                      ← PHPUnit tests mirroring src/ structure
│   ├── Controller/
│   └── Service/
└── Validator/                  ← custom constraint classes (optional)
    └── Constraint/
```

---

## 12. Installation

See **[INSTALL.md](INSTALL.md)** for the full step-by-step guide covering:

- First-time installation (6 steps)
- Apache & Nginx virtual-host configuration
- File-permission setup
- Building your first page (controller + route + Twig template)
- Database integration (PDO / Doctrine ORM)
- Composer package management
- CLI command reference
- Cache management
- Deployment checklist
- Upgrading from Symfony 2.8

---

## 13. Key CLI Reference

```bash
# ── Test Suite ───────────────────────────────────────────────────────────────
./phpunit -c phpunit.xml.dist                       # run the full test suite
./phpunit -c phpunit.xml.dist --filter ClassName    # run a specific test class

# ── Symfony Console ───────────────────────────────────────────────────────────
php bin/console list                                # list all available commands
php bin/console cache:clear --env=prod              # clear the cache (production)
php bin/console debug:router                        # show routing table
php bin/console debug:container                     # show service container
php bin/console generate:bundle                     # scaffold a new bundle
php bin/console doctrine:migrations:migrate         # run database migrations

# ── Migration Helpers (prime:migrate:*) ───────────────────────────────────────
# Run all checks (scan only — nothing written):
php bin/console prime:migrate:check --dir=src/

# Full migration workflow (always dry-run first, then apply):
php bin/console prime:migrate:nullable    --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:nullable    --dir=src/ --fix             # apply: implicit nullable → ?Type

php bin/console prime:migrate:forms       --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:forms       --dir=src/ --fix             # apply: 'text' → TextType::class

php bin/console prime:migrate:constraints --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:constraints --dir=src/ --fix             # apply: Constraints\True → IsTrue

php bin/console prime:migrate:twig        --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:twig        --dir=src/ --fix             # apply: Twig_* → Twig\ PSR-4

php bin/console prime:migrate:yaml        --dir=src/ --fix --dry-run   # preview
php bin/console prime:migrate:yaml        --dir=src/ --fix             # apply: strip !php/object: tags

php bin/console prime:migrate:report      --dir=src/ --format=html --output=report.html

# ── Composer ─────────────────────────────────────────────────────────────────
composer install                                    # install packages from composer.json
composer require vendor/package-name                # add a Packagist package
composer dump-autoload -o                           # regenerate optimised autoloader
composer show                                       # list installed packages
composer audit                                      # check for security advisories
```

---

## 14. Issue Tracker

Submit bugs, feature requests, and improvements at:
**https://github.com/se7enxweb/prime/issues**

If you discover a security issue, please report it responsibly by email to
[security@se7enx.com](mailto:security@se7enx.com) rather than opening a public issue.

---

## 15. Where to Get More Help

| Resource | URL |
|----------|-----|
| Repository | github.com/se7enxweb/prime |
| Upgrade Guide | UPGRADE-2.9.md |
| Installation Guide | INSTALL.md |
| Issue Tracker | github.com/se7enxweb/prime/issues |
| Discussions | github.com/se7enxweb/prime/discussions |
| 7x Corporate | se7enx.com |
| Support | support@se7enx.com |
| Sponsor 7x | sponsor.se7enx.com |

### Recommended Books for Newcomers

| Book | Authors | Notes |
|------|---------|-------|
| **Symfony 2: The Book** (symfony.com/doc) | Fabien Potencier, Ryan Weaver | Official documentation for Symfony 2.x — read online at symfony.com/doc/2.8 |
| **A Year With Symfony** | Matthias Noback | Deep dive into services, DI, and extension points in Symfony 2/3 |
| **Building PHP Applications with Symfony, CakePHP, and Zend Framework** | Bartosz Porebski et al. | Practical comparison guide; useful Symfony 2 chapters |
| **More with Symfony** | Community authors | Advanced techniques: performance, integration, testing, and DI |
| **PHP Objects, Patterns, and Practice** | Matt Zandstra | Essential OOP and design-pattern knowledge that underpins all Symfony development |

---

## 16. How to Contribute

Everyone is encouraged to contribute. To get started:

1. Fork the repository: [github.com/se7enxweb/prime](https://github.com/se7enxweb/prime)
2. Clone your fork and create a feature branch:
   ```bash
   git checkout -b feature/my-improvement
   ```
3. Make your changes following the existing code style
4. Add the `(c) 2004-2026 7x <info@se7enx.com>` copyright header to every modified PHP file
5. Run the PHPUnit test suite to verify no regressions:
   ```bash
   ./phpunit -c phpunit.xml.dist
   ```
6. Push and open a Pull Request against the `2.9` branch
7. Participate in the code review — maintainers respond promptly

Please read [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
before submitting.

Bug reports, feature requests, and discussions are welcome via the
[issue tracker](https://github.com/se7enxweb/prime/issues) and
[GitHub Discussions](https://github.com/se7enxweb/prime/discussions).

---

## 17. Donate & Support

7x Prime is free and open-source. If it has saved you migration time, upgrade costs,
or kept a production application running, please consider supporting the project:

- [sponsor.se7enx.com](https://sponsor.se7enx.com/) — support subscriptions
- [paypal.me/7xweb](https://www.paypal.com/paypalme/7xweb) — one-time donation
- [github.com/sponsors/se7enxweb](https://github.com/sponsors/se7enxweb) — GitHub Sponsors

Every contribution funds:

- PHP compatibility testing as new PHP versions release
- Security patching and vulnerability triage
- Documentation and developer experience improvements
- Community infrastructure

---

## 18. PHPUnit 11 Test Suite

7x Prime ships a complete test suite covering every component, bridge, and bundle in the
framework, verified to pass with **PHPUnit 11.5** on **PHP 8.5.6** — zero errors, zero
failures, zero warnings, and zero framework-attributed deprecations.

### Test Suite Status

| Metric | Result |
|--------|--------|
| PHP version | 8.5.6 |
| PHPUnit version | 11.5.55 |
| Test errors | 0 |
| Test failures | 0 |
| Deprecations (framework) | 0 |
| Warnings | 0 |
| Notices | 0 |
| Tests | 0 incomplete (8 genuine upstream TODO markers, accepted) |

### Requirements

PHPUnit 11 requires **PHP 8.1 or higher** and is **not bundled** in `vendor/`. Install it
globally (recommended) or as a project dev dependency:

```bash
# Global install — available as `phpunit` in any project
composer global require phpunit/phpunit ^11

# Or as a dev dependency in this project
composer require --dev phpunit/phpunit ^11
```

### Running the Full Suite

```bash
# Using globally-installed phpunit (fastest)
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist

# Using the project wrapper script (delegates to global phpunit)
./phpunit -c phpunit.xml.dist --no-coverage
```

### Running a Subset

```bash
# Single component directory
./phpunit -c phpunit.xml.dist src/Symfony/Component/HttpFoundation/
./phpunit -c phpunit.xml.dist src/Symfony/Component/Routing/
./phpunit -c phpunit.xml.dist src/Symfony/Component/Form/

# Single test class (by class name)
./phpunit -c phpunit.xml.dist --filter RequestTest

# Single test method
./phpunit -c phpunit.xml.dist --filter "RequestTest::testGetMethod"

# Pattern match across all test classes
./phpunit -c phpunit.xml.dist --filter "/testGet.*Route/"
```

### Displaying Deprecations, Warnings, and Notices

```bash
# Show all PHP 8.x notices, deprecations, and warnings during the run
php /root/.config/composer/vendor/bin/phpunit \
    --no-coverage \
    --display-deprecations \
    --display-warnings \
    --display-notices \
    -c phpunit.xml.dist
```

### PHPUnit 11 — Key Differences from PHPUnit 4–9

PHPUnit 11 uses **PHP 8 native attributes** in place of docblock annotations. Both styles
are accepted in 7x Prime's own test files, but attributes are preferred in new code:

```php
// Old style — PHPUnit 4–9 docblock annotations (still parsed but deprecated)
/**
 * @dataProvider valuesProvider
 * @group integration
 */
public function testProcess($input, $expected): void {}

// PHPUnit 11 style — PHP 8 native attributes (preferred)
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[DataProvider('valuesProvider')]
#[Group('integration')]
public function testProcess($input, $expected): void {}
```

**Common attribute mapping:**

| Old annotation | PHPUnit 11 attribute |
|---------------|---------------------|
| `@dataProvider foo` | `#[DataProvider('foo')]` |
| `@depends testBar` | `#[Depends('testBar')]` |
| `@group name` | `#[Group('name')]` |
| `@covers Foo::bar` | `#[CoversMethod(Foo::class, 'bar')]` |
| `@uses Foo` | `#[UsesClass(Foo::class)]` |
| `@before` | `#[Before]` |
| `@after` | `#[After]` |
| `@requires PHP 8.1` | `#[RequiresPhp('8.1')]` |
| `@expectedExceptionMessage …` | `$this->expectExceptionMessage(…)` in body |

### Writing Tests for 7x Prime Bundles

Place test classes in `src/YourBundle/Tests/` following PHPUnit conventions. Use
`PHPUnit\Framework\TestCase` for unit tests and
`Symfony\Bundle\FrameworkBundle\Test\WebTestCase` for full HTTP functional tests:

```php
<?php
// src/AppBundle/Tests/Service/SluggerTest.php

namespace AppBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SluggerTest extends TestCase
{
    #[DataProvider('slugProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $input)), '-');
        $this->assertSame($expected, $slug);
    }

    public static function slugProvider(): array
    {
        return [
            ['Hello World!', 'hello-world'],
            ['7x Prime 2.9', '7x-prime-2-9'],
            ['',             ''],
        ];
    }
}
```

Run your bundle's tests:

```bash
./phpunit -c phpunit.xml.dist src/AppBundle/Tests/
```

### Code Coverage

```bash
# HTML coverage report (requires Xdebug or PCOV)
./phpunit -c phpunit.xml.dist \
    --coverage-html=build/coverage \
    src/Symfony/Component/HttpFoundation/

# Text summary to stdout
./phpunit -c phpunit.xml.dist \
    --coverage-text \
    src/Symfony/Component/Routing/

# Clover XML (for CI integration — PHPUnit, Codecov, Coveralls)
./phpunit -c phpunit.xml.dist \
    --coverage-clover=build/logs/clover.xml \
    src/Symfony/Component/
```

### Extending the Test Suite

To add tests for framework code, create a test file in the corresponding component's
`Tests/` directory and follow the existing naming and structure conventions:

```
src/Symfony/Component/MyComponent/
├── MyService.php
└── Tests/
    ├── MyServiceTest.php         ← unit tests for MyService
    └── Fixtures/
        └── MyFixture.php         ← test fixtures (no test logic)
```

PHPUnit discovers tests automatically via the `phpunit.xml.dist` `<testsuites>` definition.
No registration is required — place your `*Test.php` file in the right directory and run
the suite.

See **[INSTALL.md — Section 15](INSTALL.md#15-running-the-phpunit-test-suite)** for the
full PHPUnit 11 reference guide including coverage, custom base classes, Symfony-specific
test helpers, and CI integration patterns.

---

## 19. Copyright

```
Copyright (C) 2004–2026 7x (se7enx.com). All rights reserved.
Portions copyright (C) 2004–2024 Fabien Potencier <fabien@symfony.com>.
See CONTRIBUTORS.md for the full contributor list.
```

---

## 20. License

7x Prime is released under the **MIT License**.
See [LICENSE](LICENSE) for the full licence text.

The upstream Symfony source is also MIT-licensed.
7x Prime honours that licence by publishing the full source on GitHub.
