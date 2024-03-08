<?php

declare(strict_types = 1);

namespace DrevOps\GitArtifact\Tests\Exception;

use PHPUnit\Framework\Exception;

/**
 * A new ErrorException class.
 */
class ErrorException extends Exception
{
    /**
     * {@inheritdoc}
     */
    public function __construct(string $message, int $code, string $file, int $line, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->file = $file;
        $this->line = $line;
    }
}
