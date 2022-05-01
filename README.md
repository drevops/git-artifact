# Package and push files to remote repositories
Robo task to push git artefact to remote repository

[![CircleCI](https://circleci.com/gh/drevops/robo-git-artefact.svg?style=shield&circle-token=04cc2cab69b05f60a48e474f966a5bce8a71b1aa)](https://circleci.com/gh/drevops/robo-git-artefact)
[![Latest Stable Version](https://poser.pugx.org/drevops/robo-git-artefact/version)](https://packagist.org/packages/drevops/robo-git-artefact)
[![Total Downloads](https://poser.pugx.org/drevops/robo-git-artefact/downloads)](https://packagist.org/packages/drevops/robo-git-artefact)
[![License](https://poser.pugx.org/drevops/robo-git-artefact/license)](https://packagist.org/packages/drevops/robo-git-artefact)

## What is it?
Build artifact from your codebase in CI and push it to a separate git repo.

## Why?
Some hosting providers, like Acquia, have limitation on the languages or frameworks required to build applications (for example, running `composer install` is not possible due to read-only file system). This means that a website has to be developed in a different (source) repository, built as artefact locally or in CI, and sent to the hosting provider's version control system (destination repository).

This package allows to do so in a transaparent way: files that needs to be present in the destination repository are controlled by a `.gitignore.deployment` file; any files that are ignored by this file will not be present in the destination repository.

Since destination repository requires a commit to add changes introduced by the artefact files (CSS, JS, etc.), there are 2 modes to make this commit: "force-push" and "branch".

See example of deployed artefact in [Artefact branches](https://github.com/drevops/robo-git-artefact-destination/branches).

## Modes
### Force-push mode (default)
Push packaged artefact to the same branch, preserving the history from the source repository, but overwriting history in destination repository on each push.

```
--mode=force-push
```

![diagram of force-push mode](https://user-images.githubusercontent.com/378794/33816665-a7b0e4a8-de8e-11e7-88f2-80baefb3d73f.png)

### Branch mode
Push packaged artefact to the new branch on each deployment, preserving history from the source repository, but requiring to trigger a deployment of newly created branch after each deployment.

```
--mode=branch
```

![diagram of branch mode](https://user-images.githubusercontent.com/378794/33816666-a87b3910-de8e-11e7-82cd-51e007ece063.png)


## Installation
```
composer require --dev -n --ansi --prefer-source --ignore-platform-reqs drevops/robo-git-artefact
```

## Usage
Use provided [`RoboFile.php`](RoboFile.php) or crearte a custom `RoboFile.php` in your repository with the following content:

```php
<?php
use DrevOps\Robo\ArtefactTrait;

/**
 * Class RoboFile.
 */
class RoboFile extends \Robo\Tasks
{

    use ArtefactTrait {
        ArtefactTrait::__construct as private __artefactConstruct;
    }

    public function __construct()
    {
        $this->__artefactConstruct();
    }
}
```

### Run
```
vendor/bin/robo artefact git@myserver.com/repository.git
```
This will create an artefact from current directory and will send it to the specified remote repository into the same branch as a current one.

### Run in CI

Fill-in these variables trough UI or in deployment script.
```
# Remote repository to push artefact to.
DEPLOY_REMOTE="${DEPLOY_REMOTE:-}"
# Remote repository branch. Can be a specific branch or a token.
DEPLOY_BRANCH="${DEPLOY_BRANCH:-[branch]}"
# Source of the code to be used for artefact building.
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
```

Call from CI configuration or deployment script:
```
"${HOME}/.composer/vendor/bin/robo" --ansi \
  --load-from "${HOME}/.composer/vendor/drevops/robo-git-artefact/RoboFile.php" artefact "${DEPLOY_REMOTE}" \
  --root="${DEPLOY_ROOT}" \
  --src="${DEPLOY_SRC}" \
  --branch="${DEPLOY_BRANCH}" \
  --gitignore="${DEPLOY_SRC}"/.gitignore.deployment \
  --report="${DEPLOY_REPORT}" \
  --push
```

See extended and fully-configured example in Drupal-Dev project https://github.com/drevops/drupal-dev/blob/8.x/scripts/deploy.sh

## Options
```
Usage:
  artefact [options] [--] <remote>

Arguments:
  remote                               Path to the remote git repository.

Options:
      --branch[=BRANCH]                Destination branch with optional tokens. [default: "[branch]"]
      --gitignore=GITIGNORE            Path to gitignore file to replace current .gitignore.
      --message[=MESSAGE]              Commit message with optional tokens. [default: "Deployment commit"]
      --mode[=MODE]                    Mode of artefact build: branch, force-push or diff. Defaults to force-push. [default: "force-push"]
      --now=NOW                        Internal value used to set internal time.
      --push                           Push artefact to the remote repository. Defaults to FALSE.
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
  Push artefact of current repository to remote git repository.
```

### Adding dependencies
`--gitignore` option allows to specify the path to the _artefact gitignore_ file that replaces existing _.gitignore_ (if any) during the build. Any files no longer ignored by the replaced _artefact gitignore_ are added into the _deployment commit_. If there are no no-longer-excluded files, the _deployment commit_ is still created, to make sure that the deployment timestamp is captured.

### Token support
Both `--branch` and `--message` option values support token replacement. Tokens are pre-defined strings surrounded by `[` and `]` and may contain optional formatter (for flexibility). For example, `[timestamp:Y-m-d]` is replaced with current timestamp in format `Y-m-d` (token formatter), which is PHP `date()` expected format.

Available tokens:
- `[timestamp:FORMAT]` - current time with a PHP `date()`-compatible format.
- `[branch]` - current `source` branch.
- `[tags]` - tags from latest `_source` commit (if any), separated by comma.

## Examples
### Push branch to the same remote
```
robo artefact git@myserver.com/repository.git --push
```
In this example, all commits in the repository will be pushed to the same branch as current one with all processed files (assets etc.) captured in the additional deployment commit. `--push` flag enables actual pushing into remote repository.


### Push release branches created from tags
```
robo artefact git@myserver.com/repository.git --mode=branch --branch=release/[tags:-] --push
```
In this example, if the latest commit was tagged with tag `1.2.0`, the artefact will be pushed to the branch `release/1.2.0`. If there latest commit is tagged with multiple tags - they will be glued to gether with delimiter `-`, which would reult in the branch name `release/1.2.0-secondtag`.

## Contributing
1. Fork this repo.
2. Check it out locally and create a feature branch.
3. `composer install` to install all required dependencies.
4. Work on your changes.
5. `composer lint` to check coding standards.
6. `composer test` to run test.
7. Commit your changes, push and create a pull request.
8. `composer clean` to cleanup your environment.
