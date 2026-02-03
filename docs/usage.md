# Usage

This document describes how to use Haiku from the command line, including available commands, options, and examples.

## Commands

Haiku provides two main commands:

- `fix` — Sorting, combining, and optimizing adblock filter lists.
- `build` — Compiles multiple filter list sources (local files and remote URLs) into unified output files

Both commands use the same configuration system unless overridden by CLI options.


<br>


## `fix` Command

```sh
vendor/bin/haiku fix [options]
```

### Options

- `--path <path>`
  Path to the filter file or directory to process.
- `--force`
  Ignore cache and process all files regardless of whether they have changed.
- `--backup`
  Create backup files before modifying.
- `--keep-empty-lines`
  Keep empty lines in output.
- `--config <path>`
  Custom configuration file path.
- `--x`
  Enable experimental features.
- `--help`
  Show help message
- `--verbose`
  Enable verbose logging.
- `--silent`
  Suppress all output.

```sh
# Process all files in the current directory
vendor/bin/haiku fix

# Process a specific file with a custom config
vendor/bin/haiku fix --path filter-list.txt --config haiku.yml

# Reprocess all files
vendor/bin/haiku fix --force
```


<br>


## `build` Command

```sh
vendor/bin/haiku build [options]
```

### Options

- `--force`
  Ignore cache and rebuild all sources regardless of whether they have changed.
- `--config <path>`
  Custom configuration file path.
- `--help`
  Show help message
- `--verbose`
  Enable verbose logging.
- `--silent`
  Suppress all output.

```sh
# Build using default configuration
vendor/bin/haiku build

# Build with a custom configuration file
vendor/bin/haiku build --config haiku.yml

# Rebuild all sources
vendor/bin/haiku build --force
```
