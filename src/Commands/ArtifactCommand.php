<?php

declare(strict_types = 1);

namespace DrevOps\GitArtifact\Commands;

use DrevOps\GitArtifact\Artifact;
use GitWrapper\EventSubscriber\GitLoggerEventSubscriber;
use GitWrapper\GitWrapper;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Artifact Command.
 */
class ArtifactCommand extends Command
{

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
        ->addOption(
            'gitignore',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to gitignore file to replace current .gitignore.'
        )
        ->addOption(
            'message',
            null,
            InputOption::VALUE_REQUIRED,
            'Commit message with optional tokens.',
            'Deployment commit'
        )
        ->addOption(
            'mode',
            null,
            InputOption::VALUE_REQUIRED,
            'Mode of artifact build: branch, force-push or diff. Defaults to force-push.',
            'force-push'
        )
        ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not cleanup after run.')
        ->addOption('now', null, InputOption::VALUE_REQUIRED, 'Internal value used to set internal time.')
        ->addOption('push', null, InputOption::VALUE_NONE, 'Push artifact to the remote repository')
        ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Path to the report file.')
        ->addOption(
            'root',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the root for file path resolution. If not specified, current directory is used.'
        )
        ->addOption(
            'show-changes',
            null,
            InputOption::VALUE_NONE,
            'Show changes made to the repo by the build in the output.'
        )
        ->addOption(
            'src',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory where source repository is located. If not specified, root directory is used.'
        );
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
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $gitWrapper = new GitWrapper();
        $optionDebug = $input->getOption('debug');
        if (($optionDebug || $output->isDebug())) {
            $logger = new Logger('git');
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
            $gitWrapper->addLoggerEventSubscriber(new GitLoggerEventSubscriber($logger));
        }
        $fileSystem = new Filesystem();
        $artifact = new Artifact($gitWrapper, $fileSystem, $output);
        $remote = $input->getArgument('remote');
        // @phpstan-ignore-next-line
        $artifact->artifact($remote, $input->getOptions());

        return Command::SUCCESS;
    }
}
