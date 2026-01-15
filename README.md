[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/realodix/haiku)

# Realodix Haiku

Haiku is a powerful command-line tool for managing ad-blocker filter lists. It automates repetitive tasks such as merging sources, optimizing, and tidying up filter lists effortlessly. With a built-in caching system, Haiku skips unchanged files since the last run, resulting in significantly faster processing.


## Features

- **Fully compatible with multiple filter syntaxes.** Supports Adblock Plus, AdGuard, uBlock Origin, and more.
- **Filter List Building:** Builds a unified output from multiple local or remote filter lists into a single file, regenerating metadata and removing unnecessary lines such as comments.
- **Rule Sorting:** Sorts filter rules alphabetically for consistent and readable output.
- **Domain Combining:** Combines filter rules with identical patterns but different domain (e.g., `example.com##.ads` + `example.org##.ads` → `example.com,example.org##.ads`).
- **Option Combining:** Combines filter rules with identical patterns but different options (e.g., `/ads.$image` + `/ads.$css` → `/ads.$image,css`).
- **Option Sorting:** Sorts filter options into a standardized and predictable order.
- **Caching:** Caches processed files and reprocesses only changed inputs to speed up subsequent runs.
- **Configuration:** Allows easy configuration of builder and fixer behavior via a simple `haiku.yml` file.

A few examples of transformations applied during optimization:

```adblock
!## BEFORE
[$path=/page.html,domain=b.com|a.com]##.ads1
example.com##+js(aopw, Fingerprint2)
##.ads4
example.org##.ads5
-banner-$image,domain=example.com
*$image,css,script,doc
-banner-$image,domain=example.org
b.com,a.com##.ads3
##.ads2
example.com##.ads5
! merge filter options
/ads.$image
/ads.$css,script
/ads.$css
! typo
/ads.$domain=example.com/
example.com,##.ads

!## AFTER
*$css,doc,image,script
-banner-$image,domain=example.com|example.org
##.ads2
a.com,b.com##.ads3
##.ads4
example.com,example.org##.ads5
[$domain=a.com|b.com,path=/page.html]##.ads1
example.com##+js(aopw, Fingerprint2)
! merge filter options
/ads.$image,css,script
! typo
/ads.$domain=example.com
example.com##.ads
```

For more details, see [docs/fixer-feature.md](./docs/fixer-feature.md).

## Installation

Install the package via [Composer](https://getcomposer.org/):

```sh
composer require realodix/haiku
```

Composer will install Haiku executable in its `bin-dir` which defaults to `vendor/bin`.


## Quick Start

1. **Initialize the configuration file for Haiku:**
    ```sh
    vendor/bin/haiku init
    ```

    Run this command to create a `haiku.yml` configuration file.

2. **Build Filters:**

    ```sh
    vendor/bin/haiku build
    ```

    This builds unified outputs from filter sources as defined in your config.

3. **Fix & Optimize:**

    ```sh
    vendor/bin/haiku fix
    ```

    This tidies and optimizes the specified filter file or directory.


## Usage

Haiku provides two main commands: `build` for merging sources and `fix` for optimization.

### Building Filter Lists
Builds multiple source files into a single output file as defined in your `haiku.yml`, including metadata regeneration and stripping unnecessary lines such as comments.

```sh
Usage:
    vendor/bin/haiku build [options]

Options:
    --force     Ignore cache and rebuild all sources.
    --config    Use a custom configuration file path.

Examples:
    # Build all sources with custom config file.
    vendor/bin/haiku build --config haiku.yml
    # Rebuild all sources with custom config file and ignore cache.
    vendor/bin/haiku build --force --config haiku.yml
```


### Fixing Filter Lists
Optimizes existing filter files or directories by cleaning syntax, sorting rules, and combining compatible patterns.

```sh
Usage:
    vendor/bin/haiku fix [options]

Options:
    --path      Path to the filter file or directory to process.
    --force     Ignore cache and process all files.
    --config    Use a custom configuration file path.
    --cache     Specify a custom cache path.

Examples:
    # Process all files in the root directory.
    vendor/bin/haiku fix
    # Process a specific file with custom config file.
    vendor/bin/haiku fix --path filter-list.txt --config haiku.yml
    # Process all files in the root directory with custom chache directory.
    vendor/bin/haiku fix --cache ./customcachedir
```


## Configuration

The configuration file should be a valid YAML file. The following options are available:

```yaml
# cache_dir: .tmp

# Settings for the `fix` command
fixer:
  paths:
    - folder_1/file.txt
    - folder_2
  excludes:
    - excluded_file.txt
    - some/path/to/file.txt
    - path/to/source

# Settings for the `build` command
builder:
  output_dir: dist
  filter_list:
    # First filter list
    - filename: general_blocklist.txt # Required
      remove_duplicates: true
      header: |
        [Adblock Plus 2.0]
        ! Title: Ad Blocklist
        ! Description: Filter list that specifically removes adverts.
        ! Last modified: %timestamp%
        ! Expires: 5 days (update frequency)
        ! Homepage: https://example.org/
        ! License: MIT
        ! --------------------------------------------------
      source: # Required
        - blocklists/general/local-rules.txt
        - https://cdn.example.org/blocklists/general.txt

    # Second filter list
    - filename: custom_privacy.txt
      source:
        - sources/tracking_domains-1.txt
        - sources/tracking_domains-2.txt
```

See [configuration reference](./docs/configuration.md) for more details.

> [!NOTE]
> You can delete any configurations you don't need. Haiku will use the default values ​​instead.


## Contributing

Contributions are welcome! Please:

1. Fork the repo and create a feature branch.
2. Add tests for new features.
3. Ensure code passes `composer check`.
4. Submit a PR with a clear description.

Report bugs or suggest features via Issues.


## License

This project is licensed under the [MIT License](./LICENSE).
