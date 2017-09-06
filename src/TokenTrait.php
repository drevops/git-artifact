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
            if (count($match) < 2) {
                return $match;
            }
            $abc = $match[1];
            $parts = explode(':', $abc, 2);
            $token = isset($parts[0]) ? $parts[0] : null;
            $argument = isset($parts[1]) ? $parts[1] : null;
            if ($token) {
                $method = 'getToken'.ucfirst($token);
                if (method_exists($this, $method)) {
                    $match = call_user_func([$this, $method], $argument);
                }
            }

            return $match;
        }, $string);

        return $string;
    }
}
