<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\Sanitizer;

interface SanitizerInterface
{
    /**
     * Sanitize recursively a value
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function sanitize($value);
}
