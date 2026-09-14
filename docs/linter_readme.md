# Haiku Lint

The Haiku Lint provides static analysis for adblock filter lists, identifying syntax errors, deprecated options, and redundant rules. Unlike the Fixer system which modifies files, the Linter is designed to report issues without altering the source, though many rules align with Fixer logic to ensure consistency.

## Usage

### `lint` Command

```sh
vendor/bin/haiku lint [options]
```

### Options

- `--path <path>`

  Specifies the file or directory to analyze.

- `--force`

  Ignore cache and analyse all files regardless of whether they have changed.

- `--config <path>`

  Specifies a custom configuration file.

- `--generate-baseline` or `-b`

  Generates the **currently reported list of errors as a "baseline"** and causes it not to be reported on subsequent runs. It allows you to focus only on violations of new and updated adblock filter rules.

  It works best when you want to get rid of a few dozen to a few hundred reported errors that you don’t have time or energy to deal with right now. It’s not the best tool when you have 15,000 errors.

### Examples

```sh
# Analyze all files in the current directory
vendor/bin/haiku lint

# Analyze a specific file with a custom config
vendor/bin/haiku lint --path filter-list.txt --config haiku.yml
```

## Configuration

#### `paths`

A list of files or directories to analyze. Paths are relative to the project root. If not specified, the project root is used by default.

#### `excludes`

A list of files or directories to exclude from analysis. If root-level paths are provided, the `vendor` directory is automatically excluded.

#### `ignoreErrors`

Errors can be ignored by adding a regular expression to the configuration file under the `ignoreErrors` key.

To ignore an error by a regular expression, add a string entry:

```yml
linter:
  ignoreErrors:
    - '#Deprecated filter option: "(empty|object-subrequest)"#'
    - 'Unknown filter option: "documen"'
```

To ignore errors by a regular expression only in a specific file, add an entry with `message` or `messages` and `path` or `paths` keys.

```yml
linter:
  ignoreErrors:
    - messages:
        - 'Error message 1'
        - 'Error message 2'
    - paths:
        - SomeFile.txt
        - SomeOtherFile.txt
    - path: File1.txt
      messages:
        - 'Error message 1'
        - 'Error message 2'
    - path: File2.txt
      message: 'Error message 2'
```

#### `rules`

A set of options used to configure the linter.

- ##### `no_extra_blank_lines`

    Disallows excessive consecutive blank lines.

    **Type:** `false` | `int`
    **Default:** `false`

    - `false`: rule is disabled.
    - `int`: maximum number of blank lines allowed **in a row**.

    This option limits the number of blank lines that can appear consecutively (in a single sequence). It does **not** limit the total number of blank lines in a file.

- ##### `no_short_rules`

    Check if the rule length is less than the specified minimum threshold value, i.e. if the rule is too short.

    **Type:** `false` | `int`
    **Default:** `false`

    - `false`: rule is disabled.
    - `int`: minimum rule length.

- ##### `no_unknown_scriptlets`

    Checks for unknown scriptlet names to help catch typos.

    **Type:** `bool` | `array`
    **Default:** `true`

    - `true`: enable the check with default known scriptlets.
    - `false`: disable the check.
    - `array`: enable the check and register custom scriptlets under the `known` key.


```yml
# cache_dir: .tmp

linter:
  paths:
    - src
  excludes:
    - vendor
  rules:
    no_extra_blank_lines: 5
    no_unknown_scriptlets:
      known:
        - my-custom-scriptlet
  ignoreErrors:
    - messages:
      - 'Deprecated filter option: "empty"'
```

For a production configuration example, see [AdBlockID-src/haiku.yml](https://github.com/realodix/AdBlockID-src/blob/main/haiku.yml).
