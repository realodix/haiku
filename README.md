[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/realodix/haiku)

# Realodix Haiku

Haiku is a powerful command-line tool for managing adblock filter lists efficiently. It automates repetitive tasks such as merging sources, optimizing, and tidying up filter lists effortlessly.

### # Three capabilities, one tool

#### 01/Lint - Static analysis
Analyze your filter lists to catch syntax errors, structural defects, and invalid filter options / modifiers before they impact users. Built with deep understanding of Adblock Plus, AdGuard, and uBlock Origin syntaxes.

#### 02/Fix - Optimizer & Normalizer
An opinionated optimizer that sorts, combines, and deduplicates rules for a leaner, faster-loading filter list. No manual formatting roulette; drop it in, automate it, and move on.

For a complete list of transformations, see [docs/fixer-feature.md](./docs/fixer-feature.md).

#### 03/Build - Compiler & Bundler
Compile multiple filter sources (local files and/or remote URLs) into a single, unified output. Automatically regenerates header metadata, strips unnecessary lines such as comments, and delivers a production-ready deployment.


### # Three steps to first run

1. **Install**. Install the package via [Composer](https://getcomposer.org/) — `composer require realodix/haiku`.
2. **Initialize**. Run `vendor/bin/haiku init` in your project root. Haiku detects your layout and writes a `haiku.yml`.
2. **Run**. Use `vendor/bin/haiku lint`, `vendor/bin/haiku fix`, or `vendor/bin/haiku build`. Wire it into pre-commit, CI, or your editor.

For detailed command usage, available options, and more examples, see [docs/usage.md](./docs/usage.md).


### # Configuration

See [configuration file](./docs/configuration.md) documentation or [AdBlockID-src/haiku.yml](https://github.com/realodix/AdBlockID-src/blob/main/haiku.yml) for a production configuration example.


## Contributing

Contributions are welcome! Please:

1. Fork the repo and create a feature branch.
2. Add tests for new features.
3. Ensure code passes `composer check`.
4. Submit a PR with a clear description.

Report bugs or suggest features via Issues.


## License

This project is licensed under the [MIT License](./LICENSE).
