<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact;

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
   *
   * @return \Psr\Log\LoggerInterface
   *   Logger.
   */
  public static function createLogger(string $name, OutputInterface $output, string $logFile): LoggerInterface {
    $logger = new Logger($name);
    // Console handler.
    $handler = new ConsoleHandler($output);
    $logger->pushHandler($handler);
    // Stream handler if needed.
    if (!empty($logFile)) {
      $verbosityMapping = [
        OutputInterface::VERBOSITY_QUIET => Level::Error,
        OutputInterface::VERBOSITY_NORMAL => Level::Warning,
        OutputInterface::VERBOSITY_VERBOSE => Level::Notice,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Level::Info,
        OutputInterface::VERBOSITY_DEBUG => Level::Debug,
      ];
      $verbosity = $output->getVerbosity();
      $level = $verbosityMapping[$verbosity] ?? Level::Debug;
      $handler = new StreamHandler($logFile, $level);
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
   * Log debug.
   *
   * @param string|\Stringable $message
   *   Message.
   * @param array<mixed> $context
   *   Context.
   */
  public function logNotice(string|\Stringable $message, array $context = []): void {
    $this->logger->notice($message, $context);
  }

}
