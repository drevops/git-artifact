<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Traits;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
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
   * Create Logger.
   *
   * @param string $name
   *   Name.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   Output.
   * @param string $filepath
   *   Filepath to log file.
   *
   * @return \Psr\Log\LoggerInterface
   *   Logger.
   */
  public static function loggerCreate(string $name, OutputInterface $output, string $filepath): LoggerInterface {
    $logger = new Logger($name);

    $handler = new ConsoleHandler($output);
    $logger->pushHandler($handler);

    if (!empty($filepath)) {
      $map = [
        OutputInterface::VERBOSITY_QUIET => Level::Error,
        OutputInterface::VERBOSITY_NORMAL => Level::Warning,
        OutputInterface::VERBOSITY_VERBOSE => Level::Notice,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info,
        OutputInterface::VERBOSITY_DEBUG => Level::Debug,
      ];

      $handler = new StreamHandler($filepath, $map[$output->getVerbosity()] ?? Level::Debug);

      $logger->pushHandler($handler);
    }

    return $logger;
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

}
