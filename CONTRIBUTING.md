# Contributing

Thank you for contributing to `padosoft/laravel-evidence-risk-review`.

## Local Setup

```bash
composer install
vendor/bin/phpunit
```

## Required Gates

Run these before opening a PR:

```bash
composer validate --strict --no-interaction --no-ansi
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
vendor/bin/phpunit
npx --yes @redocly/cli@latest lint resources/openapi.yaml
```

## Project Rules

- Keep the package standalone and host-agnostic.
- Keep HTTP, MCP, LLM, and persistence behavior default-OFF.
- Put business logic in core services, not adapters.
- Add tests for every behavior change.
- Add Playwright only when UI/UX behavior is introduced.

Read `AGENTS.md`, `CLAUDE.md`, `docs/RULES.md`, `docs/PROGRESS.md`, and `docs/LESSON.md` before large changes.
