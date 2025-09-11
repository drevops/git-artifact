# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
- `[timestamp:FORMAT]` - Current timestamp with PHP date format
- `[branch]` - Current branch name
- `[safebranch]` - Branch name with non-alphanumeric chars replaced
- `[tags:DELIMITER]` - Tags from latest commit

## Testing Structure

### Functional Tests (`tests/Functional/`)
Integration tests that verify end-to-end functionality:
- `BranchModeTest.php` - Tests branch mode deployment
- `ForcePushModeTest.php` - Tests force-push mode deployment  
- `GeneralTest.php` - General functionality tests
- `TagTest.php` - Token replacement and tagging tests

### Unit Tests (`tests/Unit/`)
- Component-level tests for individual classes
- Follow PHPUnit conventions with strict typing

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

## Key Dependencies

- `symfony/console` - CLI framework
- `czproject/git-php` - Git operations
- `symfony/filesystem` & `symfony/finder` - File operations
- `monolog/monolog` - Logging

## Single Test Execution

Run specific test classes:
```bash
./vendor/bin/phpunit tests/Unit/TokenTest.php
./vendor/bin/phpunit tests/Functional/BranchModeTest.php
```

Run specific test methods:
```bash
./vendor/bin/phpunit --filter testTokenReplacement tests/Unit/TokenTest.php
```