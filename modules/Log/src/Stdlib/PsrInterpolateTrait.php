<?php

namespace Log\Stdlib;

/**
 * Interpolate a PSR-3 message with a context into a string.
 */
trait PsrInterpolateTrait
{
    /**
     * Interpolates context values into the PSR-3 message placeholders.
     *
     * Keys that are not stringable are kept as class or type.
     *
     * @param string $message Message with PSR-3 placeholders.
     * @param array $context Associative array with placeholders and strings.
     * @return string
     */
    public function interpolate($message, array $context = null)
    {
        $message = (string) $message;
        if (strpos($message, '{') === false) {
            return $message;
        }

        if (empty($context)) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $val) {
            if (is_null($val)
                || is_scalar($val)
                || (is_object($val) && method_exists($val, '__toString'))
            ) {
                $replacements['{' . $key . '}'] = $val;
                continue;
            }

            if (is_object($val)) {
                $replacements['{' . $key . '}'] = '[object ' . get_class($val) . ']';
                continue;
            }

            $replacements['{' . $key . '}'] = '[' . gettype($val) . ']';
        }

        return strtr($message, $replacements);
    }
}
