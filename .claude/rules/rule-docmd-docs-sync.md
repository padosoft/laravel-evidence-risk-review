# Rule: docmd Documentation Auto-Sync

This rule is mandatory and blocking.

Whenever a change adds or modifies a user-facing feature, public API behavior, configuration option, command, HTTP endpoint, MCP tool, error contract, deployment instruction, or substantially updates the README, the same work must update the corresponding docmd page under `docs-site/docs/**`.

If a new page is added, it must also be registered in `docs-site/docmd.config.json` under `navigation[]`. A page that exists but is absent from navigation is incomplete.

Use the `.claude/skills/docmd-docs/SKILL.md` skill for syntax, layout, semantic search, Cloudflare Pages, and page-structure rules.

Documentation update is not required for purely internal refactors, tooling-only fixes, formatting-only changes, test-only changes that do not alter public behavior, or cosmetic wording changes outside public docs. When skipping docs for one of these reasons, state the reason in the changelog entry, PR body, or progress note.

Before closing any work that touches docmd docs, run from `docs-site/`:

```bash
npm run check
npm run build
```

Anti-patterns that block completion:

- Shipping a user-facing feature without updating the matching docmd page.
- Adding a doc page without adding it to `navigation[]`.
- Reintroducing MDX, JSX, or raw component tags in Markdown.
- Claiming docs are valid without a real build.
- Letting semantic search trigger an interactive model-selection wizard in CI.
