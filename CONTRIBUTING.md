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

`release-key.asc` in the repository root is the public half of the `Git Artifact Release Signing` key, fingerprint `1468 C246 213B 8658 33BE  4A60 5F86 D116 C8B2 2A96`. It expires on 2028-11-09.

To extend the key before it expires, run the following commands. The fingerprint stays the same, so only `release-key.asc` changes:

```bash
gpg --quick-set-expire 1468C246213B865833BE4A605F86D116C8B22A96 2y
gpg --armor --output release-key.asc --export 1468C246213B865833BE4A605F86D116C8B22A96
```

To replace the key, update 3 places:

1. Export the new key and the previous one together into `release-key.asc`, so the signatures of earlier releases still verify:

   ```bash
   gpg --armor --output release-key.asc --export NEW_FINGERPRINT 1468C246213B865833BE4A605F86D116C8B22A96
   ```

   `NEW_FINGERPRINT` is the fingerprint of the new key.

2. Set `GPG_FINGERPRINT` in `release-php.yml` to the new fingerprint, and list both fingerprints in the "Verify the downloaded binary" section of `README.md`.
3. Store the new key in the `GPG_PRIVATE_KEY` and `GPG_PASSPHRASE` secrets of the `release` environment.
