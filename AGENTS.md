# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

Git Artifact is a PHP CLI tool that packages code artifacts from a source repository and pushes them to a destination repository. It's designed for deployment workflows where built artifacts need to be deployed to a separate repository.

The project was created from the [Scaffold](https://getscaffold.dev) template, which provides its CI workflows, linting and test configuration, and Docker setup.

## Commands

### Code Quality

```bash
# Run all linters (PHPCS, PHPStan, Rector dry-run)
composer lint

# Auto-fix code style issues (Rector, PHPCBF)
composer lint-fix
```

### Testing

```bash
# Run all PHPUnit tests (fast, no coverage)
composer test

# Run with coverage reports
composer test-coverage
# Coverage reports: .logs/.coverage-html/index.html, .logs/cobertura.xml, .logs/coverage.txt

# Run specific test class
./vendor/bin/phpunit tests/Unit/Traits/TokenTraitTest.php
./vendor/bin/phpunit tests/Functional/BranchModeTest.php

# Run specific test method
./vendor/bin/phpunit --filter testTokenProcess tests/Unit/Traits/TokenTraitTest.php
```

### Building

```bash
# Build the standalone PHAR into .build/git-artifact using Box
composer build
```

The `.build/` directory is git-ignored; the PHAR ships only as a release asset. The release workflow writes the release version into `box.json`, and Box substitutes it for the `@git-artifact-version@` placeholder in `src/app.php`, so `git-artifact --version` reports it.

## Architecture

### Core Components

- **ArtifactCommand** (`src/Commands/ArtifactCommand.php`) - Main CLI command that orchestrates the artifact creation process
- **ArtifactGitRepository** (`src/Git/ArtifactGitRepository.php`) - Extended Git repository class with artifact-specific operations
- **Traits** (`src/Traits/`) - Reusable functionality:
  - `TokenTrait` - Handle token replacement in branch names and commit messages
  - `LoggerTrait` - Logging functionality
  - `FilesystemTrait` - File system operations

### Application Entry Points

- `git-artifact` - Main executable script with autoloader discovery
- `src/app.php` - Application bootstrap using Symfony Console

### Deployment Modes

The tool supports 2 deployment modes:

1. **force-push** (default) - Pushes to same branch, overwrites destination history
2. **branch** - Creates new branches in destination based on source tags

### Token System

Supports dynamic token replacement in branch names and commit messages:

- `[timestamp:FORMAT]` - Current timestamp with PHP date format (defaults to `Y-m-d_H-i-s`)
- `[branch]` - Current branch name
- `[safebranch]` - Branch name with non-alphanumeric chars replaced
- `[tags:DELIMITER]` - Tags from latest commit (delimiter defaults to `-`)

## Testing Structure

### Functional Tests (`tests/Functional/`)

Integration tests that verify end-to-end functionality:

- `BranchModeTest.php` - Tests branch mode deployment
- `CleanupStaleBranchesTest.php` - Tests stale remote branch cleanup
- `FilePermissionsTest.php` - Tests file permission preservation
- `ForcePushModeTest.php` - Tests force-push mode deployment
- `GeneralTest.php` - General functionality tests
- `MissingBranchTest.php` - Tests packaging when the source branch cannot be determined
- `TagTest.php` - Token replacement and tagging tests

### Unit Tests (`tests/Unit/`)

- Component-level tests for individual classes
- Follow PHPUnit conventions with strict typing
- Mirror the `src/` layout: `tests/Unit/Git/ArtifactGitRepositoryTest.php` tests `src/Git/ArtifactGitRepository.php`

### Writing Tests

Tests use PHPUnit 12 attributes:

- Coverage attributes: `#[CoversClass(ClassName::class)]`
- Data providers: `#[DataProvider('dataProviderMethodName')]`

### Test Fixtures Naming Convention

- `f*` - Files with counter suffix
- `i` - Ignored files
- `c` - Committed files
- `u` - Uncommitted files
- `d` - Deleted files
- `d_` - Directories
- `sub_` - Sub-directories/files for wildcard testing
- `f*_l` - Symlinks

## Code Standards

- **PHP_CodeSniffer** (`phpcs.xml`) - Drupal and DrevOps coding standards plus the strict types requirement. Test files relax the long-array and missing-comment rules.
- **PHPStan** (`phpstan.neon`) - Level 9 static analysis
- **Rector** (`rector.php`) - PHP 8.3 sets plus the dead code, code quality, coding style, type declaration, early return and PHPUnit sets
- PSR-4 autoloading: `DrevOps\GitArtifact\` namespace maps to `src/`
- All PHP files declare `strict_types=1`
- Console output, log entries and exception messages are sentences that end with a period, and every interpolated value is wrapped in double quotes, each item of a list included: `Deleted stale branch "%s".` Label-value lines such as `Commit: <hash>` and the info and report table rows are data, so they take neither.

## Key Dependencies

- `symfony/console` - CLI framework
- `czproject/git-php` - Git operations
- `symfony/filesystem` & `symfony/finder` - File operations
- `monolog/monolog` - Logging

## CI/CD

GitHub Actions workflows:

- `.github/workflows/test-php.yml` - Lint, test and coverage on PHP 8.3, 8.4 and 8.5 with normal and lowest dependencies, the PHAR build and the `deploy-force-push` and `deploy-branch` demonstration deployments to `drevops/git-artifact-destination`
- `.github/workflows/release-php.yml` - Builds the PHAR and attaches it to the release on a tag, or on a manual run with a version
- `.github/workflows/update-dependencies.yml` - Runs Renovate on demand; needs the `RENOVATE_TOKEN` secret

Lint, the coverage threshold (`CI_CODE_COVERAGE_THRESHOLD` repository variable, default 80%), the coverage PR comment and the Codecov upload run on the PHP 8.4 normal-dependencies leg only. CircleCI (`.circleci/config.yml`) runs the same deployment demonstrations.

## Docker

```bash
# Build the Docker image
docker build -t drevops/git-artifact .

# Run the container
docker run --rm drevops/git-artifact --help

# Lint Dockerfile with hadolint
docker run --rm -i hadolint/hadolint < Dockerfile
```

- `.github/workflows/test-docker.yml` - Lints and builds the image, packages and pushes a test artifact from inside the container, and pushes a `canary`-tagged image on merge to `main`
- `.github/workflows/release-docker.yml` - Builds and pushes the multi-arch image to Docker Hub on tag

Docker Hub credentials are stored as repository secrets:

- `DOCKER_USER` - Docker Hub username
- `DOCKER_PASS` - Docker Hub access token

Override the published image name (release and canary) by setting the `DOCKER_IMAGE` repository or organization variable; it defaults to `drevops/git-artifact`.

## Updating from the template

This project was generated from a template and can pull the template's latest infrastructure (CI workflows, linting and test configuration, Docker, and docs) at any time. When you are asked to "update scaffold", fetch the updater skill from the template repository into the local `.claude/skills/` directory, then invoke it:

1. Create the target directory if it does not exist:

   ```bash
   mkdir -p .claude/skills/update-consumer-scaffold
   ```

2. Download the skill:

   ```bash
   curl -sSL https://raw.githubusercontent.com/AlexSkrypnyk/scaffold/main/.scaffold/skills/update-consumer-scaffold/SKILL.md -o .claude/skills/update-consumer-scaffold/SKILL.md
   ```

3. Invoke the `update-consumer-scaffold` skill and follow its steps.

The skill directory is fetched on demand and is git-ignored - it is not committed to the project.

This project departs from the template layout. Keep these project paths and settings when updating:

- The root `git-artifact` entry point, in place of `bin/`
- Tests in `tests/Unit` and `tests/Functional`, in place of `tests/phpunit`
- The `DrevOps\GitArtifact\` namespace
- The committed `composer.lock` and `vendor-bin/box/composer.lock`
- The `.build/git-artifact` PHAR path
- The project `Dockerfile` and `.circleci/`
- The `deploy-*` jobs and the required check names in `test-php.yml`

## Configure the GitHub repository

When you are asked to configure the project's GitHub repository (settings, labels or branch protection), run the following command in the project root:

```bash
curl -fsSL https://getscaffold.dev/install.sh | bash -s -- github
```

It needs GitHub CLI (`gh`) and a token in `SCAFFOLD_GITHUB_TOKEN`; it never uses the logged-in `gh` account. It leaves the project files untouched. Add `--dry-run` to print the changes without making them, and run with `--help` for the section options.
