# Haiku Fixer

The Haiku Fixer normalizes, sorts, combines, and cleans adblock rules. It is invoked through the `fix` command and modifies files in place unless configured otherwise.


## Usage

```sh
vendor/bin/haiku fix [options]
```

### Options

- `--path <path>` Path to the filter file or directory to process.
- `--force` Ignore cache and process all files regardless of whether they have changed.
- `--config <path>` Custom configuration file path.
- `--parallel` Run in parallel.
- `--help` Show help message.
- `--verbose` Enable verbose logging.
- `--silent` Suppress all output.

#### Examples

```sh
# Process all files in the current directory
vendor/bin/haiku fix

# Process a specific file with a custom config
vendor/bin/haiku fix --path filter-list.txt --config haiku.yml

# Reprocess all files
vendor/bin/haiku fix --force
```

## Configuration

#### `paths`

A list of files or directories to process. Paths are relative to the project root directory. If not specified, defaults to the project root.

#### `excludes`

A list of files or directories to be excluded during processing. If `excludes` contains root paths, Haiku automatically excludes the `vendor` directory.

Paths under `excludes` are relative to `fixer.paths`. Assuming `src` is defined in `fixer.paths`:

- `Config` will skip the `src/Config` folder.
- `Folder/with/File.txt` will skip `src/Folder/with/File.txt`.

#### `backup`

Creates a backup of each file before applying fixes. Default is `false`.

#### `flags`

Flags control how the fixer processes and transforms rules during the fixing pipeline. Each flag either toggles a specific behavior or adjusts how a particular transformation is performed.

Some flags are simple boolean switches, while others accept configuration values that determine the exact processing mode (e.g. [`option_format`](#filter-option-format)).

- **`fmode`**: Bulk toggle for all boolean flags. Default is `false`.
- See the [Transformations](#transformations) section below for a complete list of available flags.

```yml
# cache_dir: .tmp

fixer:
  paths:
    - src
  excludes:
    - vendor
  flags:
    remove_empty_lines: false
```


## Transformations

See [fixer_transformations.md](./fixer_transformations.md)
