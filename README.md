# robo-git-artefact
Robo task to push git artefact to remote repository
[![CircleCI](https://circleci.com/gh/integratedexperts/robo-git-artefact.svg?style=svg&circle-token=04cc2cab69b05f60a48e474f966a5bce8a71b1aa)](https://circleci.com/gh/integratedexperts/robo-git-artefact)

## How it works
Say, you have 2 git repositories: _source_ and _destination_. 

You commit your code changes to _source_, run build, which would resolve all dependencies and build assets, and expect to have all of this packaged into a _deployment artefact_ that can be pushed to your _destination_ repository.

The project file structure and git history are preserved.

The deployment is performed into a brand-new branch in _destination_ and it is expected that actual code deployment on the environment is done by checking out specific deployment branch.

By default, the _destination_ branch name follows the format `[branch]-[timestamp:Y-m-d_H-i-s]`, which guarantees uniqueness of branches. Overriding of _destination_ branch is available by using `--branch` option. Token replacement is supported (see below). 

Each _deployment branch_ will have all _source_ commits plus an extra _deployment commit_ with a _deployment message_. This commit is added even if there were no files added during the artefact build process (see below). By default, the _deployment commit message_ is `Deployment commit`, but it can be overridden by providing a `--message` option. Token replacement is supported (see below). 

### Adding dependencies
`--gitignore` option allows to specify the path to the _artefact gitignore_ file that replaces existing _.gitignore_ (if any) during the build. Any files no longer ignored by the replaced `artefact gitignore` are added in `deployment commit`. If there are no no-longer-excluded files, the _deployment commit_ is still created, to make sure that the deployment timestamp is captured.

### Token support
Both `--branch` and `--message` option values support token replacement. Tokens are pre-defined strings surrounded by `[` and `]` and may contain optional formatter (for flexibility). For example, `[timestamp:Y-m-d]` is replaced with current timestamp in format `Y-m-d` (token formatter), which is PHP `date()` expected format.

Available tokens:
- `[timestamp:FORMAT]` - current time with a PHP `date()`-compatible format.
- `[branch]` - current `source` branch.
- `[tags]` - tags from latest `_source` commit (if any), separated by comma.

## Usage

In your `RoboFile.php`:
```php
<?php
use IntegratedExperts\Robo\ArtefactTrait;

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
`robo artefact git@myserver.com/repository.git` - this will create an artefact from current directory and will send it to the specified remote repository into the same branch as a current one.
