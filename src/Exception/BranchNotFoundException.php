<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Exception;

/**
 * Exception when branch cannot be determined.
 *
 * This can occur in two scenarios:
 * 1. Detached HEAD state with no traceable source branch.
 * 2. Branch was deleted while CI is still running.
 */
class BranchNotFoundException extends GitException {

  /**
   * Constructor.
   *
   * @param string $message
   *   The exception message.
   * @param string $commitHash
   *   The commit hash where the repository is currently at.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(
    string $message = 'Unable to determine source branch',
    protected string $commitHash = '',
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, 0, $previous);
  }

  /**
   * Get the commit hash.
   *
   * @return string
   *   The commit hash.
   */
  public function getCommitHash(): string {
    return $this->commitHash;
  }

}
