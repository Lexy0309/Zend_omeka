<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\Sender;

use Facile\Sentry\Common\Sanitizer\Sanitizer;
use Facile\Sentry\Common\Sanitizer\SanitizerInterface;
use Facile\Sentry\Common\StackTrace\StackTrace;
use Facile\Sentry\Common\StackTrace\StackTraceInterface;
use Raven_Client;

/**
 * Class Sender
 */
final class Sender implements SenderInterface
{
    /**
     * @var Raven_Client
     */
    private $client;
    /**
     * @var SanitizerInterface
     */
    private $sanitizer;
    /**
     * @var StackTraceInterface
     */
    private $stackTrace;

    /**
     * Sender constructor.
     * @param Raven_Client $client
     * @param SanitizerInterface $sanitizer
     * @param StackTraceInterface $stackTrace
     */
    public function __construct(
        Raven_Client $client,
        SanitizerInterface $sanitizer = null,
        StackTraceInterface $stackTrace = null
    ) {
        $this->client = $client;
        $this->sanitizer = $sanitizer ?: new Sanitizer();
        $this->stackTrace = $stackTrace ?: new StackTrace($client);

        $this->stackTrace->addIgnoreBacktraceNamespace(__NAMESPACE__);
    }

    /**
     * @return Raven_Client
     */
    public function getClient(): Raven_Client
    {
        return $this->client;
    }

    /**
     * @return SanitizerInterface
     */
    public function getSanitizer(): SanitizerInterface
    {
        return $this->sanitizer;
    }

    /**
     * @return StackTraceInterface
     */
    public function getStackTrace(): StackTraceInterface
    {
        return $this->stackTrace;
    }

    /**
     * Send the log
     *
     * @param string $priority Sentry priority
     * @param string $message Log Message
     * @param array $context Log context
     */
    public function send(string $priority, string $message, array $context = [])
    {
        if (! $this->contextContainsException($context)) {
            $data = [
                'extra' => $this->sanitizer->sanitize($context),
                'level' => $priority,
            ];

            $this->client->captureMessage(
                $message,
                [],
                $data,
                $this->stackTrace->cleanBacktrace(debug_backtrace())
            );

            return;
        }

        /** @var \Exception $exception */
        $exception = $context['exception'];
        unset($context['exception']);

        $exceptionsStack = $this->stackTrace->getExceptions($exception);

        // Adding a first fake exception to log the message
        $stack = $this->stackTrace->cleanBacktrace(debug_backtrace());
        $exceptionsStack[] = [
            'value' => $message,
            'type' => get_class($exception),
            'stacktrace' => [
                'frames' => $this->stackTrace->getStackTraceFrames($stack),
            ],
        ];

        $data = [
            'extra' => $this->sanitizer->sanitize($context),
            'level' => $priority,
            'exception' => [
                'values' => $exceptionsStack,
            ],
        ];

        $this->client->captureMessage($message, [], $data);
    }

    /**
     * @param array $context
     *
     * @return bool
     */
    private function contextContainsException(array $context): bool
    {
        if (! array_key_exists('exception', $context)) {
            return false;
        }

        return $context['exception'] instanceof \Throwable;
    }
}
