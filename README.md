[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/realodix/haiku)

# Realodix Haiku

Haiku is a powerful command-line tool for managing adblock filter lists efficiently. It automates repetitive tasks such as merging sources, optimizing, and tidying up filter lists effortlessly.

### # Features
1. **Fixing**: Sorts, combines, normalizes, and optimizes filter rules to produce cleaner and easier-to-maintain filter list.
2. **Building**: Compiles multiple filter list sources (local files and/or remote URLs) into single unified output files, including regenerating headers metadata and removing unnecessary lines such as comments.

Haiku supports multiple adblock syntaxes including Adblock Plus, AdGuard, and uBlock Origin. It uses an incremental caching system to skip unchanged files, enabling efficient processing of large filter lists.

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


## Commands

#### Initialize configuration

Creates a `haiku.yml` configuration file in your project.

```sh
vendor/bin/haiku init
```

#### Fixer

```sh
vendor/bin/haiku fix
```

#### Builder

```sh
vendor/bin/haiku build
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
