<?php
namespace DownloadManager\Job;

use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

class NotificationAvailability extends AbstractJob
{
    public function perform()
    {
        // $jobId = $this->getArg('jobId');
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $holdingRanksItemPlugin = $controllerPluginManager->get('holdingRanksItem');

        /** @var \DownloadManager\Api\Representation\DownloadRepresentation $download */
        $download = $api->read('downloads', $this->getArg('downloadId'))->getContent();
        $resource = $download->resource();

        /** @var \DownloadManager\Entity\Download[] $ranks */
        $ranks = $holdingRanksItemPlugin($resource, 1);
        if (empty($ranks)) {
            return;
        }

        $download = reset($ranks);
        /** @var \DownloadManager\Api\Representation\DownloadRepresentation $download */
        $download = $api->read('downloads', $download->getId())->getContent();
        $owner = $download->owner();

        $siteOfUserPlugin = $controllerPluginManager->get('siteOfUser');
        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $siteOfUserPlugin($owner);

        $settings = $services->get('Omeka\Settings');

        $subject = new PsrMessage(
            $settings->get('downloadmanager_notification_availability_subject'),
            ['site_name' => $site->title()]
        );

        $message = new PsrMessage(
            $settings->get('downloadmanager_notification_availability_message'),
            [
                'user_name' => $owner->name(),
                'resource_title' => $resource->displayTitle(),
                'resource_link' => $resource->siteUrl($site->slug(), true),
                'site_name' => $site->title(),
                'site_link' => $site->siteUrl($site->slug(), true),
            ]
        );

        // Update log.
        $log = $download->log() ?: [];
        $log[] = ['action' => 'notified availability', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
        $data = [];
        $data['o-module-access:log'] = $log;
        $api
            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);

        $recipient = $owner->email();
        return $this->notifyEmail($message, $subject, $recipient);
    }

    /**
     * Notify a message to a recipient.
     *
     * @param string $message
     * @param string $subject
     * @param string $recipient
     */
    protected function notifyEmail($message, $subject, $recipient)
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Stdlib\Mailer $mailer */
        $mailer = $services->get('ControllerPluginManager')->get('mailer');
        $mailer = $mailer();

        $body = $message . "\r\n\r\n";

        $message = $mailer->createMessage();
        $message
            ->setSubject($subject)
            ->setBody($body)
            ->addTo($recipient);

        $mailer->send($message);
    }
}
