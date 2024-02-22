<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Git+Artifact&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Git Artifact"></a>
</p>

<h1 align="center">Package and push files to remote repositories</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/pulls)
[![CircleCI](https://circleci.com/gh/drevops/git-artifact.svg?style=shield)](https://circleci.com/gh/drevops/git-artifact)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/git-artifact)
[![Total Downloads](https://poser.pugx.org/drevops/behat-screenshot/downloads)](https://packagist.org/packages/drevops/git-artifact)
![LICENSE](https://img.shields.io/github/license/drevops/git-artifact)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

<p align="center">Robo task to push git artifact to remote repository.
    <br>
</p>

## What is it?

Build artifact from your codebase in CI and push it to a separate git repo.

## Why?

Some hosting providers, like Acquia, have limitation on the languages or
frameworks required to build applications (for example, running
`composer install` is not possible due to read-only file system). This means
that a website has to be developed in a different (source) repository, built as
artifact locally or in CI, and sent to the hosting provider's version control
system (destination repository).

This package allows doing so in a transparent way: files that need to be present
in the destination repository are controlled by a `.gitignore.deployment` file;
any files that are ignored by this file will not be present in the destination
repository.

Since destination repository requires a commit to add changes introduced by the
artifact files (CSS, JS, etc.), there are 2 modes to make this commit:
"force-push" and "branch".

See example of deployed artifact
in [Artefact branches](https://github.com/drevops/git-artifact-destination/branches).

## Modes

### Force-push mode (default)

Push packaged artifact to the same branch, preserving the history from the
source repository, but overwriting history in destination repository on each
push.

    --mode=force-push

![diagram of force-push mode](https://user-images.githubusercontent.com/378794/33816665-a7b0e4a8-de8e-11e7-88f2-80baefb3d73f.png)

### Branch mode

Push packaged artifact to the new branch on each deployment, preserving history
from the source repository, but requiring to trigger a deployment of newly
created branch after each deployment.

    --mode=branch

![diagram of branch mode](https://user-images.githubusercontent.com/378794/33816666-a87b3910-de8e-11e7-82cd-51e007ece063.png)

## Installation

    composer require --dev -n --ansi --prefer-source --ignore-platform-reqs drevops/git-artifact

## Usage

Use provided [`RoboFile.php`](RoboFile.php) or crearte a custom `RoboFile.php`
in your repository with the following content:

```php
<?php
use DrevOps\Robo\ArtefactTrait;

/**
 * Class RoboFile.
 */
class RoboFile extends \Robo\Tasks
{

    use ArtefactTrait {
        ArtefactTrait::__construct as private __artifactConstruct;
    }

    public function __construct()
    {
        $this->__artifactConstruct();
    }
}
```

### Run

    vendor/bin/robo artifact git@myserver.com/repository.git

This will create an artifact from current directory and will send it to the
specified remote repository into the same branch as a current one.

### Run in CI

See examples:

- [GitHub Actions](.github/workflows/test-php.yml)
- [CircleCI](.circleci/config.yml)

Fill-in these variables trough UI or in deployment script.

    # Remote repository to push artifact to.
    DEPLOY_REMOTE="${DEPLOY_REMOTE:-}"

    # Remote repository branch. Can be a specific branch or a token.
    DEPLOY_BRANCH="${DEPLOY_BRANCH:-[branch]}"

    # Source of the code to be used for artifact building.
    DEPLOY_SRC="${DEPLOY_SRC:-}"

    # The root directory where the deployment script should run from. Defaults to
    # the current directory.
    DEPLOY_ROOT="${DEPLOY_ROOT:-$(pwd)}"

    # Deployment report file name.
    DEPLOY_REPORT="${DEPLOY_REPORT:-${DEPLOY_ROOT}/deployment_report.txt}"

    # Email address of the user who will be committing to a remote repository.
    DEPLOY_USER_NAME="${DEPLOY_USER_NAME:-"Deployer Robot"}"

    # Name of the user who will be committing to a remote repository.
    DEPLOY_USER_EMAIL="${DEPLOY_USER_EMAIL:-deployer@example.com}"

Call from CI configuration or deployment script:

    "${HOME}/.composer/vendor/bin/robo" --ansi \
      --load-from "${HOME}/.composer/vendor/drevops/git-artifact/RoboFile.php" artifact "${DEPLOY_REMOTE}" \
      --root="${DEPLOY_ROOT}" \
      --src="${DEPLOY_SRC}" \
      --branch="${DEPLOY_BRANCH}" \
      --gitignore="${DEPLOY_SRC}"/.gitignore.deployment \
      --report="${DEPLOY_REPORT}" \
      --push

See extended and
fully-configured [example in the DrevOps project](https://github.com/drevops/drevops/blob/develop/scripts/drevops/deploy-artifact.sh).

## Options

    Usage:
      artifact [options] [--] <remote>

    Arguments:
      remote                               Path to the remote git repository.

    Options:
          --branch[=BRANCH]                Destination branch with optional tokens. [default: "[branch]"]
          --gitignore=GITIGNORE            Path to gitignore file to replace current .gitignore.
          --message[=MESSAGE]              Commit message with optional tokens. [default: "Deployment commit"]
          --mode[=MODE]                    Mode of artifact build: branch, force-push or diff. Defaults to force-push. [default: "force-push"]
          --no-cleanup                     Do not cleanup after run.
          --now=NOW                        Internal value used to set internal time.
          --push                           Push artifact to the remote repository. Defaults to FALSE.
          --report=REPORT                  Path to the report file.
          --root=ROOT                      Path to the root for file path resolution. If not specified, current directory is used.
          --show-changes                   Show changes made to the repo by the build in the output.
          --src=SRC                        Directory where source repository is located. If not specified, root directory is used.
      -h, --help                           Display this help message
      -q, --quiet                          Do not output any message
      -V, --version                        Display this application version
          --ansi                           Force ANSI output
          --no-ansi                        Disable ANSI output
      -n, --no-interaction                 Do not ask any interactive question
          --simulate                       Run in simulated mode (show what would have happened).
          --progress-delay=PROGRESS-DELAY  Number of seconds before progress bar is displayed in long-running task collections. Default: 2s. [default: 2]
      -D, --define=DEFINE                  Define a configuration item value. (multiple values allowed)
      -v|vv|vvv, --verbose                 Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

    Help:
      Push artifact of current repository to remote git repository.

### Adding dependencies

`--gitignore` option allows to specify the path to the _artifact gitignore_ file
that replaces existing _.gitignore_ (if any) during the build. Any files no
longer ignored by the replaced _artifact gitignore_ are added into the
_deployment commit_. If there are no no-longer-excluded files, the _deployment
commit_ is still created, to make sure that the deployment timestamp is
captured.

### Token support

Both `--branch` and `--message` option values support token replacement. Tokens
are pre-defined strings surrounded by `[` and `]` and may contain optional
formatter (for flexibility). For example, `[timestamp:Y-m-d]` is replaced with
current timestamp in format `Y-m-d` (token formatter), which is PHP `date()`
expected format.

Available tokens:

- `[timestamp:FORMAT]` - current time with a PHP `date()`-compatible format.
- `[branch]` - current `source` branch.
- `[tags]` - tags from latest `_source` commit (if any), separated by comma.

## Examples

### Push branch to the same remote

    robo artifact git@myserver.com/repository.git --push

In this example, all commits in the repository will be pushed to the same branch
as current one with all processed files (assets etc.) captured in the additional
deployment commit. `--push` flag enables actual pushing into remote repository.

### Push release branches created from tags

    robo artifact git@myserver.com/repository.git --mode=branch --branch=release/[tags:-] --push

In this example, if the latest commit was tagged with tag `1.2.0`, the artifact
will be pushed to the branch `release/1.2.0`. If there latest commit is tagged
with multiple tags - they will be glued to gether with delimiter `-`, which
would reult in the branch name `release/1.2.0-secondtag`.

## Maintenance

### Lint code

```bash
composer lint
composer lint:fix
```

### Run tests

```bash
composer test
```

