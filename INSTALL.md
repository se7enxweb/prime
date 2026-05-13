# 7x Prime (v2.9) — Installation & Developer Guide

> Full installation, web server configuration, route and page examples,
> database integration patterns, Composer package usage, and the PHPUnit test suite.
> Target: PHP 8.0 – 8.5 · Apache 2.4 / Nginx 1.18+ · MySQL / MariaDB / PostgreSQL / SQLite

---

## Table of Contents

1. [Requirements](#1-requirements)
2. [Architecture & Directory Overview](#2-architecture--directory-overview)
3. [First-Time Installation](#3-first-time-installation)
4. [Composer Dependency Management](#4-composer-dependency-management)
5. [Web Server Configuration](#5-web-server-configuration)
6. [File Permissions](#6-file-permissions)
7. [Building Your First Page](#7-building-your-first-page)
8. [Routing — Full Reference](#8-routing--full-reference)
9. [Controllers — Request, Parameters, Redirects](#9-controllers--request-parameters-redirects)
10. [Templates & Twig](#10-templates--twig)
11. [Database Integration — PDO (Built-in)](#11-database-integration--pdo-built-in)
12. [Database Integration — Doctrine ORM (via Bridge)](#12-database-integration--doctrine-orm-via-bridge)
13. [Composer Packages in Controllers](#13-composer-packages-in-controllers)
14. [Forms and Validation](#14-forms-and-validation)
15. [Running the PHPUnit Test Suite](#15-running-the-phpunit-test-suite)
16. [Writing Your Own Tests](#16-writing-your-own-tests)
17. [Console CLI Commands](#17-console-cli-commands)
18. [Cache Management](#18-cache-management)
19. [Deployment Checklist](#19-deployment-checklist)
20. [Upgrading from Symfony 2.8](#20-upgrading-from-symfony-28)
21. [Troubleshooting](#21-troubleshooting)

---

## 1. Requirements

### Mandatory

| Requirement | Minimum | Tested Versions |
|-------------|---------|-----------------|
| PHP | 8.0 | 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 |
| Web Server | — | Apache 2.4 · Nginx 1.18+ |
| OS | Linux | CentOS 7 / AlmaLinux 8 / Ubuntu 22.04+ / Debian 12+ |
| Composer | 2.x | latest 2.x |

### Optional but Recommended

| Optional Dependency | Purpose |
|--------------------|---------|
| MySQL 8.0+ / MariaDB 10.3+ | Relational database |
| PostgreSQL 14+ | Alternative relational database |
| SQLite 3.x | Lightweight / embedded database |
| PHP ext-pdo | PDO database driver (usually bundled) |
| PHP ext-pdo_mysql | MySQL PDO driver |
| PHP ext-pdo_pgsql | PostgreSQL PDO driver |
| PHP ext-mbstring | Multi-byte string functions |
| PHP ext-intl | Internationalisation |
| PHP ext-xml | XML processing (required by Symfony components) |
| PHP ext-curl | HTTP client support |
| PHP ext-opcache | Performance (production) |

### Check PHP Version

```bash
php -v
# Expected: PHP 8.x.x ...

php -m | grep -E 'pdo|mbstring|intl|opcache|xml|curl'
# Check enabled extensions
```

---

## 2. Architecture & Directory Overview

```
project-root/
├── app/
│   ├── AppKernel.php                Bundle registration and environment bootstrap
│   ├── AppCache.php                 HTTP cache kernel (optional)
│   ├── config/
│   │   ├── config.yml               Main framework configuration
│   │   ├── config_dev.yml           Development overrides
│   │   ├── config_prod.yml          Production overrides
│   │   ├── routing.yml              URL routing rules
│   │   ├── routing_dev.yml          Development routing (profiler, etc.)
│   │   ├── security.yml             Firewall and access control rules
│   │   ├── services.yml             DI service definitions
│   │   └── parameters.yml           Environment-specific parameters (gitignored)
│   ├── cache/                       Compiled container and route cache (gitignored)
│   ├── logs/                        Application log files (gitignored)
│   └── Resources/
│       └── views/
│           ├── base.html.twig       Global HTML layout template
│           └── {bundle}/            Per-bundle view overrides
├── src/
│   └── AppBundle/                   Your application bundle
│       ├── AppBundle.php
│       ├── Controller/
│       │   └── DefaultController.php
│       ├── Entity/                  Doctrine ORM entities
│       ├── Form/                    Form type classes
│       ├── Repository/              Doctrine entity repositories
│       └── Resources/
│           └── views/               Bundle-specific Twig templates
├── src/Symfony/                     7x Prime framework source
│   ├── Bridge/                      Integration bridges (Doctrine, Twig, …)
│   ├── Bundle/                      FrameworkBundle, SecurityBundle, TwigBundle, …
│   └── Component/                   Stand-alone components
├── vendor/                          Composer packages (gitignored)
├── web/                             ← Web server DocumentRoot (only publicly served dir)
│   ├── app.php                      Production front controller
│   ├── app_dev.php                  Development front controller (profiler enabled)
│   └── .htaccess                    Apache rewrite rules
├── composer.json                    Composer manifest
├── composer.lock                    Lock file
└── phpunit.xml.dist                 PHPUnit configuration
```

### Request Lifecycle

```
1.  Browser sends GET /blog/hello-world
2.  Apache/Nginx rewrites all requests → web/app.php
3.  app.php creates AppKernel, boots the DI container, registers bundles
4.  Routing component matches /blog/hello-world → AppBundle:Blog:show, slug=hello-world
5.  HttpKernel dispatches kernel.request event (firewalls, listeners)
6.  BlogController::showAction(Request $request, string $slug) runs
7.  Controller calls Doctrine repository, renders Twig template
8.  Twig renders blog/show.html.twig, extending base.html.twig
9.  Response returned through kernel.response event
10. HTTP response sent to browser
```

---

## 3. First-Time Installation

### Step 1 — Clone the Repository

```bash
git clone -b 2.9 https://github.com/se7enxweb/prime.git my-project
cd my-project
```

### Step 2 — Verify PHP Version

```bash
php -r "echo PHP_VERSION . PHP_EOL;"
# Must print 8.0.0 or higher
```

### Step 3 — Install Composer Packages

```bash
composer install
```

This installs `se7enxweb/twig` and any other declared dependencies.

### Step 4 — Configure Parameters

```bash
# Copy the parameters template
cp app/config/parameters.yml.dist app/config/parameters.yml
# Edit database credentials and secret
```

### Step 5 — Set Up Web Server

See [Section 5 — Web Server Configuration](#5-web-server-configuration) for Apache and Nginx examples.
Point your `DocumentRoot` to the **`web/`** subdirectory — not the project root. This keeps
`app/`, `src/`, `vendor/`, `composer.json`, and all framework internals off the public web.

### Step 6 — Verify the Installation

Navigate to `http://yourapp/` (using the `app_dev.php` front controller in development:
`http://yourapp/app_dev.php/`). The Symfony welcome page confirms the installation is working.

```bash
# Check PHP configuration for Symfony compatibility
php bin/check_configuration.php
```

### 💾 Git Save Point — Installation Complete

```bash
git status        # verify working tree is clean
git log --oneline -3
```

---

## 4. Composer Dependency Management

### How It Works

Composer manages all third-party packages declared in `composer.json`. The autoloader
bootstrapped by `web/app.php` covers both the framework source under `src/Symfony/` and
every installed package under `vendor/`.

### Adding a Packagist Package

```bash
composer require guzzlehttp/guzzle
# Now GuzzleHttp\Client is available anywhere in your application
```

### Removing a Package

```bash
composer remove guzzlehttp/guzzle
composer dump-autoload -o
```

### Keeping vendor/ Out of Git

The `.gitignore` in this repository already excludes `vendor/` and `composer.lock`.
Collaborators run `composer install` after cloning.

### Optimised Autoloader (Production)

```bash
composer install --no-dev --optimize-autoloader
```

---

## 5. Web Server Configuration

### Apache 2.4 — Virtual Host

Set `DocumentRoot` to the **`web/`** subdirectory. This keeps `app/`, `src/`, `vendor/`,
`composer.json`, and all framework internals off the public web.

```apacheconf
<VirtualHost *:80>
    ServerName myapp.example.com

    # Point DocumentRoot at web/ — the only directory served over HTTP
    DocumentRoot /var/www/myapp/web

    <Directory /var/www/myapp/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  /var/log/apache2/myapp-error.log
    CustomLog /var/log/apache2/myapp-access.log combined
</VirtualHost>
```

Ensure `mod_rewrite` is enabled:

```bash
a2enmod rewrite
systemctl reload apache2
```

`web/.htaccess` rewrite rules (included in the repository):

```apacheconf
DirectoryIndex app.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ app.php [QSA,L]
</IfModule>
```

### Nginx — Server Block

Set `root` to the **`web/`** subdirectory.

```nginx
server {
    listen 80;
    server_name myapp.example.com;

    # Root must point to web/ — not the project root
    root /var/www/myapp/web;
    index app.php;

    location / {
        try_files $uri $uri/ /app.php$is_args$args;
    }

    location ~ ^/(app|app_dev)\.php(/|$) {
        fastcgi_pass   unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    # Deny access to hidden files (.git, .env, etc.)
    location ~ /\. {
        deny all;
    }
}
```

Reload Nginx after changes:

```bash
nginx -t && systemctl reload nginx
```

---

## 6. File Permissions

Symfony writes to `app/cache/` and `app/logs/`. Set them writable by the web server user:

```bash
# Option A — open permissions (development only)
chmod -R 777 app/cache app/logs

# Option B — www-data ownership (production recommended)
chown -R www-data:www-data app/cache app/logs
chmod -R 755 app/cache app/logs
```

When using multiple users (e.g. your deploy user and the web server user), use the ACL approach:

```bash
setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX app/cache app/logs
setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX app/cache app/logs
```

---

## 7. Building Your First Page

This section walks through creating a `hello` page from scratch, covering bundle, controller,
route, and Twig template in full.

### Step 7.1 — Register the Bundle

AppBundle is already registered in `app/AppKernel.php` in the default project layout:

```php
// app/AppKernel.php
public function registerBundles(): array
{
    return [
        // … other bundles …
        new AppBundle\AppBundle(),
    ];
}
```

### Step 7.2 — Create the Controller

```php
<?php
// src/AppBundle/Controller/HelloController.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class HelloController extends Controller
{
    /**
     * Display a personalised greeting.
     * URL: /hello/{name}
     */
    public function indexAction(Request $request, string $name = 'World'): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('hello/index.html.twig', [
            'name'  => ucfirst(htmlspecialchars($name, ENT_QUOTES, 'UTF-8')),
            'title' => 'Hello, ' . ucfirst($name) . '!',
        ]);
    }
}
```

> **Convention:** Controller classes live in `src/{Bundle}/Controller/`. Each action is a
> public method named `{action}Action(Request $request, …)`. Return a `Response` object —
> `$this->render()` returns one wrapping the rendered Twig output.

### Step 7.3 — Create the Twig Template

```twig
{# src/AppBundle/Resources/views/hello/index.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ title }}{% endblock %}

{% block body %}
<div class="page hello-page">
    <h1>{{ title }}</h1>
    <p>Hello from 7x Prime (v2.9)!</p>
    <p>PHP {{ constant('PHP_VERSION') }}</p>
</div>
{% endblock %}
```

### Step 7.4 — Define a Route (YAML)

```yaml
# app/config/routing.yml
hello:
    path:     /hello/{name}
    defaults: { _controller: AppBundle:Hello:index, name: World }
    requirements:
        name: "[A-Za-z][A-Za-z0-9\\-_]{0,31}"
```

### Step 7.5 — Test the Page

```bash
curl -s http://localhost/hello
# → Greeting with "Hello, World!"

curl -s http://localhost/hello/Alice
# → Greeting with "Hello, Alice!"
```

---

## 8. Routing — Full Reference

Routing is configured in `app/config/routing.yml` (or imported from bundle `Resources/config/routing.yml`).

### Static Route

```yaml
about:
    path:     /about
    defaults: { _controller: AppBundle:Page:about }
```

### Route with Parameters

```yaml
article_show:
    path:     /articles/{slug}
    defaults: { _controller: AppBundle:Article:show }
    requirements:
        slug: "[a-z0-9\\-]+"
```

### Route with Multiple Parameters and Defaults

```yaml
blog_archive:
    path:     /blog/{year}/{month}
    defaults: { _controller: AppBundle:Blog:archive, month: null }
    requirements:
        year:  "\\d{4}"
        month: "\\d{2}"
```

### Route with HTTP Method Restriction

```yaml
api_post_create:
    path:    /api/posts
    defaults: { _controller: AppBundle:Api:createPost }
    methods:  [POST]
```

### Import Bundle Routes

```yaml
# app/config/routing.yml
app_bundle:
    resource: "@AppBundle/Resources/config/routing.yml"
    prefix:   /
```

### Accessing Route Parameters in Controllers

```php
public function archiveAction(Request $request, string $year, ?string $month): Response
{
    // $year and $month come from the matched route
    return $this->render('blog/archive.html.twig', [
        'year'  => $year,
        'month' => $month,
    ]);
}
```

### Generating URLs

```php
// In a controller
$url  = $this->generateUrl('article_show', ['slug' => 'hello-world']);
// /articles/hello-world

$absoluteUrl = $this->generateUrl('article_show', ['slug' => 'hello-world'],
    \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
```

```twig
{# In a Twig template #}
<a href="{{ path('article_show', {slug: article.slug}) }}">Read more</a>
<a href="{{ url('article_show', {slug: article.slug}) }}">Absolute link</a>
```

---

## 9. Controllers — Request, Parameters, Redirects

### Reading GET / POST Parameters

```php
public function searchAction(Request $request): Response
{
    // Query string (?q=…)
    $query = $request->query->get('q', '');
    $page  = (int) $request->query->get('page', 1);

    // POST body
    if ($request->isMethod('POST')) {
        $title = $request->request->get('title', '');
    }

    return $this->render('search/results.html.twig', [
        'query' => htmlspecialchars($query, ENT_QUOTES, 'UTF-8'),
        'page'  => max(1, $page),
    ]);
}
```

### Redirect

```php
public function oldUrlAction(): Response
{
    return $this->redirectToRoute('new_route_name', [], 301);
    // or: return $this->redirect('/new-url', 301);
}
```

### Forward 404

```php
public function showAction(string $slug): Response
{
    $post = $this->getDoctrine()->getRepository('AppBundle:Post')
        ->findOneBy(['slug' => $slug]);

    if (!$post) {
        throw $this->createNotFoundException('Post not found: ' . $slug);
    }

    return $this->render('post/show.html.twig', ['post' => $post]);
}
```

### JSON Response

```php
use Symfony\Component\HttpFoundation\JsonResponse;

public function apiAction(): JsonResponse
{
    return new JsonResponse(['status' => 'ok', 'version' => '2.9']);
}
```

---

## 10. Templates & Twig

### Template Inheritance

```twig
{# app/Resources/views/base.html.twig #}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}7x Prime{% endblock %}</title>
    {% block stylesheets %}{% endblock %}
</head>
<body>
    <header><a href="{{ path('homepage') }}">Home</a></header>
    <main>{% block body %}{% endblock %}</main>
    <footer>&copy; {{ "now"|date("Y") }} 7x</footer>
    {% block javascripts %}{% endblock %}
</body>
</html>
```

```twig
{# src/AppBundle/Resources/views/article/show.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{{ article.title }}{% endblock %}

{% block body %}
<article>
    <h1>{{ article.title }}</h1>
    <time>{{ article.createdAt|date('Y-m-d') }}</time>
    <div class="body">{{ article.body|nl2br }}</div>
    <p><a href="{{ path('article_index') }}">← Back to articles</a></p>
</article>
{% endblock %}
```

### Passing Variables to Templates

Every key in the array passed to `$this->render()` becomes a Twig variable:

```php
return $this->render('article/show.html.twig', [
    'article' => $article,   // → {{ article.title }}, {{ article.body }}
    'related' => $related,   // → {% for item in related %}
]);
```

### Twig Filters and Functions (commonly used)

```twig
{{ "hello world"|upper }}             {# HELLO WORLD #}
{{ article.createdAt|date('Y-m-d') }} {# 2026-05-11 #}
{{ description|truncate(100) }}
{{ path('route_name', {id: 1}) }}     {# /articles/1 #}
{{ asset('css/app.css') }}            {# /css/app.css (with cache busting) #}
{% if is_granted('ROLE_ADMIN') %}…{% endif %}
```

---

## 11. Database Integration — PDO (Built-in)

PDO is available in any standard PHP installation and requires no additional bundle configuration.

### Step 11.1 — Store Credentials Securely

Use `app/config/parameters.yml` (gitignored) for credentials:

```yaml
# app/config/parameters.yml
parameters:
    database_host:     127.0.0.1
    database_port:     3306
    database_name:     myapp
    database_user:     myapp_user
    database_password: secret
```

Reference them in `app/config/config.yml`:

```yaml
# app/config/config.yml
parameters:
    db_dsn: "mysql:host=%database_host%;port=%database_port%;dbname=%database_name%;charset=utf8mb4"
```

### Step 11.2 — Create a PDO Service

```yaml
# app/config/services.yml
services:
    app.pdo:
        class: PDO
        arguments:
            - "%db_dsn%"
            - "%database_user%"
            - "%database_password%"
            - { 3: !php/const PDO::ERRMODE_EXCEPTION, 11: !php/const PDO::FETCH_ASSOC }
```

Or instantiate directly in a controller (simpler for small projects):

```php
$pdo = new \PDO(
    'mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4',
    $this->getParameter('database_user'),
    $this->getParameter('database_password'),
    [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
```

### Step 11.3 — Query in a Controller

```php
public function indexAction(Request $request): Response
{
    $pdo   = $this->get('app.pdo');
    $page  = max(1, (int) $request->query->get('page', 1));
    $limit = 10;

    $stmt = $pdo->prepare(
        'SELECT id, slug, title, excerpt, created_at
         FROM   articles
         WHERE  published = 1
         ORDER  BY created_at DESC
         LIMIT  :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit',  $limit,              \PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $limit, \PDO::PARAM_INT);
    $stmt->execute();

    return $this->render('article/index.html.twig', [
        'articles' => $stmt->fetchAll(),
        'page'     => $page,
    ]);
}
```

### Sample MySQL Table DDL

```sql
CREATE TABLE articles (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(128)      NOT NULL UNIQUE,
    title       VARCHAR(255)      NOT NULL,
    excerpt     TEXT              NULL,
    body        LONGTEXT          NOT NULL,
    published   TINYINT(1)        NOT NULL DEFAULT 0,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_created (published, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 12. Database Integration — Doctrine ORM (via Bridge)

The `DoctrineBundle` and `Doctrine\Bridge` are included in `se7enxweb/prime`. Doctrine 2.x ORM
maps PHP objects (Entities) to database rows.

### Step 12.1 — Configure Doctrine

```yaml
# app/config/config.yml
doctrine:
    dbal:
        driver:   pdo_mysql
        host:     "%database_host%"
        port:     "%database_port%"
        dbname:   "%database_name%"
        user:     "%database_user%"
        password: "%database_password%"
        charset:  utf8mb4
    orm:
        auto_generate_proxy_classes: "%kernel.debug%"
        auto_mapping: true
```

### Step 12.2 — Define an Entity

```php
<?php
// src/AppBundle/Entity/Article.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ArticleRepository")
 * @ORM\Table(name="articles")
 */
class Article
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\Column(type="string", length=128, unique=true)
     */
    private string $slug;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $title;

    /**
     * @ORM\Column(type="text")
     */
    private string $body;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $published = false;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTimeInterface $createdAt;

    // Getters and setters …
    public function getId(): int            { return $this->id; }
    public function getSlug(): string       { return $this->slug; }
    public function setSlug(string $s): void { $this->slug = $s; }
    public function getTitle(): string      { return $this->title; }
    public function setTitle(string $t): void { $this->title = $t; }
    public function getBody(): string       { return $this->body; }
    public function setBody(string $b): void { $this->body = $b; }
    public function isPublished(): bool     { return $this->published; }
    public function setPublished(bool $p): void { $this->published = $p; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $d): void { $this->createdAt = $d; }
}
```

### Step 12.3 — Generate / Update the Schema

```bash
# Generate SQL preview
php bin/console doctrine:schema:update --dump-sql

# Apply schema changes to the database
php bin/console doctrine:schema:update --force
```

### Step 12.4 — Query in a Controller

```php
public function indexAction(): Response
{
    $articles = $this->getDoctrine()
        ->getRepository('AppBundle:Article')
        ->findBy(['published' => true], ['createdAt' => 'DESC'], 10);

    return $this->render('article/index.html.twig', ['articles' => $articles]);
}

public function showAction(string $slug): Response
{
    $article = $this->getDoctrine()
        ->getRepository('AppBundle:Article')
        ->findOneBy(['slug' => $slug, 'published' => true]);

    if (!$article) {
        throw $this->createNotFoundException('Article not found: ' . $slug);
    }

    return $this->render('article/show.html.twig', ['article' => $article]);
}
```

### Step 12.5 — Custom Repository Query

```php
<?php
// src/AppBundle/Repository/ArticleRepository.php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class ArticleRepository extends EntityRepository
{
    public function findPublishedBySlug(string $slug): ?object
    {
        return $this->createQueryBuilder('a')
            ->where('a.slug = :slug AND a.published = true')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestPublished(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.published = true')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
```

---

## 13. Composer Packages in Controllers

After running `composer require vendor/package`, the library is available everywhere through
the Composer autoloader (bootstrapped in `web/app.php`).

### Example — Guzzle HTTP Client

```bash
composer require guzzlehttp/guzzle
```

```php
use GuzzleHttp\Client;

public function fetchAction(): Response
{
    $client   = new Client();
    $response = $client->get('https://api.example.com/data');
    $data     = json_decode($response->getBody(), true);

    return $this->render('api/data.html.twig', ['data' => $data]);
}
```

### Example — Monolog Logging

```bash
composer require monolog/monolog
```

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

public function createAction(Request $request): Response
{
    $log = new Logger('article');
    $log->pushHandler(new StreamHandler($this->getParameter('kernel.logs_dir') . '/app.log'));

    // … create article …
    $log->info('Article created', ['slug' => $slug]);

    return $this->redirectToRoute('article_index');
}
```

### Example — Carbon Date Formatting

```bash
composer require nesbot/carbon
```

```php
use Carbon\Carbon;

public function showAction(string $slug): Response
{
    $article = /* … fetch … */;

    return $this->render('article/show.html.twig', [
        'article'   => $article,
        'humanDate' => Carbon::instance($article->getCreatedAt())->diffForHumans(),
    ]);
}
```

---

## 14. Forms and Validation

Symfony's Form component provides type-safe form handling with built-in CSRF protection.

### Define a Form Type

```php
<?php
// src/AppBundle/Form/ArticleType.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Entity\Article;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title',  TextType::class,     ['label' => 'Title'])
            ->add('body',   TextareaType::class, ['label' => 'Body'])
            ->add('save',   SubmitType::class,   ['label' => 'Save Article']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Article::class]);
    }
}
```

### Use the Form in a Controller

```php
use AppBundle\Form\ArticleType;
use AppBundle\Entity\Article;

public function newAction(Request $request): Response
{
    $article = new Article();
    $form    = $this->createForm(ArticleType::class, $article);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $article->setCreatedAt(new \DateTime());
        $em = $this->getDoctrine()->getManager();
        $em->persist($article);
        $em->flush();
        return $this->redirectToRoute('article_index');
    }

    return $this->render('article/new.html.twig', [
        'form' => $form->createView(),
    ]);
}
```

### Render the Form in Twig

```twig
{# src/AppBundle/Resources/views/article/new.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
{{ form_start(form) }}
    {{ form_errors(form) }}
    {{ form_row(form.title) }}
    {{ form_row(form.body) }}
    {{ form_row(form.save) }}
{{ form_end(form) }}
{% endblock %}
```

---

## 15. Running the PHPUnit 11 Test Suite

7x Prime ships with `phpunit.xml.dist` at the project root defining the full test suite
for all framework components. The suite is verified to pass cleanly on **PHPUnit 11.5** /
**PHP 8.5.6** with zero errors, zero failures, zero warnings, and zero framework-attributed
deprecations.

### 15.1 — Install PHPUnit 11

PHPUnit 11 is **not bundled** in `vendor/`. Install it globally (one-time setup, works
for all PHP projects) or as a dev dependency:

```bash
# Global install — recommended
composer global require phpunit/phpunit ^11

# Confirm it is available
php /root/.config/composer/vendor/bin/phpunit --version
# PHPUnit 11.5.x by Sebastian Bergmann and contributors.

# Or install per-project as a dev dependency
composer require --dev phpunit/phpunit ^11
./vendor/bin/phpunit --version
```

> PHPUnit 11 requires **PHP 8.1 or higher** (`declare(strict_types=1)` and fibers
> are used internally). PHP 8.5.6 is the recommended runtime for running 7x Prime's
> full test suite.

### 15.2 — Run All Tests

```bash
# Using globally installed phpunit (recommended)
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist

# Using the project wrapper (./phpunit) which delegates to the global install
./phpunit -c phpunit.xml.dist --no-coverage
```

Full run time is approximately **5–6 minutes** on a modern server. Expected result:

```
PHPUnit 11.5.55 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.6
Configuration: /path/to/symfony/phpunit.xml.dist

...............................................................................
...............................................................................
...............................................................

OK (NNNN tests, NNNN assertions)
```

### 15.3 — Run a Specific Component

```bash
# Single component
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist \
    src/Symfony/Component/HttpFoundation/

# Multiple components
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist \
    src/Symfony/Component/Routing/ \
    src/Symfony/Component/HttpKernel/

# A Bridge
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist \
    src/Symfony/Bridge/Doctrine/

# A Bundle
php /root/.config/composer/vendor/bin/phpunit --no-coverage -c phpunit.xml.dist \
    src/Symfony/Bundle/FrameworkBundle/
```

### 15.4 — Filter by Class or Method

```bash
# All tests in a class
./phpunit -c phpunit.xml.dist --filter RequestTest

# A single test method (exact match)
./phpunit -c phpunit.xml.dist --filter "RequestTest::testGetMethod"

# A regex pattern matched against test name
./phpunit -c phpunit.xml.dist --filter "/testGet.*Route/"

# A data-provider test case index
./phpunit -c phpunit.xml.dist --filter "testExtract#5"
```

### 15.5 — Display PHP 8.x Notices, Warnings, and Deprecations

```bash
# Show everything — useful when verifying a clean baseline
php /root/.config/composer/vendor/bin/phpunit \
    --no-coverage \
    --display-deprecations \
    --display-warnings \
    --display-notices \
    -c phpunit.xml.dist 2>&1 | tee /tmp/phpunit-full.txt

# Count deprecations only
grep -c 'Deprecation' /tmp/phpunit-full.txt
```

### 15.6 — Code Coverage Reports

Code coverage requires **Xdebug** (mode `coverage`) or **PCOV**. Do not run coverage
during regular development — it slows execution by 5–10×.

```bash
# Confirm a coverage driver is available
php -m | grep -E 'Xdebug|pcov'

# HTML report — open build/coverage/index.html in your browser
php /root/.config/composer/vendor/bin/phpunit \
    --coverage-html=build/coverage \
    -c phpunit.xml.dist \
    src/Symfony/Component/HttpFoundation/

# Text summary (quick view)
php /root/.config/composer/vendor/bin/phpunit \
    --coverage-text \
    -c phpunit.xml.dist \
    src/Symfony/Component/Routing/

# Clover XML for CI (Codecov, Coveralls, SonarQube)
php /root/.config/composer/vendor/bin/phpunit \
    --coverage-clover=build/logs/clover.xml \
    -c phpunit.xml.dist \
    src/Symfony/Component/

# JUnit XML for CI test-result reporting
php /root/.config/composer/vendor/bin/phpunit \
    --no-coverage \
    --log-junit=build/logs/junit.xml \
    -c phpunit.xml.dist
```

### 15.7 — PHPUnit 11 Configuration (phpunit.xml.dist)

The project's `phpunit.xml.dist` uses the PHPUnit 11 schema. Key sections:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         colors="true"
         stopOnFailure="false"
         bootstrap="vendor/autoload.php">

    <testsuites>
        <testsuite name="Symfony Test Suite">
            <directory>src/Symfony/</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory suffix=".php">src/Symfony/</directory>
        </include>
        <exclude>
            <directory>src/Symfony/Component/*/Tests</directory>
            <directory>src/Symfony/Bridge/*/Tests</directory>
            <directory>src/Symfony/Bundle/*/Tests</directory>
        </exclude>
    </source>
</phpunit>
```

To add your application bundle to the test suite, append a `<directory>` entry:

```xml
<testsuites>
    <testsuite name="Symfony Test Suite">
        <directory>src/Symfony/</directory>
        <directory>src/AppBundle/Tests/</directory>   <!-- your bundle -->
    </testsuite>
</testsuites>
```

### 15.8 — PHPUnit 11 Attributes vs Docblock Annotations

PHPUnit 11 uses **PHP 8 native attributes** in place of docblock annotations. The 7x
Prime framework test suite uses both (for backward compatibility scanning), but all new
tests should use attributes.

**Attribute import:**

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\BackupGlobals;
```

**Full attribute comparison:**

| Old docblock annotation | PHPUnit 11 attribute |
|------------------------|---------------------|
| `@dataProvider foo` | `#[DataProvider('foo')]` |
| `@depends testBar` | `#[Depends('testBar')]` |
| `@group name` | `#[Group('name')]` |
| `@covers Foo::bar` | `#[CoversMethod(Foo::class, 'bar')]` |
| `@coversClass Foo` | `#[CoversClass(Foo::class)]` |
| `@uses Foo` | `#[UsesClass(Foo::class)]` |
| `@before` | `#[Before]` |
| `@after` | `#[After]` |
| `@requires PHP 8.1` | `#[RequiresPhp('8.1')]` |
| `@requires extension intl` | `#[RequiresPhpExtension('intl')]` |
| `@runInSeparateProcess` | `#[RunInSeparateProcess]` |
| `@backupGlobals enabled` | `#[BackupGlobals(true)]` |
| `@testdox Some description` | `#[TestDox('Some description')]` |
| `@expectedExceptionMessage …` | `$this->expectExceptionMessage(…)` in the test body |

**Inline data with `#[TestWith]` (no separate method needed):**

```php
#[TestWith([1, 2])]
#[TestWith([5, 10])]
#[TestWith([0, 0])]
public function testDouble(int $input, int $expected): void
{
    $this->assertSame($expected, $input * 2);
}
```

### 15.9 — Running Tests in CI (GitHub Actions example)

```yaml
# .github/workflows/test.yml
name: Test Suite

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.1', '8.2', '8.3', '8.4', '8.5']

    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, xml, intl, curl, pdo
          coverage: none

      - name: Install Composer packages
        run: composer install --no-interaction --prefer-dist

      - name: Install PHPUnit 11
        run: composer global require phpunit/phpunit ^11 --no-interaction

      - name: Run the test suite
        run: |
          php /root/.config/composer/vendor/bin/phpunit \
            --no-coverage \
            --display-deprecations \
            --display-warnings \
            -c phpunit.xml.dist \
            --log-junit=build/logs/junit.xml

      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: phpunit-results-php${{ matrix.php }}
          path: build/logs/junit.xml
```

### 15.10 — Interpreting Test Output

| Symbol | Meaning |
|--------|---------|
| `.` | Test passed |
| `F` | Assertion failure |
| `E` | PHP error (exception, fatal error, parse error) |
| `S` | Skipped (environment requirement not met) |
| `I` | Incomplete (test marked as TODO) |
| `W` | Warning (non-fatal PHP warning caught by PHPUnit) |
| `D` | Deprecation (PHP or framework deprecation triggered) |
| `N` | Notice (PHP notice triggered) |
| `R` | Risky (test ran but no assertions were made) |

A clean 7x Prime run should show only `.` and `S` characters in the progress output.

---

## 16. Writing, Extending, and Organising Your Own Tests

This section covers how to write tests against 7x Prime components and bundles using
PHPUnit 11, how to use the Symfony-specific test base classes, how to extend the framework
test suite, and how to organise tests in a maintainable way.

### 16.1 — Directory Layout

Place test files in your bundle's `Tests/` directory, mirroring the source structure:

```
src/AppBundle/
├── Controller/
│   └── ArticleController.php
├── Entity/
│   └── Article.php
├── Service/
│   └── Slugger.php
└── Tests/
    ├── Controller/
    │   └── ArticleControllerTest.php   ← functional test (WebTestCase)
    ├── Entity/
    │   └── ArticleTest.php             ← unit test (TestCase)
    ├── Service/
    │   └── SluggerTest.php             ← unit test (TestCase)
    └── Fixtures/
        └── articles.yml                ← test data (loaded by fixtures)
```

PHPUnit discovers all `*Test.php` files automatically. No registration is needed.

### 16.2 — Unit Test (PHPUnit\Framework\TestCase)

Use `TestCase` for classes that have no Symfony container dependency:

```php
<?php
// src/AppBundle/Tests/Service/SluggerTest.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('unit')]
class SluggerTest extends TestCase
{
    private Slugger $slugger;

    protected function setUp(): void
    {
        $this->slugger = new \AppBundle\Service\Slugger();
    }

    #[DataProvider('slugProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->slugger->slugify($input));
    }

    public static function slugProvider(): array
    {
        return [
            'simple words'     => ['Hello World',       'hello-world'],
            'special chars'    => ['7x Prime 2.9!',     '7x-prime-2-9'],
            'existing hyphens' => ['already-slugified', 'already-slugified'],
            'empty string'     => ['',                  ''],
            'unicode'          => ['Ünïcödé',            'unicode'],
        ];
    }

    public function testSlugifyThrowsOnNullByte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Null bytes are not allowed');
        $this->slugger->slugify("hello\0world");
    }
}
```

### 16.3 — Functional / Web Test (WebTestCase)

Use `WebTestCase` for controllers and routes. It boots the full Symfony kernel:

```php
<?php
// src/AppBundle/Tests/Controller/ArticleControllerTest.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
class ArticleControllerTest extends WebTestCase
{
    public function testIndexReturns200(): void
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/articles');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testShow404WhenSlugNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/articles/this-does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateArticleRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/articles/new');

        // Should redirect to login
        $this->assertResponseRedirects('/login');
    }

    public function testJsonEndpointReturnsValidJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('articles', $data);
    }
}
```

**PHPUnit 11 HTTP assertion helpers** (from `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`):

| Method | Description |
|--------|-------------|
| `assertResponseIsSuccessful()` | Status code 200–299 |
| `assertResponseStatusCodeSame(int $code)` | Exact status code match |
| `assertResponseRedirects(string $url)` | 3xx redirect to given URL |
| `assertResponseHasHeader(string $header)` | Response has the header |
| `assertResponseHeaderSame(string $h, string $v)` | Header has exact value |
| `assertSelectorExists(string $selector)` | CSS selector found in DOM |
| `assertSelectorTextContains(string $sel, string $text)` | Element text contains value |
| `assertPageTitleContains(string $text)` | `<title>` contains value |
| `assertFormValue(string $form, string $field, string $val)` | Form field has value |

### 16.4 — Kernel Test (KernelTestCase)

Use `KernelTestCase` when you need the DI container but not the HTTP layer:

```php
<?php
// src/AppBundle/Tests/Service/MailerTest.php

namespace AppBundle\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class MailerTest extends KernelTestCase
{
    private \AppBundle\Service\MailerService $mailer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mailer = self::$kernel->getContainer()->get('app.mailer');
    }

    public function testWelcomeEmailIsQueued(): void
    {
        $count = $this->mailer->sendWelcome('user@example.com', 'Alice');
        $this->assertSame(1, $count);
    }
}
```

### 16.5 — Mocking with PHPUnit 11

PHPUnit 11 creates mocks via `$this->createMock()`, `$this->createStub()`, and
`$this->getMockBuilder()`. The old `getMock()` API was removed:

```php
// Create a mock of a concrete class
$repo = $this->createMock(\AppBundle\Repository\ArticleRepository::class);

// Stub a return value
$repo->method('findPublishedBySlug')
     ->with('hello-world')
     ->willReturn($article);

// Assert a method is called exactly once
$mailer = $this->createMock(\AppBundle\Service\MailerService::class);
$mailer->expects($this->once())
       ->method('sendWelcome')
       ->with('user@example.com', $this->anything());

// Stub a sequence of return values
$cache = $this->createMock(\Symfony\Component\Cache\Adapter\FilesystemAdapter::class);
$cache->method('get')->willReturnOnConsecutiveCalls(null, $cachedItem, $cachedItem);

// Throw an exception from a mock
$pdo = $this->createMock(\PDO::class);
$pdo->method('prepare')->willThrowException(new \PDOException('Connection refused'));
```

### 16.6 — Writing a Custom Test Base Class

For repeated setup across many tests in your bundle, create a base class:

```php
<?php
// src/AppBundle/Tests/AppTestCase.php

/*
 * (c) 2004-2026 7x <info@se7enx.com>
 */

namespace AppBundle\Tests;

use PHPUnit\Framework\TestCase;
use AppBundle\Entity\Article;

abstract class AppTestCase extends TestCase
{
    /** Create a valid Article entity pre-populated with test data. */
    protected function makeArticle(array $overrides = []): Article
    {
        $article = new Article();
        $article->setTitle($overrides['title']   ?? 'Test Article');
        $article->setSlug($overrides['slug']     ?? 'test-article');
        $article->setBody($overrides['body']     ?? 'Test body content.');
        $article->setPublished($overrides['pub'] ?? true);
        $article->setCreatedAt(new \DateTime($overrides['date'] ?? 'now'));
        return $article;
    }

    /** Assert that a Response object has a JSON body matching the given keys. */
    protected function assertJsonKeys(
        \Symfony\Component\HttpFoundation\Response $response,
        array $expectedKeys
    ): void {
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data, 'Response body is not valid JSON');
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $data, "JSON key '$key' not found in response");
        }
    }
}
```

Extend it in your test classes:

```php
class ArticleTest extends \AppBundle\Tests\AppTestCase
{
    public function testDefaultPublishedState(): void
    {
        $article = $this->makeArticle(['pub' => false]);
        $this->assertFalse($article->isPublished());
    }
}
```

### 16.7 — Extending Framework Component Tests

To add or patch tests for a 7x Prime framework component, create your test file in the
component's `Tests/` directory. The file will be discovered automatically:

```php
<?php
// src/Symfony/Component/HttpFoundation/Tests/MyNewCookieTest.php

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

class MyNewCookieTest extends TestCase
{
    public function testSameSiteDefaultsToLax(): void
    {
        $cookie = new Cookie('test', 'value');
        $this->assertStringContainsString('SameSite=Lax', (string) $cookie);
    }

    public function testSameSiteNoneRequiresSecure(): void
    {
        $cookie = new Cookie('session', 'abc', 0, '/', null, true, true, 'None');
        $this->assertStringContainsString('SameSite=None', (string) $cookie);
        $this->assertStringContainsString('secure', strtolower((string) $cookie));
    }
}
```

Run just the new test:

```bash
./phpunit -c phpunit.xml.dist --filter MyNewCookieTest
```

### 16.8 — Test Fixtures and Data Providers

Keep data providers as `public static` methods returning arrays of named cases:

```php
public static function validSlugProvider(): \Generator
{
    // Using a Generator is also supported by PHPUnit 11
    yield 'simple'          => ['hello world',  'hello-world'];
    yield 'numbers'         => ['article 42',   'article-42'];
    yield 'already clean'   => ['clean-slug',   'clean-slug'];
}

public static function invalidSlugProvider(): array
{
    return [
        'empty string'    => ['',              \InvalidArgumentException::class],
        'null byte'       => ["foo\0bar",       \InvalidArgumentException::class],
        'too long'        => [str_repeat('a', 200), \LengthException::class],
    ];
}
```

Store complex fixture objects in `Tests/Fixtures/`:

```
src/AppBundle/Tests/Fixtures/
├── articles.yml          ← YAML fixture data loaded by DoctrineFixturesBundle
├── DummyEntity.php       ← Minimal entity used only in tests
└── TestKernel.php        ← Minimal AppKernel for isolated integration tests
```

### 16.9 — Isolated Integration Tests with a Minimal Kernel

When testing a component that needs the container but you want to avoid booting
the full application kernel, define a minimal test kernel:

```php
<?php
// src/AppBundle/Tests/Fixtures/TestKernel.php

namespace AppBundle\Tests\Fixtures;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    public function registerBundles(): array
    {
        return [
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \AppBundle\AppBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config_test.yml');
    }

    public function getCacheDir(): string { return sys_get_temp_dir() . '/prime_test_cache'; }
    public function getLogDir(): string   { return sys_get_temp_dir() . '/prime_test_logs'; }
}
```

Use it in a `KernelTestCase`:

```php
protected static function getKernelClass(): string
{
    return \AppBundle\Tests\Fixtures\TestKernel::class;
}
```

### 16.10 — Checking for Regressions After Patching Vendor Code

After patching any file under `vendor/` (as 7x Prime does for PHP 8.5 compatibility),
always run the full test suite for the affected component to confirm no regressions:

```bash
# After patching vendor/zendframework/zend-code
./phpunit -c phpunit.xml.dist src/Symfony/Bridge/*/Tests/ --filter "Param|Reflect"

# After patching vendor/doctrine
./phpunit -c phpunit.xml.dist src/Symfony/Bridge/Doctrine/Tests/

# After patching any Symfony component
./phpunit -c phpunit.xml.dist src/Symfony/Component/PropertyInfo/Tests/

# Run the full suite to confirm global baseline is intact
php /root/.config/composer/vendor/bin/phpunit --no-coverage \
    --display-deprecations --display-warnings --display-notices \
    -c phpunit.xml.dist 2>&1 | tail -20
```

A clean baseline is:
```
OK (NNNN tests, NNNN assertions)
```
Any `E`, `F`, or `W` in the progress line requires investigation before committing.

---

## 17. Console CLI Commands

The framework ships a console binary at `bin/console`:

```bash
# ── General ──────────────────────────────────────────────────────────────────
php bin/console list                                # list all available commands
php bin/console help <command>                      # help for a specific command

# ── Cache ────────────────────────────────────────────────────────────────────
php bin/console cache:clear                         # clear dev cache
php bin/console cache:clear --env=prod              # clear production cache
php bin/console cache:warmup --env=prod             # warm up production cache

# ── Routing ──────────────────────────────────────────────────────────────────
php bin/console debug:router                        # show all routes
php bin/console router:match /blog/my-post          # test which route matches a URL

# ── Container / Services ─────────────────────────────────────────────────────
php bin/console debug:container                     # list all services
php bin/console debug:container app.pdo             # inspect a specific service

# ── Doctrine ─────────────────────────────────────────────────────────────────
php bin/console doctrine:schema:validate            # validate entity mapping
php bin/console doctrine:schema:update --dump-sql   # preview schema SQL
php bin/console doctrine:schema:update --force      # apply schema changes
php bin/console doctrine:migrations:migrate         # run pending migrations
php bin/console doctrine:fixtures:load              # load data fixtures

# ── Bundle Generation ─────────────────────────────────────────────────────────
php bin/console generate:bundle                     # scaffold a new bundle
php bin/console generate:controller                 # scaffold a controller

# ── Migration Helpers (prime:migrate:*) ───────────────────────────────────────
# All commands support --dir=PATH (default: src/)
# Fixable commands support --fix (apply) and --fix --dry-run (preview)

php bin/console prime:migrate:check    --dir=src/             # run all checks; show summary + fix suggestions
php bin/console prime:migrate:nullable --dir=src/             # scan implicit nullable types
php bin/console prime:migrate:nullable --dir=src/ --fix       # auto-fix implicit nullable types
php bin/console prime:migrate:forms    --dir=src/             # scan string form type aliases
php bin/console prime:migrate:forms    --dir=src/ --fix       # auto-fix string form type aliases + inject use statements
php bin/console prime:migrate:constraints --dir=src/          # scan reserved constraint names (True/False/Null)
php bin/console prime:migrate:constraints --dir=src/ --fix    # auto-fix Constraints\True → Constraints\IsTrue etc.
php bin/console prime:migrate:twig     --dir=src/             # scan Twig_* legacy class names
php bin/console prime:migrate:twig     --dir=src/ --fix       # auto-fix known Twig_* → Twig\ PSR-4 names
php bin/console prime:migrate:yaml     --dir=src/             # scan YAML !php/object: tags
php bin/console prime:migrate:yaml     --dir=src/ --fix       # strip !php/object: tags (values become plain strings)
php bin/console prime:migrate:report   --dir=src/             # full text report
php bin/console prime:migrate:report   --dir=src/ --format=html --output=report.html  # HTML report

# Always preview before writing: add --dry-run to any --fix command
php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run

# ── Composer ─────────────────────────────────────────────────────────────────
composer install                                    # install packages
composer require vendor/package                     # add a package
composer remove vendor/package                      # remove a package
composer dump-autoload -o                           # rebuild optimised autoloader
composer audit                                      # check for known vulnerabilities
composer show                                       # list installed packages
```

---

## 18. Cache Management

### Clear the Application Cache

```bash
php bin/console cache:clear                   # development (default env)
php bin/console cache:clear --env=prod        # production
```

Or manually:

```bash
rm -rf app/cache/*
```

### PHP OPcache (Production)

Ensure `opcache.validate_timestamps=0` in production `php.ini` for maximum performance.
Clear OPcache after deployment:

```bash
php -r "opcache_reset();"
# or via a web endpoint behind authentication that calls opcache_reset()
```

### HTTP Cache (AppCache)

For full-page HTTP caching, use `web/app.php` with `AppCache`:

```php
// web/app.php
require_once __DIR__.'/../app/AppCache.php';
$kernel = new AppCache(new AppKernel('prod', false));
```

---

## 19. Deployment Checklist

### Pre-Deployment

```bash
# 1. Run the full test suite — all tests must pass
./phpunit -c phpunit.xml.dist --no-coverage

# 2. Install Composer packages (production mode — no dev deps)
composer install --no-dev --optimize-autoloader

# 3. Review parameters.yml / environment variables — no secrets in git
git log --follow -p app/config/parameters.yml 2>/dev/null   # should return nothing

# 4. Verify PHP 8.x compatibility
php -n -l src/AppBundle/Controller/DefaultController.php
```

### Post-Deployment

```bash
# 5. Clear the production cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 6. Set correct file permissions
chmod -R 775 app/cache app/logs
chown -R www-data:www-data app/cache app/logs

# 7. Confirm the site loads
curl -Is http://yoursite.com/ | head -5
# HTTP/1.1 200 OK

# 8. Verify the project root is NOT publicly accessible
curl -Is http://yoursite.com/../composer.json | head -3
# Should be 403 Forbidden or 404 — never a 200 OK
```

### Security Hardening

- The `DocumentRoot` is `web/` — `app/`, `src/`, `vendor/`, `composer.json`, and `.env` files
  live above the web root and are unreachable over HTTP by design
- Set `$kernel = new AppKernel('prod', false)` in `web/app.php` for production (debug off)
- Use HTTPS — configure TLS in Apache/Nginx and redirect HTTP to HTTPS
- Review database credentials — use `parameters.yml` (gitignored) or environment variables
- Run `composer audit` to check installed packages for known CVEs
- Cookies default to `SameSite=Lax` in 7x Prime — verify your session configuration

---

## 20. Upgrading from Symfony 2.8

See **[UPGRADE-2.9.md](UPGRADE-2.9.md)** for the complete migration guide.
Key breaking changes addressed in the `2.9` branch:

- **PHP ^8.0 required** — was `>=5.3.9` upstream; all PHP 8.x deprecations resolved.
- **SameSite cookies** — cookies now default to `SameSite=Lax`; update any cross-site cookie usage.
- **YAML `!php/object:` deserialization is restricted** — prevent object injection attacks;
  use `Yaml::PARSE_OBJECT_FOR_MAP` where object parsing is intentional.
- **CRLF-injection protection** — header values are sanitised; raw `\r\n` in header strings
  will throw an `InvalidArgumentException`.
- **Obsolete polyfills removed** — `symfony/polyfill-*` entries removed from `composer.json`
  since PHP 8.x provides all required functions natively.
- **Hardened session defaults** — `cookie_httponly=true`, `cookie_secure` recommended for HTTPS.

---

## 21. Troubleshooting

**Q: I get a blank page or 500 error after installation.**
A: Check PHP error logs:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```
Use `web/app_dev.php` in development — it enables the profiler and shows full error pages with
stack traces. Ensure `APP_ENV=dev` is not blocked by your firewall configuration in `app_dev.php`.

---

**Q: Routes are not matching — I always get 404.**
A: Verify `mod_rewrite` is enabled (`a2enmod rewrite`) and `AllowOverride All` is set for your Apache
`DocumentRoot` (`/var/www/myapp/web`). For Nginx, confirm `try_files` includes `app.php$is_args$args`.
Run `php bin/console debug:router` to list all registered routes and check for typos.

---

**Q: The DI container cannot find a service.**
A: Run `php bin/console debug:container` to list all registered services. Ensure your service is
declared in `app/config/services.yml` or automatically discovered via bundle configuration. Clear
the cache after any configuration change: `php bin/console cache:clear`.

---

**Q: Composer packages are not found at runtime.**
A: Verify `vendor/autoload.php` exists (`composer install` must have been run). Confirm `web/app.php`
or `web/app_dev.php` includes `require_once __DIR__.'/../vendor/autoload.php';`. Use `composer show`
to list installed packages and verify the package name.

---

**Q: PHPUnit tests fail with PHP 8.x type errors.**
A: You may be running a test that was not updated to the PHP 8.x baseline. Check the `2.9` branch
for the latest test files. Report regressions at:
[github.com/se7enxweb/prime/issues](https://github.com/se7enxweb/prime/issues)

---

**Q: Twig throws `Template … not found`.**
A: Twig looks for templates in `app/Resources/views/` and then in each bundle's
`Resources/views/`. Check the template path syntax: `AppBundle:Article:show` resolves to
`src/AppBundle/Resources/views/Article/show.html.twig`. Use
`php bin/console debug:twig` to inspect template paths.

---

**Q: Doctrine fails with `Table … doesn't exist`.**
A: Run `php bin/console doctrine:schema:update --force` to create or update the database schema.
Verify the connection parameters in `app/config/parameters.yml`. Check with
`php bin/console doctrine:schema:validate`.

---

**Q: How do I get commercial support?**
A: Contact [support@se7enx.com](mailto:support@se7enx.com) or visit [se7enx.com](https://se7enx.com/).

---

## Copyright

```
Copyright (C) 2004-2026 7x (se7enx.com). All rights reserved.
Portions copyright (C) 2004-2024 Fabien Potencier <fabien@symfony.com>.
See CONTRIBUTORS.md for the full contributor list.
```

## License

Licensed under the **MIT License**. See [LICENSE](LICENSE) for the full text.
