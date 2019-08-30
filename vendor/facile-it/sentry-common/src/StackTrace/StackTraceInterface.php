<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\StackTrace;

interface StackTraceInterface
{
    /**
     * Add namespace to ignore during cleanBacktrace() method.
     *
     * @deprecated
     * @param string $namespace
     */
    public function addIgnoreBacktraceNamespace(string $namespace);

    /**
     * Generate exceptions data for Sentry
     *
     * @param \Throwable $exception
     * @return array
     */
    public function getExceptions(\Throwable $exception): array;

    /**
     * Generate StackTrace frames for Sentry
     *
     * @param array $trace
     * @return array
     */
    public function getStackTraceFrames(array $trace): array;

    /**
     * Remove stacks until the stack is from outside the logging context based on namespaces to ignore.
     *
     * @param array $trace
     * @return array
     */
    public function cleanBacktrace(array $trace): array;
}
