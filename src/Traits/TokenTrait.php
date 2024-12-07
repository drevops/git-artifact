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
   * @return string
   *   String with replaced tokens if replacements are available or
   *   original string.
   */
  protected function tokenProcess(string $string): string {
    $processed = preg_replace_callback('/(?:\[([^\]]+)\])/', function (array $match): string {
      $replacement = strval($match[0]);

      if (!empty($match[1])) {
        $parts = explode(':', $match[1], 2);

        $token = $parts[0];
        $argument = $parts[1] ?? NULL;

        if ($token !== '' && $token !== '0') {
          $method = 'getToken' . ucfirst($token);

          if (method_exists($this, $method) && is_callable([$this, $method])) {
            $replacement = (string) $this->$method($argument);
          }
        }
      }

      return $replacement;
    }, $string);

    return $processed ?? $string;
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
