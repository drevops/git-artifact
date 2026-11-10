# AGENTS.md

This file provides guidance to AI agents when working with code in this repository.

## Project Overview

Git Artifact is a PHP CLI tool that packages code artifacts from a source repository and pushes them to a destination repository. It's designed for deployment workflows where built artifacts need to be deployed to a separate repository.

## Development Commands

### Code Quality and Linting
- `composer lint` - Run code quality checks (PHPCS, PHPStan, Rector dry-run)
- `composer lint-fix` - Fix code quality issues (Rector, PHPCBF)

### Testing  
- `composer test` - Run PHPUnit tests without coverage
- `composer test-coverage` - Run PHPUnit tests with coverage

### Building
- `composer build` - Build standalone PHAR executable using Box

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
The tool supports two deployment modes:
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

- Follows Drupal coding standards via PHPCS
- Uses PHPStan level 9 for static analysis  
- Rector for automated code modernization to PHP 8.2+
- PSR-4 autoloading: `DrevOps\GitArtifact\` namespace maps to `src/`
- Console output, log entries and exception messages are sentences that end with a period, and every interpolated value is wrapped in double quotes, each item of a list included: `Deleted stale branch "%s".` Label-value lines such as `Commit: <hash>` and the info and report table rows are data, so they take neither.

## Key Dependencies

- `symfony/console` - CLI framework
- `czproject/git-php` - Git operations
- `symfony/filesystem` & `symfony/finder` - File operations
- `monolog/monolog` - Logging

## Single Test Execution

Run specific test classes:
```bash
./vendor/bin/phpunit tests/Unit/Traits/TokenTraitTest.php
./vendor/bin/phpunit tests/Functional/BranchModeTest.php
```

Run specific test methods:
```bash
./vendor/bin/phpunit --filter testTokenProcess tests/Unit/Traits/TokenTraitTest.php
```

## Updating from the template

This project was generated from the [Scaffold](https://getscaffold.dev) template and can pull the template's latest infrastructure (CI workflows, linting and test configuration, Docker) at any time. When you are asked to "update scaffold", fetch the updater skill from the template repository into the local `.claude/skills/` directory, then invoke it:

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

The project layout has diverged from the template: the entry point is the root `git-artifact` file with `src/app.php`, tests live in `tests/{Unit,Functional,Traits}`, and the `Dockerfile`, `.circleci/` and the deployment demo jobs in `test-php.yml` are project-owned. Port template changes onto this layout instead of replacing it.

## Configure the GitHub repository

When you are asked to configure the project's GitHub repository (settings, labels or branch protection), run the following command in the project root:

```bash
curl -fsSL https://getscaffold.dev/install.sh | bash -s -- github
```

It needs GitHub CLI (`gh`) and a token in `SCAFFOLD_GITHUB_TOKEN`; it never uses the logged-in `gh` account. It leaves the project files untouched. Add `--dry-run` to print the changes without making them, and run with `--help` for the section options.