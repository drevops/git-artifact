<?php

declare(strict_types = 1);

namespace DrevOps\Robo\Commands;

use Robo\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ArtifactCommand extends Command
{

  protected Runner $runner;

  public function __construct(Runner $runner, ?string $name = null)
  {
    parent::__construct($name);
    $this->runner = $runner;
  }

  protected function configure()
  {
    $this->setName('git-artifact');
    $this->addArgument('remote', InputArgument::REQUIRED, 'Path to the remote git repository.');
    $this
      ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Destination branch with optional tokens.', '[branch]')
      ->addOption('debug', null, InputOption::VALUE_NONE, 'Print debug information.')
      ->addOption('gitignore', null, InputOption::VALUE_REQUIRED, 'Path to gitignore file to replace current .gitignore.')
      ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Commit message with optional tokens.', 'Deployment commit')
      ->addOption('mode', null, InputOption::VALUE_REQUIRED,'Mode of artifact build: branch, force-push or diff. Defaults to force-push.', 'force-push')
      ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not cleanup after run.')
      ->addOption('now', null, InputOption::VALUE_REQUIRED, 'Internal value used to set internal time.')
      ->addOption('push', null, InputOption::VALUE_NONE, 'Push artifact to the remote repository')
      ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Path to the report file.')
      ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Path to the root for file path resolution. If not specified, current directory is used.')
      ->addOption('show-changes', null, InputOption::VALUE_NONE, 'Show changes made to the repo by the build in the output.')
      ->addOption('src', null, InputOption::VALUE_REQUIRED, 'Directory where source repository is located. If not specified, root directory is used.');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $first = array_shift($_SERVER['argv']);
    array_unshift($_SERVER['argv'], $first, 'artifact');
    return $this->runner->execute($_SERVER['argv'], null, null, $output);
  }
}
