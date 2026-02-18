# Configuration Guide

This document provides comprehensive documentation for configuring Haiku through the configuration file. It explains the file structure, all available configuration options, and their defaults.


## Configuration File Overview

Haiku is configured through a single YAML file named `haiku.yml` located in your project's root directory. This file controls both the `build` and `fix` commands through separate configuration sections.

### Creating the Configuration File
Use the `init` command to generate a template configuration file:

```sh
vendor/bin/haiku init
```

This creates a `haiku.yml` file with commented examples of all available options.

### Configuration Resolution Priority
Configuration values are resolved in the following order (highest priority first):

1. **CLI options** - Command-line flags like `--force`.
2. **haiku.yml values** - Settings in your configuration file.
3. **Default values** - Built-in defaults defined by Haiku

CLI options always override configuration file values for the current command execution only.


## Global Configuration

Global configuration options apply to all commands and are defined at the root level of configuration file.

#### `cache_dir`
Specifies the directory where Haiku stores cache data for both the `build` and `fix` commands.

```yml
cache_dir: .tmp
```

**Behavior**
- If not set, Haiku uses an internal default cache directory
- Cache is shared across commands
- Deleting this directory forces a full reprocess


## Fixer Configuration

This section configures the behavior for the `fix` command.

```yml
fixer:
  paths:
    - src
  excludes:
    - vendor
  flags:
    remove_empty_lines: false
```

#### `paths`
A list of files or directories to process. Paths are relative to the project root directory. If not specified, defaults to the project root.

#### `excludes`
A list of files or directories to be excluded during processing. If `excludes` contains root paths, Haiku automatically excludes `vendor` directory.

Paths under `excludes` are relative to the `fixer.paths`. Here are some examples of `excludes`, assuming that `src` is defined in `fixer.paths`:
- `Config` will skip the `src/Config` folder.
- `Folder/with/File.txt` will skip `src/Folder/with/File.txt`.

#### `backup`
Creates a backup of each file before applying fixes. Default is `false`.

#### `flags`
A list of flags to control processing behavior.

- **`remove_empty_lines`**: Removes empty lines. Default is `true`.
- **`fmode`**: Enable all features. Default is `false`.
- **`xmode`**: Enables experimental features. Default is `false`.


## Builder Configuration

This section configures the behavior for the `build` command.

```yml
builder:
  output_dir: dist
  filter_list:
    - filename: example.txt
      header: |
        [Adblock Plus 2.0]
        ! Title: Example List
        ! Last modified: %timestamp%
      source:
        - local.txt
        - https://example.org/list.txt
      remove_duplicates: true
```

#### `output_dir`
The directory where compiled filter lists are written. The directory is created automatically if it doesn't exist.

#### `filter_list`
An array defining one or more filter lists to build. Each item in the array configures a single output filter list. At least one filter list must be defined.

- **`filename`** (*Required*): The output filename for the compiled filter list.
- **`header`**: A multi-line string prepended to the output file. Supports placeholder substitution:
  - `%timestamp%`: Replaced with current date/time in RFC 7231 format.
- **`source`** (*Required*): A list of source files (local or URL) to build the filter list from.
- **`remove_duplicates`**: Controls whether duplicate lines are removed after sources are merged.
  - Possible values: `true` or `false`
  - Default: `false`

```yml
# A minimal configuration requires for the build command:

builder:
  filter_list:
    - filename: output.txt
      source:
        - input.txt
```


## Real-World Example

For a production configuration example, see
[AdBlockID-src/haiku.yml](https://github.com/realodix/AdBlockID-src/blob/ca03961fc3/haiku.yml)
