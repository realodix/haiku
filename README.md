[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/realodix/haiku)

# Realodix Haiku

Haiku is a powerful command-line tool for managing adblock filter lists efficiently. It automates repetitive tasks such as merging sources, optimizing, and tidying up filter lists effortlessly.


## Features

- **Building**: Compiles multiple filter list sources (local files and/or remote URLs) into single unified output files, including regenerating headers metadata and removing unnecessary lines such as comments.
- **Fixing**: Normalize, sort, and combine adblock rules to produce cleaner and more maintainable filter lists. Supports multiple adblock syntaxes (Adblock Plus, AdGuard, uBlock Origin, and more).
- **Unified Caching System**: Automatically skips unchanged inputs for significantly faster subsequent runs.
- **Configuration via YAML**: Control both building and fixing behavior through a single `haiku.yml` file.

The following example shows Haiku that normalizes the order and combines compatible rules.

```adblock
!## BEFORE
[$path=/page.html,domain=b.com|a.com]##.textad
example.com##+js(aopw, Fingerprint2)
-banner-$image,domain=example.org
-banner-$image,domain=example.com
b.com,a.com##.ads

!## AFTER
-banner-$image,domain=example.com|example.org
a.com,b.com##.ads
[$domain=a.com|b.com,path=/page.html]##.textad
example.com##+js(aopw, Fingerprint2)
```

For a complete list of transformations, see [docs/fixer-feature.md](./docs/fixer-feature.md).

## Installation

Install the package via [Composer](https://getcomposer.org/):

```sh
composer require realodix/haiku
```

Composer will install Haiku executable in its `bin-dir` which defaults to `vendor/bin`.


## Quick Start

### Initialize configuration

   ```sh
   vendor/bin/haiku init
   ```
   Creates a `haiku.yml` configuration file in your project.

### Main Workflow

- **Build filter lists**
    ```sh
    vendor/bin/haiku build
    ```

- **Fix and optimize filter lists**
    ```sh
    vendor/bin/haiku fix
    ```

For detailed command usage, available options, and more examples, see [docs/usage.md](./docs/usage.md).



## Configuration

See [configuration file](./docs/configuration.md) documentation or [AdBlockID-src/haiku.yml](https://github.com/realodix/AdBlockID-src/blob/ca03961fc3/haiku.yml) for a production configuration example.


## Contributing

Contributions are welcome! Please:

1. Fork the repo and create a feature branch.
2. Add tests for new features.
3. Ensure code passes `composer check`.
4. Submit a PR with a clear description.

Report bugs or suggest features via Issues.


## License

This project is licensed under the [MIT License](./LICENSE).
