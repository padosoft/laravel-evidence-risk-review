---
title: Cloudflare Pages
description: Deploy the docmd site through Cloudflare Pages Git integration.
---

# Cloudflare Pages

## Motivation

Cloudflare Pages should build from Git on main with docs-site as root, leaving deployment credentials out of the repository.

## Theory

Evidence risk review treats an answer as a set of claims $C = \{c_1, c_2, ..., c_n\}$ and sources $S = \{s_1, s_2, ..., s_m\}$. Each source receives a tier rank $r(s) \in [0,100]$. A claim with assertiveness $a(c)$ has a profile-specific minimum rank $t(a,c)$. A deterministic gap is:

$$
g(c) = \max(0, t(a,c) - \max_{s \in S_c} r(s))
$$

The package then combines evidence gaps, profile checks, and verdict precedence into a stable ReviewResult.

## Design + Diagram

```mermaid
flowchart LR
  A[Artifact] --> B[Evidence tier labeling]
  B --> C[Risk sweep]
  C --> D[Verdict reduction]
  D --> E[ReviewResult]
  C --> F[(Review log)]
  G[Domain profile] --> C
  H[Budget meter] --> C
```

## Data Model / Contract

Production branch: main; root directory: docs-site; build command: npm run build; output: _site; Node pinned by .node-version.

| Field | Meaning |
| --- | --- |
| artifact_id | Host-defined stable identifier. |
| claims | Discrete claims extracted by the host. |
| sources | Cited evidence with optional tier hints. |
| profile_key | Risk profile used for thresholds and checks. |
| findings | Structured review findings emitted by checks. |

## ADR

::: collapsible "Problem: adapters tend to grow business logic"
Decision: keep PHP facade, Artisan, HTTP, and MCP as thin adapters over ReviewEngine.

Consequences: there is one behavior to test, but adapters must validate input carefully.
:::

::: collapsible "Problem: external review can become expensive"
Decision: run cheap deterministic checks first and spend LLM budget only when enabled and useful.

Consequences: default installs have zero token cost, but hosts must bind an LLM contract for semantic review.
:::

## Worked Example

```text
Root directory: docs-site
Build command: npm run build
Build output directory: _site
Node: 20
```

## Gotcha / Limits

::: callout warning
The package reviews evidence strength and risk boundaries; it does not retrieve sources, extract claims automatically, or replace human review for regulated decisions.
:::
