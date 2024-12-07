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
 * Log trait.
 */
trait LogTrait {

  /**
   * Logger.
   */
  protected LoggerInterface $logger;

  /**
   * Path to the temporary log file.
   */
  protected string $logDumpFile = '';

  /**
   * Prepare logger.
   */
  protected function logPrepare(string $name, InputInterface $input, OutputInterface $output): void {
    if ($input->getOption('log')) {
      $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
    }

    $this->logDumpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . time() . '-artifact-log.log';

    $this->logger = new Logger($name);

    $handler = new ConsoleHandler($output);
    $this->logger->pushHandler($handler);

    if (!empty($this->logDumpFile)) {
      $map = [
        OutputInterface::VERBOSITY_QUIET => Level::Error,
        OutputInterface::VERBOSITY_NORMAL => Level::Warning,
        OutputInterface::VERBOSITY_VERBOSE => Level::Notice,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info,
        OutputInterface::VERBOSITY_DEBUG => Level::Debug,
      ];

      $handler = new StreamHandler($this->logDumpFile, $map[$output->getVerbosity()] ?? Level::Debug);

      $this->logger->pushHandler($handler);
    }

    $this->logDebug('Debug messages enabled');
  }

  /**
   * Log debug.
   *
   * @param string|\Stringable $message
   *   Message.
   * @param array<mixed> $context
   *   Context.
   */
  public function logDebug(string|\Stringable $message, array $context = []): void {
    $this->logger->debug($message, $context);
  }

  /**
   * Log notice.
   *
   * @param string|\Stringable $message
   *   Message.
   * @param array<mixed> $context
   *   Context.
   */
  public function logNotice(string|\Stringable $message, array $context = []): void {
    $this->logger->notice($message, $context);
  }

  /**
   * Log error.
   *
   * @param string|\Stringable $message
   *   Message.
   * @param array<mixed> $context
   *   Context.
   */
  public function logError(string|\Stringable $message, array $context = []): void {
    $this->logger->error($message, $context);
  }

  /**
   * Dump log to file.
   */
  protected function logDump(string $filename): void {
    if ($this->fs->exists($this->logDumpFile)) {
      $this->fs->copy($this->logDumpFile, $filename);
      $this->fs->remove($this->logDumpFile);
    }
  }

}
