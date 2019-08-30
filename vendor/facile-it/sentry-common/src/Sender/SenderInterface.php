<?php

declare(strict_types=1);

namespace Facile\Sentry\Common\Sender;

/**
 * Interface SenderInterface
 */
interface SenderInterface
{
    /**
     * Send the log
     *
     * @param string $priority Sentry priority
     * @param string $message Log Message
     * @param array $context Log context
     */
    public function send(string $priority, string $message, array $context = []);
}
