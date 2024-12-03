<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Traits;

/**
 * Trait TokenTrait.
 */
trait TokenTrait {

  /**
   * Process tokens.
   *
   * @param string $string
   *   String that may contain tokens surrounded by '[' and ']'.
   *
   * @return string|null
   *   String with replaced tokens if replacements are available or
   *   original string.
   */
  protected function tokenProcess(string $string): ?string {
    return preg_replace_callback('/(?:\[([^\]]+)\])/', function (array $match): string {
      if (!empty($match[1])) {
        $parts = explode(':', $match[1], 2);

        $token = $parts[0] ?? NULL;
        $argument = $parts[1] ?? NULL;

        if ($token) {
          $method = 'getToken' . ucfirst($token);

          if (method_exists($this, $method) && is_callable([$this, $method])) {
            $match[0] = (string) $this->$method($argument);
          }
        }
      }

      return strval($match[0]);
    }, $string);
  }

  /**
   * Check if the string has at least one token.
   *
   * @param string $string
   *   String to check.
   *
   * @return bool
   *   TRUE if there is at least one token present, FALSE otherwise.
   */
  protected static function tokenExists(string $string): bool {
    return (bool) preg_match('/\[[^]]+]/', $string);
  }

}
