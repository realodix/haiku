# Concepts

This document explains the core concepts behind Haiku. Understanding these concepts will help you configure, extend, and use Haiku more effectively.

---

## What is Haiku?

Haiku is a command-line tool designed to **manage, normalize, and optimize adblock filter lists**.

Rather than acting as a simple formatter, Haiku understands the *structure* and *semantics* of adblock rules, allowing it to safely transform, merge, and optimize filters across multiple syntaxes.

At a high level, Haiku focuses on three things:

- **Workflow automation**
- **Rule normalization**
- **Deterministic output**

---

## Filter Lists vs Filter Rules

### Filter List

A *filter list* is a file (or a collection of files) containing adblock rules.

These files may come from:

- Local sources
- Remote URLs
- Generated outputs from other tools

Haiku treats filter lists as **inputs or outputs**, not as atomic units.

### Filter Rule

A *filter rule* is a single logical blocking or cosmetic instruction, such as:

- Network rules
  `/ads.$image,domain=example.com`

- Cosmetic rules
  `example.com##.ads`

Haiku parses and processes rules individually, which enables advanced operations like sorting, combining, and normalization.

---

## Commands and Responsibilities

Haiku separates responsibilities into two main commands:

### build

The `build` command focuses on **aggregation**:

- Merging multiple filter list sources
- Regenerating headers and metadata
- Producing clean, unified output files
- Removing duplicate rules (disabled by default)

It does **not** deeply optimize or rewrite individual rules beyond basic normalization.

### fix

The `fix` command focuses on **rule-level optimization**:

- Normalizing syntax
- Sorting rules deterministically
- Combining compatible rules
- Cleaning malformed or inconsistent patterns

It operates on existing files or directories and is safe to run repeatedly.

---

## Rule Normalization

Normalization ensures that logically equivalent rules are represented in a **single canonical form**.

Examples of normalization include:

- Sorting domains alphabetically
  `b.com,a.com` → `a.com,b.com`

- Sorting options deterministically
  `$image,css,script` → `$css,image,script`

- Fixing minor syntax inconsistencies without changing meaning

Normalization is the foundation that enables reliable sorting and combining.

---

## Rule Combining

Haiku can combine multiple rules **only when it is safe to do so**.

Examples:

- **Domain combining**
  ```
  example.com##.ads
  example.org##.ads
  ```
  becomes:
  ```
  example.com,example.org##.ads
  ```

- **Option combining**
  ```
  /ads.$image
  /ads.$css
  ```
  becomes:
  ```
  /ads.$image,css
  ```

Combining reduces file size while preserving semantics.

---

## Multi-Syntax Awareness

Haiku is aware of multiple adblock syntaxes, including:

- Adblock Plus
- uBlock Origin
- AdGuard and related variants

Instead of forcing all rules into a single syntax, Haiku preserves the **original intent and compatibility** of each rule.

---

## Deterministic Output

One of Haiku’s core design goals is **determinism**.

Given the same inputs and configuration:
- Output files are identical
- Rule order is predictable
- Changes are minimal and meaningful in version control

This makes Haiku especially suitable for:
- Collaborative filter list maintenance
- CI pipelines
- Long-term versioned repositories

---

## Caching Model

Haiku uses a unified caching system shared by both `build` and `fix`.

The cache tracks:
- Input content
- Configuration
- Relevant processing steps

If nothing has changed, Haiku skips processing automatically, resulting in significantly faster runs on large filter lists.

---

## Configuration-Driven Workflow

All behavior in Haiku is controlled via a single configuration file (`haiku.yml`).

This includes:
- Input sources
- Output locations
- Exclusions
- Command-specific behavior

CLI options can override configuration values, but configuration remains the primary source of truth.

---

## Idempotency

Haiku is designed to be **idempotent**.

Running `fix` multiple times on the same files produces the same result after the first successful run.

This guarantees safety when integrating Haiku into automated workflows.

---

## Philosophy

Haiku follows a few guiding principles:

- **Do not guess intent**
- **Optimize only when safe**
- **Prefer explicit rules over heuristics**
- **Readable output is a feature**

These principles guide both the implementation and future evolution of the project.
