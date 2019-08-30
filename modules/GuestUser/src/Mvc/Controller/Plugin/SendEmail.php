<?php
namespace GuestUser\Mvc\Controller\Plugin;

use GuestUser\Stdlib\PsrMessage;
use Omeka\Stdlib\Mailer as MailerService;
use Zend\Log\Logger;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Send an email.
 */
class SendEmail extends AbstractPlugin
{
    /**
     * @var MailerService
     */
    protected $mailer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param MailerService $mailer
     * @param Logger $logger
     */
    public function __construct(MailerService $mailer, Logger $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Send an email to a recipient (no check is done), and get response.
     *
     * @param string $recipient
     * @param string $subject
     * @param string $body
     * @param string $name
     * @return bool|string True, or a message in case of error.
     */
    public function __invoke($recipient, $subject, $body, $name = null)
    {
        $recipient = trim($recipient);
        if (empty($recipient)) {
            return new PsrMessage('The message has no recipient.'); // @translate
        }
        $subject = trim($subject);
        if (empty($subject)) {
            return new PsrMessage('The message has no subject.'); // @translate
        }
        $body = trim($body);
        if (empty($body)) {
            return new PsrMessage('The message has no content.'); // @translate
        }

        $mailer = $this->mailer;
        $message = $mailer->createMessage();

        $isHtml = strpos($body, '<') === 0;
        if ($isHtml) {
            // Full html.
            if (strpos($body, '<!DOCTYPE') === 0 || strpos($body, '<html') === 0) {
                $boundary = substr(str_replace(['+', '/', '='], '', base64_encode(uniqid() . uniqid())), 0, 20);
                $message->getHeaders()
                    ->addHeaderLine('MIME-Version: 1.0')
                    ->addHeaderLine('Content-Type: multipart/alternative; boundary=' . $boundary);
                $raw = strip_tags($body);
                $body = <<<BODY
--$boundary
Content-Transfer-Encoding: quoted-printable
Content-Type: text/html; charset=UTF-8
MIME-Version: 1.0

$raw

--$boundary
Content-Transfer-Encoding: quoted-printable
Content-Type: text/html; charset=UTF-8
MIME-Version: 1.0

$body

--$boundary--
BODY;
            }
            // Partial html.
            else {
                $message->getHeaders()
                    ->addHeaderLine('MIME-Version: 1.0')
                    ->addHeaderLine('Content-Type: text/html; charset=UTF-8');
            }
        }

        $message
            ->addTo($recipient, $name)
            ->setSubject($subject)
            ->setBody($body);

        try {
            $mailer->send($message);
            // Log email sent for security purpose.
            $msg = new PsrMessage(
                'A mail was sent to {email} with subject {subject}', // @translate
                ['email' => $recipient, 'subject' => $subject]
            );
            $this->logger->info($msg);
            return true;
        } catch (\Exception $e) {
            $this->logger->err((string) $e);
            return (string) $e;
        }
    }
}
