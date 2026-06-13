# Copilot Instructions For Laravel Evidence Risk Review

This repository is a reusable Laravel package, not a host application.

Important project context:

- Target Laravel 13 and PHP `^8.3`; CI is planned for PHP 8.3, 8.4, and 8.5.
- The package must remain standalone-agnostic: no AskMyDocs, sibling package, host table, or host service references in `src/`.
- HTTP API, MCP, and LLM integrations are default-OFF. Flag any change that enables networked or external behavior by default.
- There is no hard LLM SDK dependency. Hosts bind `EvidenceReviewerLlmContract`.
- `ReviewEngine` is the orchestration core. Facades, Artisan commands, HTTP controllers, and MCP adapters must stay thin.
- Review logs are append-only. Flag update/delete paths unless explicitly justified by a new documented requirement.
- Heavy or LLM checks must be budget-gated and auditable when skipped.
- Domain profile content belongs in config/profile data, not hard-coded core service logic.

Review priorities:

- Standalone package boundaries and default-OFF behavior.
- Missing tests for OFF/ON paths, budget exhaustion, unknown profiles, unknown tiers, append failures, and no-network default tests.
- Security issues involving API keys, authorization headers, provider tokens, raw LLM payloads, profile hints, and command/API output.
- Laravel package ergonomics: service provider, config publishing, migrations, commands, Testbench feature tests, and architecture tests.
- Drift between README/process docs and actual commands or gates.
