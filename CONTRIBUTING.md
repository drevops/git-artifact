# Contributing

Thank you for considering a contribution to this project. Follow this guide to install the dependencies and run the same linters and tests that CI runs on your pull request. Run the section for each stack your change touches, from the repository root.

## PHP

The functional tests commit to real git repositories, so set `user.name` and `user.email` in your git config first.

Install the dependencies, then run the linters and the tests:

```bash
composer install
composer lint
composer test
```

To fix the PHP_CodeSniffer and Rector issues that the linters report, run `composer lint-fix`. To write a coverage report to `.logs/`, enable the PCOV or Xdebug extension and run `composer test-coverage`. Xdebug also needs `XDEBUG_MODE=coverage`.

To build the standalone PHAR into `.build/git-artifact`, run `composer build`.

## Docker

Lint the `Dockerfile`, build the image and run a container from it:

```bash
docker run --rm -i hadolint/hadolint < Dockerfile
docker build -t drevops/git-artifact .
docker run --rm drevops/git-artifact --help
```

The container prints the `git-artifact` help.
