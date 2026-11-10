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

## Release signing

`release-php.yml` attaches `git-artifact`, `SHA256SUMS` and `SHA256SUMS.asc` to each release. It signs `SHA256SUMS` with the `GPG_PRIVATE_KEY` secret, plus `GPG_PASSPHRASE` when the key has one. Both secrets live in the `release` environment. The workflow then checks the signature against `release-key.asc` and the fingerprint pinned in `GPG_FINGERPRINT`, and records a build attestation for `git-artifact` and `SHA256SUMS`.

`release-key.asc` in the repository root is the public half of the `Git Artifact Release Signing` key, fingerprint `REPLACE_WITH_FORMATTED_FINGERPRINT`. It expires on REPLACE_WITH_EXPIRY_DATE.

To extend the key before it expires, run the following commands. The fingerprint stays the same, so only `release-key.asc` changes:

```bash
gpg --quick-set-expire REPLACE_WITH_FINGERPRINT 2y
gpg --armor --output release-key.asc --export REPLACE_WITH_FINGERPRINT
```

A new key needs a new `release-key.asc`, the new fingerprint in `GPG_FINGERPRINT` in `release-php.yml` and in the "Verify the downloaded binary" section of `README.md`, and new secrets in the `release` environment.
