<p align="right">
<a href="https://deepwiki.com/realodix/haiku"><img src="https://img.shields.io/badge/DeepWiki-realodix%2Fhaiku-blue.svg?logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACwAAAAyCAYAAAAnWDnqAAAAAXNSR0IArs4c6QAAA05JREFUaEPtmUtyEzEQhtWTQyQLHNak2AB7ZnyXZMEjXMGeK/AIi+QuHrMnbChYY7MIh8g01fJoopFb0uhhEqqcbWTp06/uv1saEDv4O3n3dV60RfP947Mm9/SQc0ICFQgzfc4CYZoTPAswgSJCCUJUnAAoRHOAUOcATwbmVLWdGoH//PB8mnKqScAhsD0kYP3j/Yt5LPQe2KvcXmGvRHcDnpxfL2zOYJ1mFwrryWTz0advv1Ut4CJgf5uhDuDj5eUcAUoahrdY/56ebRWeraTjMt/00Sh3UDtjgHtQNHwcRGOC98BJEAEymycmYcWwOprTgcB6VZ5JK5TAJ+fXGLBm3FDAmn6oPPjR4rKCAoJCal2eAiQp2x0vxTPB3ALO2CRkwmDy5WohzBDwSEFKRwPbknEggCPB/imwrycgxX2NzoMCHhPkDwqYMr9tRcP5qNrMZHkVnOjRMWwLCcr8ohBVb1OMjxLwGCvjTikrsBOiA6fNyCrm8V1rP93iVPpwaE+gO0SsWmPiXB+jikdf6SizrT5qKasx5j8ABbHpFTx+vFXp9EnYQmLx02h1QTTrl6eDqxLnGjporxl3NL3agEvXdT0WmEost648sQOYAeJS9Q7bfUVoMGnjo4AZdUMQku50McDcMWcBPvr0SzbTAFDfvJqwLzgxwATnCgnp4wDl6Aa+Ax283gghmj+vj7feE2KBBRMW3FzOpLOADl0Isb5587h/U4gGvkt5v60Z1VLG8BhYjbzRwyQZemwAd6cCR5/XFWLYZRIMpX39AR0tjaGGiGzLVyhse5C9RKC6ai42ppWPKiBagOvaYk8lO7DajerabOZP46Lby5wKjw1HCRx7p9sVMOWGzb/vA1hwiWc6jm3MvQDTogQkiqIhJV0nBQBTU+3okKCFDy9WwferkHjtxib7t3xIUQtHxnIwtx4mpg26/HfwVNVDb4oI9RHmx5WGelRVlrtiw43zboCLaxv46AZeB3IlTkwouebTr1y2NjSpHz68WNFjHvupy3q8TFn3Hos2IAk4Ju5dCo8B3wP7VPr/FGaKiG+T+v+TQqIrOqMTL1VdWV1DdmcbO8KXBz6esmYWYKPwDL5b5FA1a0hwapHiom0r/cKaoqr+27/XcrS5UwSMbQAAAABJRU5ErkJggg==" alt="DeepWiki"></a>
<a href="https://packagist.org/packages/realodix/haiku"><img src="https://img.shields.io/packagist/dt/realodix/haiku"></a>
</p>

# Realodix Haiku

Haiku is a powerful command-line tool for managing adblock filter lists efficiently. It automates repetitive tasks such as merging sources, optimizing, and tidying up filter lists effortlessly.

**Built with a deep understanding of the syntaxes of Adblock Plus, AdGuard, and uBlock Origin.**

### # Three capabilities, one tool

#### 🔍 01/Linter
Analyze your filter lists to perform intelligent redundancy analysis, while catching syntax errors and invalid options or modifiers before they affect users.

#### 📝 02/Formatter
A deterministic formatter that sorts, combines, and deduplicates rules for a leaner, faster-loading filter list. No manual formatting roulette; drop in, automate it, and move on.

#### 📦 03/Bundler
Combines multiple filter list files (local and/or remote URLs) into a single file. Automatically regenerates header metadata, strips unnecessary lines such as comments, and delivers a production-ready deployment.


### # Three steps to first run

1. **Install**. Install the package via [Composer](https://getcomposer.org/)

    ```sh
    composer require realodix/haiku
    ```

2. **Initialize**. Run `vendor/bin/haiku init` in your project root. The command writes a `haiku.yml` configuration file.
2. **Run**. Use `vendor/bin/haiku lint`, `vendor/bin/haiku format`, or `vendor/bin/haiku build`. Wire it into pre-commit, CI, or your editor.

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
