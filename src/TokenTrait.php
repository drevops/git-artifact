<?php

namespace IntegratedExperts\Robo;

/**
 * Trait TokenTrait.
 *
 * @package IntegratedExperts\Robo
 */
trait TokenTrait
{

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
    protected function tokenProcess($string)
    {
        $string = preg_replace_callback('/(?:\[([^\]]+)\])/', function ($match) {
            if (count($match) > 1) {
                $parts = explode(':', $match[1], 2);
                $token = isset($parts[0]) ? $parts[0] : null;
                $argument = isset($parts[1]) ? $parts[1] : null;
                if ($token) {
                    $method = 'getToken'.ucfirst($token);
                    if (method_exists($this, $method)) {
                        $match[0] = call_user_func([$this, $method], $argument);
                    }
                }
            }

            return $match[0];
        }, $string);

        return $string;
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
    protected function hasToken($string)
    {
        return (bool) preg_match('/\[[^\]]+\]/', $string);
    }
}
