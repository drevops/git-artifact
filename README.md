<p align="center">
  <a href="" rel="noopener">
  <img width=150px height=150px src="logo.png" alt="Git Artifact logo"></a>
</p>

<h2 align="center">Package and push files to a remote repository</h2>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/drevops/git-artifact.svg)](https://github.com/drevops/git-artifact/pulls)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/drevops/git-artifact)
[![codecov](https://codecov.io/gh/drevops/git-artifact/branch/main/graph/badge.svg?token=QNBXCIBK5J)](https://codecov.io/gh/drevops/git-artifact)
[![Total Downloads](https://poser.pugx.org/drevops/behat-screenshot/downloads)](https://packagist.org/packages/drevops/git-artifact)
[![Docker Pulls](https://img.shields.io/docker/pulls/drevops/git-artifact?logo=docker)](https://hub.docker.com/r/drevops/git-artifact)
![amd64](https://img.shields.io/badge/arch-linux%2Famd64-brightgreen)
![arm64](https://img.shields.io/badge/arch-linux%2Farm64-brightgreen)
![LICENSE](https://img.shields.io/github/license/drevops/git-artifact)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

[![Test PHP](https://github.com/drevops/git-artifact/actions/workflows/test-php.yml/badge.svg)](https://github.com/drevops/git-artifact/actions/workflows/test-php.yml)
[![Test Docker](https://github.com/drevops/git-artifact/actions/workflows/test-docker.yml/badge.svg)](https://github.com/drevops/git-artifact/actions/workflows/test-docker.yml)
[![CircleCI](https://circleci.com/gh/drevops/git-artifact.svg?style=shield)](https://circleci.com/gh/drevops/git-artifact)

[![Vortex Ecosystem](https://img.shields.io/badge/%F0%9F%8C%80-Vortex%20Ecosystem-2C5A68?style=for-the-badge&labelColor=65ACBC)](https://github.com/drevops/vortex)
</div>

---

## 🌟 With Git Artifact, you can:

📦 Assemble a code artifact locally or in CI<br/>
🧹 Exclude any unwanted files using a deployment `.gitignore`<br/>
📤 Transfer the final artifact to a destination Git repository for deployment<br/>
🔁 Choose between `force-push` or `branch` modes to fit your workflow<br/>

See example of deployed artifact
in [Artifact branches](https://github.com/drevops/git-artifact-destination/branches).

## 🔀 Workflow

1️⃣ 🧑‍💻 Develop in the _source_ repository<br/>
2️⃣ 📦 CI installs dependencies and runs **git-artifact** to package and push code to _destination_ repository<br/>
3️⃣ 🚀 Hosting receives the code artifact and triggers a deployment<br/>

## 🎚️ Modes

### `force-push` mode (default)

Push the packaged artifact to the **same branch** in the _destination_ repository.
This will carry over the branch history from the _source_ repository and will overwrite
the existing branch history in the _destination_ repository.

```
==================================================
 🏃 Run 1
==================================================

Local repo                  Remote repo
                            +------------------+
                            | Artifact commit  | 💥 New commit
                            +------------------+
+-----------+               +------------------+
| Commit 2  |               | Commit 2         | \
+-----------+  ==  📦  ==>  +------------------+  ) 👍 Source commit
| Commit 1  |               | Commit 1         | /   history preserved
+-----------+               +------------------+
 `mybranch`                      `mybranch`

                                     👆
                        Branch name identical to source

==================================================
 🏃 Run 2
==================================================

Local repo                    Remote repo
                            +------------------+
                            | Artifact commit  | 💥 New commit
                            +------------------+
+-----------+               +------------------+
| Commit 4  |               | Commit 4         |  \
+-----------+               +------------------+   \
| Commit 3  |               | Commit 3         |    \
+-----------+  ==  📦  ==>  +------------------+     )  👍 Source commit
| Commit 2  |               | Commit 2         |    /    history preserved
+-----------+               +------------------+   /
| Commit 1  |               | Commit 1         |  /
+-----------+               +------------------+
 `mybranch`                      `mybranch`

                                     👆
                       Branch name identical to source

```

#### Use case

Forwarding all changes in the _source_ repository to the _destination_
repository **as-is** for **every branch**: for example, a commit in the _source_
repository branch `feature/123` would create a commit in the _destination_
repository branch `feature/123`. The next commit to the _source_ repository
branch `feature/123` would update the _destination_ repository branch
`feature/123` with the changes, but would overwrite the last "artifact commit".

### `branch` mode

Push the packaged artifact to the **new branch** in the _destination_ repository.
This will carry over the branch history from the _source_ repository to a
dedicated branch in the _destination_ repository. The follow-up pushes to the
branch in the _destination_ repository will be blocked.

```
==================================================
 🏃 Run 1
==================================================

Local repo                  Remote repo
                            +------------------+
                            | Artifact commit  | 💥 New commit
                            +------------------+
+-----------+               +------------------+
| Commit 2  |               | Commit 2         | \
+-----------+  ==  📦  ==>  +------------------+  ) 👍 Source commit
| Commit 1  |               | Commit 1         | /    history preserved
+-----------+               +------------------+

 `mybranch`                  `deployment/1.2.3`
 tagged with
   `1.2.3`

     👆                              👆
 Tagged branch              New branch based on tag

==================================================
 🏃 Run 2
==================================================

Local repo                    Remote repo
                            +------------------+
                            | Artifact commit  | 💥 New commit
                            +------------------+
+-----------+               +------------------+
| Commit 4  |               | Commit 4         |  \
+-----------+               +------------------+   \
| Commit 3  |               | Commit 3         |    \
+-----------+  ==  📦  ==>  +------------------+     )  👍 Source commit
| Commit 2  |               | Commit 2         |    /    history preserved
+-----------+               +------------------+   /
| Commit 1  |               | Commit 1         |  /
+-----------+               +------------------+

 `mybranch`                  `deployment/1.2.4`
 tagged with
   `1.2.4`  👈 New tag 1.2.4

     👆                              👆
 Tagged branch            New branch based on a new tag
 with a new tag

```

#### Use case

Creating a **new branch** in the _destination_ repository for every **tag**
created in the _source_ repository: for example, a tag `1.2.3` in the source
repository would create a branch `deployment/1.2.3` in the destination
repository. The addition of the new tags would create new unique branches in the
destination repository.

#### Cleanup of stale branches

In `branch` mode the _destination_ repository accumulates a new branch for every
deployment. Enable `--cleanup-stale` to remove old ones automatically after a
successful push:

```
./git-artifact git@github.com:yourorg/your-repo-destination.git \
  --mode=branch \
  --branch="deployment/[tags:.]" \
  --cleanup-stale \
  --cleanup-pattern="deployment/*" \
  --cleanup-age=3
```

Any branch in the _destination_ repository that matches `--cleanup-pattern` and
whose last commit is older than `--cleanup-age` days is deleted. The branch that
was just pushed and the _destination_ repository's default branch are never
deleted. `--cleanup-pattern` is required - it is the only way to identify the
branches created by your deployments - and is matched as a shell glob. Add
`--dry-run` to preview deletions without performing them.

Because deletion uses standard Git, this works with any remote (GitHub, GitLab,
Bitbucket, self-hosted), not only GitHub.

## 📥 Installation

### As a standalone binary

This tool is intended to be used as a standalone binary. You will need to
have PHP installed on your system to run the binary.

Download the latest release from the [GitHub releases page](https://github.com/drevops/git-artifact/releases/latest).

### As a Docker container

The tool is also published as a multi-architecture (`linux/amd64`, `linux/arm64`) Docker image at [`drevops/git-artifact`](https://hub.docker.com/r/drevops/git-artifact), with `git` and an SSH client bundled in - no local PHP required. Mount your source repository at `/app` and pass the same arguments you would pass to the binary:

```shell
docker run --rm \
  -v "${PWD}":/app \
  -e GIT_AUTHOR_NAME="Deployer" -e GIT_AUTHOR_EMAIL="deployer@example.com" \
  -e GIT_COMMITTER_NAME="Deployer" -e GIT_COMMITTER_EMAIL="deployer@example.com" \
  drevops/git-artifact \
  https://github.com/yourorg/your-repo-destination.git --branch=main
```

The `GIT_AUTHOR_*` and `GIT_COMMITTER_*` variables set the identity for the deployment commit. To push to an SSH remote, also mount your SSH credentials read-only:

```shell
docker run --rm \
  -v "${PWD}":/app \
  -v "${HOME}/.ssh":/root/.ssh:ro \
  -e GIT_AUTHOR_NAME="Deployer" -e GIT_AUTHOR_EMAIL="deployer@example.com" \
  -e GIT_COMMITTER_NAME="Deployer" -e GIT_COMMITTER_EMAIL="deployer@example.com" \
  drevops/git-artifact \
  git@github.com:yourorg/your-repo-destination.git --branch=main
```

#### Image tags

Cross-platform (`linux/amd64`, `linux/arm64`) images are built by GitHub Actions and pushed to [Docker Hub](https://hub.docker.com/r/drevops/git-artifact):

- `<version>` (e.g. `1.2.3`) - published when a release tag is created on GitHub.
- `latest` - published when a release tag is created on GitHub.
- `canary` - published on every push to the `main` branch (latest unreleased changes).

Pin to a specific `<version>` tag for reproducible deployments and use `canary` only to try out unreleased changes.

### As a Composer dependency

You may also install this tool globally using Composer:
```shell
composer global require --dev drevops/git-artifact:~1.1
```

#### 📌 Version constraint

When using `git-artifact` in CI/CD scripts, we recommend using **Tilde Version Range Operator** to ensure stability.
The tilde constraint allows patch updates (e.g., `1.0.0` → `1.1.1`) but blocks minor version updates (e.g., `1.1.0` → `1.2.0`).

This ensures that:
- **Security fixes and bug patches** are automatically applied
- **CI/CD pipelines remain stable** - no unexpected breaking changes
- **Minor version updates are blocked** - these may introduce behavioral changes that could affect deployments

This is especially important in CI/CD environments where deployment reliability is critical and changes should be tested before adoption.

## ▶️ Usage

```shell
./git-artifact git@github.com:yourorg/your-repo-destination.git
```

This will create an artifact from current directory and will send it to the
specified remote repository into the same branch as a current one.

Avoid including development dependencies in your artifacts. Instead, configure
your CI to install production-only dependencies, export the resulting code,
and use that as the artifact source. See our CI examples below.

Call from the CI configuration or deployment script:

```shell
export DEPLOY_BRANCH=<YOUR_CI_PROVIDER_BRANCH_VARIABLE>
./git-artifact git@github.com:yourorg/your-repo-destination.git \
  --branch="${DEPLOY_BRANCH}" \
  --push
```

CI providers may report branches differently when packaging is triggered by tags.
We encourage you to explore our continuously and automatically tested examples:

- [GitHub Actions](.github/workflows/test-php.yml)
- [CircleCI](.circleci/config.yml)

See extended and
fully-configured [example in the Vortex project](https://github.com/drevops/vortex/blob/develop/scripts/vortex/deploy-artifact.sh).


## 🎛️ Options

| Name                       | Default value       | Description                                                                                                         |
|----------------------------|---------------------|---------------------------------------------------------------------------------------------------------------------|
| `--ansi`                   |                     | Force ANSI output. Use `--no-ansi` to disable                                                                       |
| `--branch`                 | `[branch]`          | Destination branch with optional tokens (see below)                                                                 |
| `--cleanup-age`            | `7`                 | Age in days after which a matching remote branch is considered stale; used with `--cleanup-stale`                    |
| `--cleanup-pattern`        |                     | Glob pattern of remote branches eligible for stale cleanup (e.g. `deployment/*`); required with `--cleanup-stale`    |
| `--cleanup-stale`          |                     | Delete stale remote branches that match `--cleanup-pattern` and are older than `--cleanup-age` days                  |
| `--dry-run`                |                     | Run without pushing to the remote repository                                                                        |
| `--fail-on-missing-branch` |                     | Fail artifact packaging if source branch cannot be determined. By default, artifact packaging is skipped gracefully |
| `--gitignore`              |                     | Path to the `.gitignore` file to replace the current `.gitignore`                                                   |
| `--log`                    |                     | Path to the log file                                                                                                |
| `--message`                | `Deployment commit` | Commit message with optional tokens (see below)                                                                     |
| `--mode`                   | `force-push`        | Mode of artifact packaging: `branch`, `force-push`                                                                  |
| `--no-cleanup`             |                     | Do not cleanup after run                                                                                            |
| `--now`                    |                     | Internal value used to set internal time                                                                            |
| `--root`                   |                     | Path to the root for file path resolution. Uses current directory if not specified                                  |
| `--show-changes`           |                     | Show changes made to the repo during packaging in the output                                                        |
| `--src`                    |                     | Directory where source repository is located. Uses root directory if not specified                                  |
| `-V, --version`            |                     | Display this application version                                                                                    |
| `-h, --help`               |                     | Display help for the given command                                                                                  |
| `-n, --no-interaction`     |                     | Do not ask any interactive question                                                                                 |
| `-q, --quiet`              |                     | Do not output any messages                                                                                          |
| `-v, --verbose`            |                     | Increase the verbosity of messages: 1 for normal, 2 for more verbose, 3 for debug                                   |

## 🧹 Modifying artifact content

`--gitignore` option allows to specify the path to the artifact's `.gitignore`
file that replaces existing `.gitignore` (if any) during packaging. Any files no
longer ignored by the replaced artifact's `.gitignore` are added into the
deployment commit. If there are no no-longer-excluded files, the deployment
commit is still created, to make sure that the deployment timestamp is
captured.

## 🏷️ Token support

Tokens are pre-defined strings surrounded by `[` and `]` and may contain
optional formatter. For example, `[timestamp:Y-m-d]` is
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

### 🧪 Testing

Packaging and deployment of artifacts is a mission-critical process, so we maintain
a set of unit, functional and integration tests to make sure that everything works
as expected.

You can see examples of the branches created by the Git Artifact in the [example _destination_ repository](https://github.com/drevops/git-artifact-destination/branches).

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
