<?php
namespace GuestUser\Mvc\Controller\Plugin;

use GuestUser\Stdlib\PsrMessage;
use Omeka\Stdlib\Message;
use Zend\Http\Client;
use Zend\Log\Logger;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Send a sms and get response.
 */
class SendSms extends AbstractPlugin
{
    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $baseUrl;


    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Client $httpClient
     * @param string $baseUrl
     * @param Logger $logger
     */
    public function __construct(Client $httpClient, $baseUrl, Logger $logger)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = $baseUrl;
        $this->logger = $logger;
    }

    /**
     * Send file a sms to a recipient (no check is done), and get response.
     *
     * @link https://dev.infobip.com
     *
     * @param string $recipient
     * @param string $message
     * @return array|string The body of response on success, message on error.
     */
    public function __invoke($recipient, $message)
    {
        if (!strlen($message) || empty($recipient)) {
            return;
        }

        $url = $this->baseUrl . '/sms/1/text/single';
        $values = [
            'to' => $recipient,
            'text' => $message,
        ];

        $client = $this->httpClient;
        $client->setUri($url);
        $client->setRawBody(json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        try {
            /** @var \Zend\Http\Response $response */
            $response = $client->send();
        } catch (\Zend\Http\Client\Adapter\Exception\RuntimeException $e) {
            // Don't return internal error to user.
            $this->logger->err(new Message('Runtime exception when sending sms: %s (%s #%d).',
                $e->getMessage(), $e->getFile(), $e->getLine()));
            return 'Runtime error'; // @translate
        }

        if (!$response->isSuccess()) {
            $message = new Message(
                $response->renderStatusLine()
            );
            if ($body = trim($response->getBody())) {
                $message .= PHP_EOL . $body . PHP_EOL;
            }
            $this->logger->err(new Message('Error when sending sms: %s', $message)); // @translate
            return $message;
        }

        // Log sms sent for security purpose.
        $msg = new PsrMessage(
            'A sms was sent to {phone}: {message}', // @translate
            ['phone' => $recipient, 'message' => $message]
        );
        $this->logger->info($msg);

        // @link https://dev.infobip.com/getting-started/response-status-and-error-codes#section-general-status-codes
        $body = json_decode($response->getBody(), true);
        $answer = reset($body['messages']);
        $statusGroupId = $answer['status']['groupId'];
        // 1 is pending, 3 is delivered.
        if (!in_array($statusGroupId, [1, 3])) {
            $message = sprintf(
                'Error %s: %s',
                $answer['status']['name'],
                $answer['status']['description']
            );
            return $message;
        }

        return $answer;
    }
}
