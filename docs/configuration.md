# Configuration

Haiku is configured using a single YAML file named `haiku.yml`. This document serves as a complete reference for all supported configuration options, their semantics, defaults, and interactions.

## Configuration Resolution

Configuration is resolved in the following order:

1. Default values defined by Haiku.
2. Values from `haiku.yml`.
3. CLI options (highest priority).

CLI options always override configuration file values, but only for the scope of the current command execution.

## General

#### `cache_dir`
Specifies the directory where Haiku stores cache data for both the `build` and `fix` commands.

```yml
cache_dir: .tmp
```

**Behavior**
- If not set, Haiku uses an internal default cache directory
- Cache is shared across commands
- Deleting this directory forces a full reprocess


## Fixer
This section configures the behavior for the `fix` command.

```yml
fixer:
  paths:
    - src
  excludes:
    - vendor
```

##### `paths`
A list of files or directories to be processed. If `fixer.paths` is not set, it defaults to the project's root directory.

##### `excludes`
A list of files or directories to be excluded during processing. If `excludes` contains root paths, Haiku automatically excludes `vendor` directory.

Paths under `excludes` are relative to the `fixer.paths`. Here are some examples of `excludes`, assuming that `src` is defined in `fixer.paths`:
- `Config` will skip the `src/Config` folder.
- `Folder/with/File.txt` will skip `src/Folder/with/File.txt`.


## Builder
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

##### `output_dir`
The directory where generated filter lists are written. Directory is created if it does not exist.

##### `filter_list`
An array that defines one or more filter lists to be built. Each item in the array is an object that configures a single filter list.

- **`filename`** (Required): The output filename for the filter list.
- **`header`**: A multi-line string prepended to the output file.
  - `%timestamp%`: replaced with the current date and time formatted according to RFC 7231.
- **`source`** (Required): A list of source files (local or URL) to build the filter list from.
- **`remove_duplicates`**: Controls whether duplicate lines are removed after sources are merged.
  - **Possible values:** `true` or `false`
  - **Default:** `false`
