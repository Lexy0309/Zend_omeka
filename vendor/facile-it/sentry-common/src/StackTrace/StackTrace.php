<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\StackTrace;

use Raven_Client;
use Raven_Serializer;
use Raven_ReprSerializer;

class StackTrace implements StackTraceInterface
{
    /**
     * @var Raven_Client
     */
    private $client;
    /**
     * @var Raven_Serializer
     */
    private $serializer;

    /**
     * @var Raven_ReprSerializer
     */
    private $reprSerializer;
    /**
     * @var string[]
     */
    private $ignoreBacktraceNamespaces;

    /**
     * StackTrace constructor.
     *
     * @param Raven_Client $client
     * @param Raven_Serializer $serializer
     * @param Raven_ReprSerializer $reprSerializer
     * @param array $ignoreBacktraceNamespaces
     */
    public function __construct(
        Raven_Client $client,
        Raven_Serializer $serializer = null,
        Raven_ReprSerializer $reprSerializer = null,
        array $ignoreBacktraceNamespaces = []
    ) {
        $this->client = $client;
        $this->serializer = $serializer ?: new Raven_Serializer();
        $this->reprSerializer = $reprSerializer;
        $this->ignoreBacktraceNamespaces = $ignoreBacktraceNamespaces;
        $this->addIgnoreBacktraceNamespace(__NAMESPACE__);
    }

    /**
     * @param string $namespace
     */
    public function addIgnoreBacktraceNamespace(string $namespace)
    {
        $this->ignoreBacktraceNamespaces[] = $namespace;
    }

    /**
     * Generate exceptions data for Sentry
     *
     * @param \Throwable $exception
     * @return array
     */
    public function getExceptions(\Throwable $exception): array
    {
        $exc = $exception;
        do {
            $exc_data = [
                'value' => $this->serializer->serialize($exc->getMessage()),
                'type' => get_class($exc),
            ];

            /**'exception'
             * Exception::getTrace doesn't store the point at where the exception
             * was thrown, so we have to stuff it in ourselves. Ugh.
             */
            $trace = $exc->getTrace();
            $frame_where_exception_thrown = [
                'file' => $exc->getFile(),
                'line' => $exc->getLine(),
            ];

            array_unshift($trace, $frame_where_exception_thrown);

            $exc_data['stacktrace'] = [
                'frames' => $this->getStackTraceFrames($trace),
            ];

            $exceptions[] = $exc_data;
        } while ($exc = $exc->getPrevious());

        return array_reverse($exceptions);
    }

    /**
     * Generate StackTrace frames for Sentry
     *
     * @param array $trace
     * @return array
     */
    public function getStackTraceFrames(array $trace): array
    {
        return \Raven_Stacktrace::get_stack_info(
            $trace,
            $this->client->trace,
            [],
            $this->client->message_limit,
            $this->client->getPrefixes(),
            $this->client->getAppPath(),
            $this->client->getExcludedAppPaths(),
            $this->serializer,
            $this->reprSerializer
        );
    }

    /**
     * Remove stacks until the stack is from outside the logging context based on namespaces to ignore.
     *
     * @param array $trace
     * @return array
     */
    public function cleanBacktrace(array $trace): array
    {
        // find first not removable index
        $firstNotRemovable = 0;
        foreach ($trace as $index => $item) {
            if (! $this->shouldRemoveStack($item)) {
                $firstNotRemovable = $index;
                break;
            }
        }

        $trace = array_slice($trace, $firstNotRemovable ?: 0);

        return $trace;
    }

    /**
     * Should the stack be removed?
     *
     * @param array $stack
     * @return bool
     */
    private function shouldRemoveStack(array $stack): bool
    {
        if (empty($stack['class'])) {
            return false;
        }

        foreach ($this->ignoreBacktraceNamespaces as $namespace) {
            if (0 === strpos($stack['class'], $namespace)) {
                return true;
            }
        }

        return false;
    }
}
