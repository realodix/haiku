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
- `--config <path>`
  Custom configuration file path.
- `--parallel`
  Run in parallel.
- `--help`
  Show help message.
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


## `lint` Command

```sh
vendor/bin/haiku lint [options]
```

### Options

- `--path <path>`
  Specifies the file or directory to analyze.
- `--config <path>`
  Specifies a custom configuration file.
- `--generate-baseline` or `-b`

  Generates the **currently reported list of errors as a “baseline”** and cause it not being reported on subsequent runs. It allows you to focus only on violations of new and updated adblock filter rules.

  It works best when you want to get rid of a few dozen to a few hundred reported errors that you don’t have time or energy to deal with right now. It’s not the best tool when you have 15,000 errors.

```sh
# Analyze all files in the current directory
vendor/bin/haiku lint

# Analyze a specific file with a custom config
vendor/bin/haiku lint --path filter-list.txt --config haiku.yml
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
  Show help message.
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
