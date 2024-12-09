<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Traits;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Logger trait.
 */
trait LoggerTrait {

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Path to the temporary log file.
   */
  protected string $loggerDumpFile = '';

  /**
   * Initialize logger.
   *
   * @param string $name
   *   Logger name.
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input interface.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output interface.
   */
  protected function loggerInit(string $name, InputInterface $input, OutputInterface $output): void {
    if ($input->getOption('log')) {
      $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
    }

    $this->loggerDumpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . time() . '-artifact-log.log';

    $this->logger = new Logger($name);

    $console_handler = new ConsoleHandler($output);
    $this->logger->pushHandler($console_handler);

    if (!empty($this->loggerDumpFile)) {
      $map = [
        OutputInterface::VERBOSITY_QUIET => Level::Error,
        OutputInterface::VERBOSITY_NORMAL => Level::Warning,
        OutputInterface::VERBOSITY_VERBOSE => Level::Notice,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info,
        OutputInterface::VERBOSITY_DEBUG => Level::Debug,
      ];

      $stream_handler = new StreamHandler($this->loggerDumpFile, $map[$output->getVerbosity()] ?? Level::Debug);
      $this->logger->pushHandler($stream_handler);
    }

    $this->logger->debug('Debug messages enabled');
  }

  /**
   * Dump logger to file.
   *
   * @param string $filename
   *   Filename to dump the logger to.
   */
  protected function loggerDump(string $filename): void {
    if ($this->fs->exists($this->loggerDumpFile)) {
      $this->fs->copy($this->loggerDumpFile, $filename);
      $this->fs->remove($this->loggerDumpFile);
    }
  }

}
