<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\Sanitizer;

use Traversable;

final class Sanitizer implements SanitizerInterface
{
    /**
     * Sanitize recursively a value
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function sanitize($value)
    {
        if ($value instanceof Traversable) {
            return $this->sanitize(iterator_to_array($value));
        } elseif (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        } elseif (is_object($value)) {
            return $this->sanitizeObject($value);
        } elseif (is_resource($value)) {
            return get_resource_type($value);
        }

        return $value;
    }

    /**
     * @param mixed $object
     * @return string
     */
    private function sanitizeObject($object): string
    {
        if (method_exists($object, '__toString')) {
            return (string) $object;
        }

        return get_class($object);
    }
}
