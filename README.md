<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Git+Artifact&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Git Artifact logo"></a>
</p>

<h2 align="center">Package and push files to a remote repository</h2>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/pulls)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/git-artifact)
[![codecov](https://codecov.io/gh/drevops/git-artifact/branch/main/graph/badge.svg?token=QNBXCIBK5J)](https://codecov.io/gh/drevops/git-artifact)
[![Total Downloads](https://poser.pugx.org/drevops/behat-screenshot/downloads)](https://packagist.org/packages/drevops/git-artifact)
![LICENSE](https://img.shields.io/github/license/drevops/git-artifact)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Test PHP](https://github.com/drevops/git-artifact/actions/workflows/test-php.yml/badge.svg)](https://github.com/drevops/git-artifact/actions/workflows/test-php.yml)
[![CircleCI](https://circleci.com/gh/drevops/git-artifact.svg?style=shield)](https://circleci.com/gh/drevops/git-artifact)

</div>

---

## What is it?

A tool to assemble a code artifact from your codebase, remove unnecessary files,
and push it into a separate Git repository.

## Why?

In hosting environments like Acquia, there are restrictions on the languages or
frameworks available for building applications—for instance, the inability to
run `composer install` due to a read-only filesystem. Consequently, a website
source code has to be developed in a separate (source) repository, then
assembled into a code artifact either locally or via CI, and then transferred
to the hosting provider's version control system (destination repository).

This tool facilitates such processes seamlessly: it uses a
`.gitignore.deployment` file to determine which files should be transferred to
the destination repository, ensuring only necessary files are included and
leaving out those specified by the ignore file.

Furthermore, since the destination repository requires a commit to incorporate
changes from the artifact (like CSS, JS, etc.), the tool offers two options for
this commit: `force-push` or `branch`, accommodating different workflow
preferences.

See example of deployed artifact
in [Artifact branches](https://github.com/drevops/git-artifact-destination/branches).

## Modes

### `force-push` mode (default)

Push the packaged artifact to the same branch in the destination repository,
carrying over the history from the source repository while overwriting the
existing history in the destination repository.

![diagram of force-push mode](https://user-images.githubusercontent.com/378794/33816665-a7b0e4a8-de8e-11e7-88f2-80baefb3d73f.png)

#### Use case

Forwarding all changes from the source repository to the destination
repository as-is for every branch: for example, a commit in the source
repository branch `feature/123` would create a commit in the destination
repository branch `feature/123`. The next commit to the source repository
branch `feature/123` would update the destination repository branch
`feature/123` with the changes, but would overwrite the last "deployment"
commit.

### `branch` mode

Push packaged artifact to the new branch on each deployment, preserving history
from the source repository, but requiring to trigger a deployment of newly
created branch after each deployment.

![diagram of branch mode](https://user-images.githubusercontent.com/378794/33816666-a87b3910-de8e-11e7-82cd-51e007ece063.png)

#### Use case

Creating a new branch in the destination repository for every tag
created in the source repository: for example, a tag `1.2.3` in the source
repository would create a branch `deployment/1.2.3` in the destination
repository. The addition of the new tags would create new unique branches in the
destination repository.

## Installation

```shell
composer require --dev drevops/git-artifact
```

or download the latest release from the [GitHub releases page](https://github.com/drevops/git-artifact/releases/latest).

## Usage

```shell
./git-artifact git@github.com:yourorg/your-repo-destination.git
```

This will create an artifact from current directory and will send it to the
specified remote repository into the same branch as a current one.

### Run in CI

See examples:

- [GitHub Actions](.github/workflows/test-php.yml)
- [CircleCI](.circleci/config.yml)

Call from CI configuration or deployment script:

```shell
export DEPLOY_BRANCH=<YOUR_CI_PROVIDER_BRANCH_VARIABLE>
./git-artifact git@github.com:yourorg/your-repo-destination.git \
  --branch="${DEPLOY_BRANCH}" \
  --push
```

See extended and
fully-configured [example in the Scaffold project](https://github.com/drevops/scaffold/blob/develop/scripts/drevops/deploy-artifact.sh).

## Options

| Name                   | Default value       | Description                                                                                     |
|------------------------|---------------------|-------------------------------------------------------------------------------------------------|
| `--branch`             | `[branch]`          | Destination branch with optional tokens.                                                        |
| `--gitignore`          |                     | Path to gitignore file to replace current `.gitignore`.                                         |
| `--message`            | `Deployment commit` | Commit message with optional tokens.                                                            |
| `--mode`               | `force-push`        | Mode of artifact build: branch, force-push or diff.                                             |
| `--no-cleanup`         |                     | Do not cleanup after run.                                                                       |
| `--now`                |                     | Internal value used to set internal time.                                                       |
| `--dry-run`            |                     | Run without pushing to the remote repository.                                                   |
| `--log`                |                     | Path to the log file.                                                                           |
| `--root`               |                     | Path to the root for file path resolution. Uses current directory if not specified.             |
| `--show-changes`       |                     | Show changes made to the repo by the build in the output.                                       |
| `--src`                |                     | Directory where source repository is located. Uses root directory if not specified.             |
| `-h, --help`           |                     | Display help for the given command. Displays help for the artifact command if no command given. |
| `-q, --quiet`          |                     | Do not output any message.                                                                      |
| `-V, --version`        |                     | Display this application version.                                                               |
| `--ansi`               |                     | Force ANSI output. Use `--no-ansi` to disable.                                                  |
| `-n, --no-interaction` |                     | Do not ask any interactive question.                                                            |
| `-v, --verbose`        |                     | Increase the verbosity of messages: 1 for normal, 2 for more verbose, 3 for debug.              |

### Modifying artifact content

`--gitignore` option allows to specify the path to the artifact's `.gitignore`
file that replaces existing `.gitignore` (if any) during the build. Any files no
longer ignored by the replaced artifact's `.gitignore` are added into the
deployment commit. If there are no no-longer-excluded files, the deployment
commit is still created, to make sure that the deployment timestamp is
captured.

### Token support

Tokens are pre-defined strings surrounded by `[` and `]` and may contain
optional formatter (for flexibility). For example, `[timestamp:Y-m-d]` is
replaced with the current timestamp in format `Y-m-d` (token formatter), which
is PHP [`date()`](https://www.php.net/manual/en/function.date.php) expected format.

Both `--branch` and `--message` option values support token replacement.

Available tokens:

- `[timestamp:FORMAT]` - current time with a PHP [`date()`](https://www.php.net/manual/en/function.date.php)-compatible `FORMAT`.
- `[branch]` - current branch in the source repository.
- `[safebranch]` - current branch in the source repository with with all non-alphanumeric characters replaced with `-` and lowercased.
- `[tags:DELIMITER]` - tags from the latest commit in the source repository
  separated by a `DELIMITER`.

## Maintenance

### Lint and fix code

```bash
composer lint
composer lint-fix
```

### Run tests

```bash
composer test
```

---
_Repository created using https://getscaffold.dev/ project scaffold template_
