# Usage

This document describes how to use Haiku from the command line, including available commands, options, and examples.

## Commands

Haiku provides two main commands:

- `build` — Compile multiple filter list sources into unified output files.
- `fix` — Normalize and optimize existing filter list files or directories.

Both commands use the same configuration system unless overridden by CLI options.

---

## build

Compile filter list sources into unified output files as defined in `haiku.yml`.

```sh
vendor/bin/haiku build [options]
```

### Options

- `--force`
  Ignore cache and rebuild all sources.
- `--config <path>`
  Use a custom configuration file path.

### Examples

```sh
# Build using default configuration
vendor/bin/haiku build

# Build with a custom configuration file
vendor/bin/haiku build --config haiku.yml

# Rebuild all sources and ignore cache
vendor/bin/haiku build --force
```

---

## fix

Normalize, sort, and combine adblock rules in existing filter list files or directories.

```sh
vendor/bin/haiku fix [options]
```

### Options

- `--path <path>`
  Path to the filter file or directory to process.
- `--force`
  Ignore cache and process all files.
- `--config <path>`
  Use a custom configuration file path.
- `--cache <path>`
  Specify a custom cache directory.

### Examples

```sh
# Process all files in the current directory
vendor/bin/haiku fix

# Process a specific file with a custom config
vendor/bin/haiku fix --path filter-list.txt --config haiku.yml

# Use a custom cache directory
vendor/bin/haiku fix --cache ./customcachedir
```
