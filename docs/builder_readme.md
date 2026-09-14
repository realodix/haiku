# Haiku Filterlist Builder

A tool for compiling a list of filters from multiple sources (local files or URLs) into a consolidated output file.

#### What the tool offers:
- Aggregates content from local files and remote URLs.
- Applies headers with timestamp placeholders.
- Remove comment lines.
- Remove duplicate lines when configured.


## Usage

The entry point is `haiku build`. It runs the builder against the source files declared in configuration file (or against arguments you pass on the command line).

```sh
vendor/bin/haiku build [options]
```

### Options

- `--force`

  Ignore cache and rebuild all sources regardless of whether they have changed.

- `--config <path>`

  Custom configuration file path.

#### Examples

```sh
# Build using default configuration
vendor/bin/haiku build

# Build with a custom configuration file
vendor/bin/haiku build --config haiku.yml

# Rebuild all sources
vendor/bin/haiku build --force
```


## Configuration

#### `output_dir`

The directory where output files are written. The directory is created automatically if it doesn't exist.

#### `filter_lists`

An array defining one or more filter lists to build. Each item in the array configures a single output filter list. At least one filter list must be defined.

- **`filename`** (*Required*): The output filename for the compiled filter list.
- **`header`**: A multi-line string prepended to the output file. Supports placeholder substitution:
  - `%timestamp%`: Replaced with current date/time in RFC 7231 format.
- **`includes`** (*Required*): A list of included files (local or URL) that will be used to build the filter list.
- **`remove_duplicates`**: Controls whether duplicate lines are removed after sources are merged.
  - Possible values: `true` or `false`
  - Default: `false`


```yml
# cache_dir: .tmp

builder:
  filter_lists:
    - filename: filter_1.txt
      header: |
        [Adblock Plus 2.0]
        ! Title: Example List
        ! Last modified: %timestamp%
      includes:
        - local.txt
        - https://example.com/list.txt
    - filename: filter_2.txt
      includes:
        - local_2.txt
      remove_duplicates: true
```

For a production configuration example, see [AdBlockID-src/haiku.yml](https://github.com/realodix/AdBlockID-src/blob/main/haiku.yml).
