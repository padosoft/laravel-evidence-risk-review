# Laravel Evidence Risk Review

![Laravel Evidence Risk Review dashboard](resources/screenshots/laravel-evidence-risk-review-admin-Dashboard-dark.png)

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)
[![CI](https://github.com/padosoft/laravel-evidence-risk-review/actions/workflows/ci.yml/badge.svg)](https://github.com/padosoft/laravel-evidence-risk-review/actions/workflows/ci.yml)

Evidence-aware risk review guardrails for Laravel applications, AI products, RAG systems, and MCP tools.

This package labels source strength, detects risky claims, keeps LLM calls default-OFF, records review evidence when enabled, and exposes the same core engine through PHP, Artisan, HTTP, and MCP surfaces.

## Table Of Contents

- [Why It Exists](#why-it-exists)
- [What Is Inside](#what-is-inside)
- [Quick Start](#quick-start)
- [PHP Surface](#php-surface)
- [Artisan Surface](#artisan-surface)
- [HTTP Surface](#http-surface)
- [MCP Surface](#mcp-surface)
- [Configuration](#configuration)
- [Profiles And Taxonomy](#profiles-and-taxonomy)
- [Review Logs](#review-logs)
- [Testing](#testing)
- [Architecture](#architecture)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Why It Exists

LLM answers often look confident before they are well supported. `padosoft/laravel-evidence-risk-review` gives Laravel teams a deterministic review layer that can run before publishing, storing, streaming, or acting on AI-generated content.

The core idea is simple:

- classify every source into a configured evidence tier
- compare claim assertiveness against required evidence strength
- run cheap deterministic checks first
- call expensive or external LLM review only when explicitly enabled
- return structured findings that adapters can use consistently

## What Is Inside

| Surface | Purpose |
| --- | --- |
| PHP service and facade | Direct package API for Laravel code. |
| Artisan commands | Local review, profile, taxonomy, and log inspection. |
| HTTP API | Default-OFF REST endpoints with OpenAPI 3.1. |
| MCP registry | Framework-agnostic tool definitions and handlers. |
| Review logs | Null, in-memory, and database append-only stores. |
| Profiles | Built-in default, engineering, medical, legal, and finance profiles. |

## Quick Start

Install the package:

```bash
composer require padosoft/laravel-evidence-risk-review
```

Publish config and the optional database migration:

```bash
php artisan vendor:publish --tag=evidence-risk-review-config
php artisan vendor:publish --tag=evidence-risk-review-migrations
```

Run a dry review from PHP:

```php
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Facades\EvidenceRiskReview;

$result = EvidenceRiskReview::review(new ReviewArtifact(
    artifactId: 'answer-123',
    answerText: 'This likely helps when the documented prerequisites are met.',
));

return $result->toArray();
```

Run a dry review from the CLI:

```bash
php artisan evidence:review artifact.json --dry-run
```

Enable nothing else until you need it. HTTP, MCP integrations, LLM calls, and persistence are designed to stay opt-in.

## PHP Surface

```php
use Padosoft\EvidenceRiskReview\Facades\EvidenceRiskReview;

$arrayResult = EvidenceRiskReview::reviewArray([
    'artifact_id' => 'answer-124',
    'answer_text' => 'This always cures the condition.',
    'claims' => [[
        'id' => 'c1',
        'text' => 'This always cures the condition.',
        'assertiveness' => 'definitive',
        'source_ids' => ['s1'],
    ]],
    'sources' => [[
        'id' => 's1',
        'declared_tier' => 'blog',
    ]],
    'options' => [
        'profile_key' => 'medical',
        'dry_run' => true,
    ],
]);

$tier = EvidenceRiskReview::labelTier([
    'id' => 'source-1',
    'url' => 'https://arxiv.org/abs/1234.5678',
]);

$profiles = EvidenceRiskReview::listProfiles();
$taxonomy = EvidenceRiskReview::taxonomy();
```

## Artisan Surface

```bash
php artisan evidence:review artifact.json --dry-run
php artisan evidence:profiles
php artisan evidence:taxonomy
php artisan evidence:log --limit=25
```

`evidence:review` exits with:

| Code | Meaning |
| --- | --- |
| `0` | Review completed and no findings were produced. |
| `2` | Review completed and findings were produced. |
| `1` | Invalid input, unknown profile, unavailable dependency, or runtime failure. |

## HTTP Surface

The HTTP API is default-OFF. Enable it explicitly:

```php
'api' => [
    'enabled' => env('EVIDENCE_RISK_REVIEW_API_ENABLED', false),
    'prefix' => env('EVIDENCE_RISK_REVIEW_API_PREFIX', 'evidence-risk-review/api'),
    'middleware' => [],
],
```

Available endpoints when enabled:

```text
POST /evidence-risk-review/api/reviews
GET  /evidence-risk-review/api/reviews/{review}
GET  /evidence-risk-review/api/profiles
GET  /evidence-risk-review/api/profiles/{key}
GET  /evidence-risk-review/api/taxonomy
GET  /evidence-risk-review/api/openapi.yaml
```

HTTP errors use a stable envelope:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Expected non-empty string at [artifact_id].",
    "details": {}
  }
}
```

## MCP Surface

The MCP layer is framework-agnostic:

```php
use Padosoft\EvidenceRiskReview\Mcp\McpToolRegistry;

$registry = app(McpToolRegistry::class);

$definitions = array_map(
    static fn ($definition) => $definition->toArray(),
    $registry->definitions(),
);

$result = $registry->handle('evidence_review.assess', [
    'artifact_id' => 'answer-125',
    'answer_text' => 'No claims to check.',
    'options' => ['dry_run' => true],
]);
```

Available tools:

```text
evidence_review.assess
evidence_review.label_tier
evidence_review.list_profiles
```

## Configuration

The package config is published to `config/evidence-risk-review.php`.

Important defaults:

| Key | Default | Effect |
| --- | --- | --- |
| `api.enabled` | `false` | HTTP routes are not registered unless enabled. |
| `mcp.enabled` | `false` | Hosts decide if and how to expose MCP tools. |
| `llm.enabled` | `false` | No external LLM calls happen by default. |
| `review_log.store` | `null` | No persistence unless `array` or `database` is configured. |
| `default_profile` | `default` | Review profile used when no option is supplied. |

See `.env.example` for the supported environment variables.

## Profiles And Taxonomy

Built-in profiles:

- `default`
- `engineering`
- `medical`
- `legal`
- `finance`

Evidence tiers are configurable. Built-ins include guideline, peer-reviewed, official, preprint, news, blog, search hint, and unverified.

Profiles decide which risk checks are enabled and what minimum source tier each claim assertiveness level requires.

## Review Logs

Supported stores:

- `null`: default, append is a no-op
- `array`: useful for tests and in-process inspection
- `database`: append-only table published through the package migration

Enable database logs:

```env
EVIDENCE_RISK_REVIEW_LOG_STORE=database
EVIDENCE_RISK_REVIEW_LOG_CONNECTION=mysql
EVIDENCE_RISK_REVIEW_LOG_TABLE=evidence_risk_review_logs
```

## Testing

Local gates:

```bash
composer validate --strict --no-interaction --no-ansi
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
vendor/bin/phpunit
npx --yes @redocly/cli@latest lint resources/openapi.yaml
```

Live tests are opt-in and skip unless explicitly enabled:

```bash
EVIDENCE_RISK_REVIEW_LIVE=1 vendor/bin/phpunit --testsuite Live
```

## Architecture

The package keeps one core engine and thin adapters:

```text
ReviewArtifact / ReviewOptions
        |
        v
ReviewEngine
        |
        +-- EvidenceTierLabeler
        +-- RiskSweepEngine
        +-- EvidenceReviewerLlmContract
        +-- ReviewLogStore
        |
        v
PHP facade / Artisan / HTTP / MCP
```

Business rules live in core services and DTOs. Controllers, commands, and MCP handlers adapt input and output only.

## Security

- LLM calls are default-OFF.
- HTTP routes are default-OFF.
- Review logging is default-OFF.
- Unknown config values fail loudly.
- The package has no AskMyDocs or host-app namespace dependency.

Report vulnerabilities through the process in [SECURITY.md](SECURITY.md).

## Contributing

Read [CONTRIBUTING.md](CONTRIBUTING.md), `AGENTS.md`, `CLAUDE.md`, `docs/RULES.md`, and `docs/LESSON.md` before opening a PR.

The repo includes a Claude/agent/vibe-coding pack under `.claude/` and `skills/` so future agent sessions inherit the project rules.

## License

Apache-2.0. See [LICENSE](LICENSE).
