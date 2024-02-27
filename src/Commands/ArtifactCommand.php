<?php

declare(strict_types = 1);

namespace DrevOps\GitArtifact\Commands;

use Robo\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Artifact Command.
 */
class ArtifactCommand extends Command
{

  /**
   * Construct for command.
   *
   * @param Runner $runner
   *   Robo runner.
   * @param string|null $name
   *   Command name.
   */
    public function __construct(protected Runner $runner, ?string $name = null)
    {
        parent::__construct($name);
    }

  /**
   * Configure command.
   */
    protected function configure(): void
    {
        $this->setName('artifact');
        $this->setDescription('Push artifact of current repository to remote git repository.');
        $this->addArgument('remote', InputArgument::REQUIRED, 'Path to the remote git repository.');
        $this
        ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Destination branch with optional tokens.', '[branch]')
        ->addOption('debug', null, InputOption::VALUE_NONE, 'Print debug information.')
        ->addOption('gitignore', null, InputOption::VALUE_REQUIRED, 'Path to gitignore file to replace current .gitignore.')
        ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Commit message with optional tokens.', 'Deployment commit')
        ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode of artifact build: branch, force-push or diff. Defaults to force-push.', 'force-push')
        ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not cleanup after run.')
        ->addOption('now', null, InputOption::VALUE_REQUIRED, 'Internal value used to set internal time.')
        ->addOption('push', null, InputOption::VALUE_NONE, 'Push artifact to the remote repository')
        ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Path to the report file.')
        ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Path to the root for file path resolution. If not specified, current directory is used.')
        ->addOption('show-changes', null, InputOption::VALUE_NONE, 'Show changes made to the repo by the build in the output.')
        ->addOption('src', null, InputOption::VALUE_REQUIRED, 'Directory where source repository is located. If not specified, root directory is used.')
        ->addOption('simulate', null, InputOption::VALUE_NONE, 'Run in simulated mode (show what would have happened).')
        ->addOption('progress-delay', null, InputOption::VALUE_REQUIRED, 'Number of seconds before progress bar is displayed in long-running task collections.', 2)
        ->addOption('define', 'D', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Define a configuration item value');
    }

  /**
   * Perform actual command.
   *
   * @param InputInterface $input
   *   Input.
   * @param OutputInterface $output
   *   Output.
   *
   * @return int
   *   Status code.
   */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argv = [
        'vendor/bin/robo',
        'artifact',
        ];
        $arguments = $input->getArguments();
        foreach ($arguments as $argument) {
            if (!empty($argument)) {
                $argv[] = $argument;
            }
        }
        $options = $input->getOptions();
        foreach ($options as $name => $value) {
            if (is_string($value) || is_numeric($value)) {
                $argv[] = sprintf('--%s=%s', $name, $value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $argv[] = sprintf('--%s=%s', $name, $item);
                }
            } elseif ($value) {
                $argv[] = '--'.$name;
            }
        }

        return $this->runner->execute($argv, null, null, $output);
    }
}
