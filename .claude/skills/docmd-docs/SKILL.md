---
name: docmd-docs
description: Use whenever working in docs-site/, adding or editing public documentation pages, changing docmd navigation, plugins, semantic search, branding, Cloudflare Pages deployment, or keeping docs synchronized with user-facing package features.
---

# docmd Docs Skill

Use this skill for the public documentation site in `docs-site/`.

## Layout

- `docmd.config.json` is the source for metadata, URL, navigation, theme, footer, and plugins.
- `docs/` contains every Markdown page. `docs/index.md` is the root route `/`.
- Routes mirror the file tree: `docs/guides/foo.md` becomes `/guides/foo`.
- `navigation[]` is the only sidebar source. Every new page must be added there.
- `assets/custom.css` owns brand overrides.
- `.docmd-search/config.json` is committed to avoid the interactive semantic-search wizard.

## Commands

Run from `docs-site/`:

```bash
npm run dev
npm run check
npm run build
```

`npm run check` rejects raw JSX/MDX-style component tags in Markdown. `npm run build` must pass before closing documentation work.

## Markdown Syntax

Use Markdown plus docmd containers only. Do not use MDX, JSX, or raw component tags.

| Need | Syntax |
| --- | --- |
| Callout | `::: callout info` ... `:::`; types: `info`, `tip`, `warning`, `danger`, `success` |
| Tabs | `::: tabs`, then `== tab "Label"`, close with `:::` |
| Steps | `::: steps`, numbered list, body indented three spaces, close with `:::` |
| Collapsible | `::: collapsible "Title"` ... `:::`; use `open` prefix for default-open |
| Cards | `::: grids` > `::: grid` > `::: card "Title" icon:lucide-name` > body > Markdown link > close each container |
| Diagrams | fenced `mermaid` blocks |
| Math | KaTeX inline `$...$` and block `$$...$$` |

Icons are Lucide icon names in kebab-case.

## Plugins

Keep these plugins active unless a build-proven incompatibility is documented: search, git, seo, sitemap, mermaid, math, llms, analytics disabled. `url` must be set because sitemap, SEO, and llms need absolute context.

## Semantic Search

Semantic search uses `docmd-search` at build time. Dependencies are `docmd-search`, `@huggingface/transformers`, and `onnxruntime-node`. The browser receives a static client-side index, not a server or model runtime.

The file `.docmd-search/config.json` must remain committed with the pinned model:

```json
{ "model": "Xenova/all-MiniLM-L6-v2", "chunkSize": 512, "chunkOverlap": 64, "incremental": true, "topK": 10 }
```

`.gitignore` must ignore generated search cache while keeping this config.

## Footer And Branding

Footer content must credit Lorenzo Padovani, Padosoft, GitHub, and Apache-2.0. Brand color is `#0d9488` in `assets/custom.css` through docmd primary/link variables.

## Cloudflare Pages

Primary deploy path is Cloudflare Pages Git integration, no API key in the repo:

| Field | Value |
| --- | --- |
| Production branch | `main` |
| Root directory | `docs-site` |
| Build command | `npm run build` |
| Build output directory | `_site` |
| Node | `.node-version` pinned to `20` |

Fallback only if Cloudflare build breaks on native ONNX dependencies: a manual `workflow_dispatch` GitHub Actions workflow may run `wrangler pages deploy _site --project-name=laravel-evidence-risk-review` with Cloudflare secrets.

## Page Standard

Deep pages should include motivation, theory with formulas when useful, Mermaid design diagram, data model or contract, ADR collapsibles, a worked example, and gotchas in a warning callout.

## Gotchas

- `docs/index.md` is mandatory or `_site/index.html` is missing.
- Do not use paired `::: button`; use a Markdown link inside cards.
- Steps need careful indentation: body lines three spaces under the numbered item.
- KaTeX must not render `$variables` inside code fences; inspect output when adding shell-heavy pages.
- Keep the lockfile cross-platform and do not commit generated `_site/`, `node_modules/`, or search cache batches.
