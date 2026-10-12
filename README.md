# Sports Analytics India

A PHP website for cricket articles, match coverage, and gaming guides. The current site configuration uses the **BOBI / Best Online Gaming India** identity. Site naming and public URLs are defined in the source configuration rather than by the GitHub repository name.

## Features

- A responsive homepage, article pages, and guide categories.
- Shared PHP page templates, headers, and footers.
- A local cricket data cache and an optional configured upstream API.
- Visitor signals and a separately configured Telegram integration.
- SEO resources, image assets, and guarded content/deployment workflows.

## Requirements

- PHP 8.3, including cURL for optional upstream integrations.
- Apache with `mod_rewrite` and `mod_headers` for production routing and headers.
- Node.js 22 and repository secrets only for the optional Codex content workflow.

The website does not require npm dependencies or a frontend build step.

## Local preview

```sh
git clone https://github.com/fumakill4/sports-analytics-india-com.git
cd sports-analytics-india-com
php -S 127.0.0.1:8080 -t .
```

Open [the local homepage](http://127.0.0.1:8080/). Apache rewrite and security-header rules do not run in PHP's development server. Some alias routes therefore require Apache for a faithful preview. Keep development serving limited to the loopback interface.

Optional bot and upstream services are not included as working production credentials in this repository. They may remain unavailable during a basic frontend preview.

## Repository layout

| Path | Purpose |
| --- | --- |
| `index.php` | Homepage |
| `page-template.php` | Shared article/guide rendering |
| `includes/` | Site configuration, header, and footer |
| Article directories | Individual PHP page entry points |
| `data/cricket.json` | Local cricket content/cache data |
| `assets/` | Stylesheets, scripts, images, and media |
| `api/` | Visitor signals, cache update endpoint, and private bot entry wrapper |
| `tools/update-cricket-cache.php` | Cache maintenance utility |
| `.github/` | Guarded automation and manual deployment |
| `.htaccess` | Apache routing and response headers |

## Optional integrations

The integration code expects a private sibling `private_bot` directory outside the public document root. Its private configuration can provide upstream API credentials, Telegram settings, and synchronization secrets. The Telegram wrapper also expects a private bot entry file, which is deliberately not part of this repository.

Create and manage these values through your hosting environment or secret manager. Do not commit configuration, bot state, customer records, tokens, or personal contact details. Changes to the cache endpoint require its configured synchronization secret.

## Content validation

```sh
php -l index.php
php -l page-template.php
php -l api/update-cricket.php
php -l api/visit-signal.php
```

Verify article slugs, internal links, sitemap entries, and referenced images after editing content. Do not invoke maintenance tools against a production service merely to test page rendering.

## Deployment and security

The **Manual Safe Deploy** workflow uses an explicit upload allowlist and defaults to a dry run. Supply hosting credentials through GitHub Actions secrets and review the selected files before uploading. The workflow intentionally protects application and server configuration paths.

Visitor authentication and transport corrections are being reviewed in [security PR #1](https://github.com/fumakill4/sports-analytics-india-com/pull/1). Deploy the endpoint and browser changes together, keep administrative keys private, and enforce appropriate rate limits at the hosting layer. The private Telegram implementation and deployed service policies must be reviewed separately.

## Maintenance

Maintained by **i729**. Preserve source attribution and verify permission to reuse third-party images, branding, and article material.
