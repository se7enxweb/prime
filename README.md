# 7x Prime (v2.9)

> **7x Prime is an independent project and community effort, NOT affiliated with
> Fabien Potencier or the upstream Symfony project. It honours the upstream
> licence by publishing the full source on GitHub.**

**7x Prime** is a fast, secure, PHP 8.x-compatible fork of Symfony v2.8.52,
maintained by [7x (se7enx.com)](https://se7enx.com).  It modernises the Symfony
2.x component library for teams that need a stable, long-lived PHP 8 foundation
without migrating to Symfony 4/5/6/7.

---

## Project Status

| Item | Status |
|------|--------|
| Upstream base | Symfony 2.8.52 (last upstream release) |
| Branch | `2.9` |
| PHP | ^8.0 — tested on **PHP 8.5.6** |
| Twig | se7enxweb/twig ~1.34\|~2.4 |
| License | MIT |

---

## Who is 7x?

7x (stylised **se7enx**) is an independent open-source studio building PHP
web software at [se7enx.com](https://se7enx.com).  The 7x Prime project was
started to keep a battle-tested Symfony 2.x component library alive on modern
PHP runtimes.

---

## What is 7x Prime?

7x Prime is a **component library and full-stack PHP framework** forked from
Symfony 2.8.52.  It retains the original MVC architecture, routing engine,
form system, security component, console toolkit, and Twig templating while
adding:

- Full PHP 8.0–8.5.6 compatibility (no deprecations, no fatal errors).
- Security hardening: SameSite cookies, CRLF-injection protection, YAML object
  injection prevention, hardened session defaults.
- A clean composer package (`se7enxweb/prime`) without obsolete polyfills.

---

## Architecture Overview

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

---

## Technology Stack

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

## Requirements

- **PHP 8.0 or higher** (8.5.6 recommended and tested)
- PHP extensions: `mbstring`, `xml`, `intl`, `curl`, `pdo`
- Composer 2.x
- A web server: Apache 2.4+ with `mod_rewrite` or Nginx 1.18+

---

## Quick Start

```bash
# 1 — Clone the repository
git clone https://github.com/se7enxweb/prime.git myapp
cd myapp

# 2 — Install dependencies
composer install

# 3 — Check PHP requirements
php bin/check_configuration.php

# 4 — Point your web server DocumentRoot to web/
#     (see Installation section for full server config)

# 5 — Open http://localhost/ in your browser
```

---

## Installation

See **[INSTALL.md](INSTALL.md)** for the full step-by-step guide covering:

- First-time installation (6 steps)
- Apache & Nginx virtual-host configuration
- File-permission setup
- Building your first page (controller + route + template)
- Database integration (PDO / Doctrine / Propel)
- Composer package management
- CLI task reference
- Cache management
- Deployment checklist

---

## Main Features

- **MVC framework** — clean separation of controllers, models, and Twig templates.
- **Powerful Routing** — attribute-style, YAML, XML, or PHP route definitions with
  parameters, requirements, defaults, and host matching.
- **Form System** — form types, data transformers, validation, CSRF protection
  built-in.
- **Security Component** — firewalls, access control, voters, encoders,
  remember-me, and session management.
- **Dependency Injection Container** — full-featured DI with services, parameters,
  compiler passes, and lazy loading.
- **Console Component** — build CLI commands with styled output, progress bars,
  and interactive helpers.
- **Event Dispatcher** — subscribe/listen to framework and custom events.
- **Translation / i18n** — XLIFF, YAML, PHP translation catalogues; pluralisation;
  locale negotiation.
- **Validator** — constraint-based validation with annotation, YAML, and XML
  mapping.
- **HttpFoundation** — OO wrappers for Request, Response, Cookie (with
  SameSite), Session, FileUpload, and more.
- **Twig Templating** — fast, secure template engine with inheritance, blocks,
  macros, and filters.
- **Profiler / Web Debug Toolbar** — built-in profiler with data collectors for
  requests, events, DB queries, security, logs, and more.

---

## Building Pages, Routes, and DB Results

### 1 — Define a route (YAML)

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

### 2 — Write the controller

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

### 3 — Render with Twig

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

## CLI Reference

The framework ships a console binary at `bin/console` (or `app/console` in
older project layouts):

```bash
# List all available commands
php bin/console list

# Clear the cache
php bin/console cache:clear --env=prod

# Generate a bundle skeleton
php bin/console generate:bundle

# Run database migrations
php bin/console doctrine:migrations:migrate

# Show routing table
php bin/console debug:router

# Show service container
php bin/console debug:container

# Run tests
./phpunit -c phpunit.xml.dist
```

---

## Upgrading from Symfony 2.8

See **[UPGRADE-2.9.md](UPGRADE-2.9.md)** for a complete migration guide.
Key points:

- PHP ^8.0 required (was >=5.3.9).
- Cookies now default to `SameSite=Lax`.
- YAML `!php/object:` deserialization is now restricted.
- Obsolete polyfills removed from composer.json.

---

## Security

If you discover a security vulnerability in 7x Prime, please report it
privately by emailing **security@se7enx.com** with a clear description and
reproduction steps.  Do not open a public GitHub issue for security matters.

---

## Contributing

1. Fork the repository on GitHub.
2. Create a feature branch from `2.9`.
3. Write tests for your change.
4. Run `./phpunit -c phpunit.xml.dist` and confirm all tests pass.
5. Submit a pull request against the `2.9` branch.

Please read [CONTRIBUTING.md](CONTRIBUTING.md) and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
before submitting.

---

## Issue Tracker

Report bugs and feature requests at:
**https://github.com/se7enxweb/prime/issues**

Please search existing issues before opening a new one.

---

## Recommended Books for Newcomers

The following books are excellent resources for learning the Symfony 2.x
framework that 7x Prime is based on.  All are freely available online.

| Book | Authors | Notes |
|------|---------|-------|
| **The Definitive Guide to symfony** | Fabien Potencier, François Zaninotto | The original canonical guide; covers the symfony 1.x era but foundational concepts apply |
| **More with symfony** | Community authors | Advanced techniques: performance, integration, testing, and DI |
| **Symfony 2: The Book** (symfony.com/doc) | Fabien Potencier, Ryan Weaver | Official documentation for Symfony 2.x — read online at symfony.com/doc/2.8 |
| **A Year With Symfony** | Matthias Noback | Deep dive into services, DI, and extension points in Symfony 2/3 |
| **Building PHP Applications with Symfony, CakePHP, and Zend Framework** | Bartosz Porebski et al. | Practical comparison guide; useful Symfony 2 chapters |
| **PHP Objects, Patterns, and Practice** | Matt Zandstra | Essential OOP and design-pattern knowledge that underpins all Symfony development |

---

## Donate

If 7x Prime saves you time, consider supporting the project:

- GitHub Sponsors: https://github.com/sponsors/se7enxweb
- Website: https://se7enx.com

---

## Copyright

Copyright (C) 2004–2026 7x (se7enx.com). All rights reserved.

Portions copyright (C) 2004–2024 Fabien Potencier <fabien@symfony.com>.
See [CONTRIBUTORS.md](CONTRIBUTORS.md) for the full contributor list.

---

## License

7x Prime is released under the **MIT License**.
See [LICENSE](LICENSE) for the full licence text.

The upstream Symfony source is also MIT-licensed.
7x Prime honours that licence by publishing the full source on GitHub.
