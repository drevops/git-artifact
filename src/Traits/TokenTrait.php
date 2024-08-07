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
    /* @phpstan-ignore-next-line */
    return preg_replace_callback('/(?:\[([^\]]+)\])/', function (array $match) {
      if (count($match) > 1) {
        $parts = explode(':', $match[1], 2);
        $token = $parts[0] ?? NULL;
        $argument = $parts[1] ?? NULL;
        if ($token) {
          $method = 'getToken' . ucfirst($token);
          if (method_exists($this, $method)) {
            /* @phpstan-ignore-next-line */
            $match[0] = call_user_func([$this, $method], $argument);
          }
        }
      }

      return $match[0];
    }, $string);
  }

  /**
   * Check if the string has at least one token.
   *
   * @param string $string
   *   String to check.
   *
   * @return bool
   *   True if there is at least one token present, false otherwise.
   */
  protected function hasToken(string $string): bool {
    return (bool) preg_match('/\[[^]]+]/', $string);
  }

}
