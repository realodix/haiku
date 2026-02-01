# Usage

This document describes how to use Haiku from the command line, including available commands, options, and examples.

## Commands

Haiku provides two main commands:

- `build` — Compiles multiple filter list sources (local files and remote URLs) into unified output files
- `fix` — Sorting, combining, and optimizing adblock filter lists.

Both commands use the same configuration system unless overridden by CLI options.


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
- `--verbose`
  Enable verbose logging.
- `--quiet`
  Suppress all output.

```sh
# Build using default configuration
vendor/bin/haiku build

# Build with a custom configuration file
vendor/bin/haiku build --config haiku.yml

# Rebuild all sources and ignore the cache
vendor/bin/haiku build --force
```


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
- `--config <path>`
  Custom configuration file path.
- `--x`
  Enable experimental features.
- `--verbose`
  Enable verbose logging.
- `--quiet`
  Suppress all output.

```sh
# Process all files in the current directory
vendor/bin/haiku fix

# Process a specific file with a custom config
vendor/bin/haiku fix --path filter-list.txt --config haiku.yml

# Use a custom cache directory
vendor/bin/haiku fix --cache ./customcachedir
```
