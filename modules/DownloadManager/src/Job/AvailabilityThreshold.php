<?php
namespace DownloadManager\Job;

use Log\Stdlib\PsrMessage;
use Omeka\Entity\Item;
use Omeka\Entity\User;
use Omeka\Job\AbstractJob;
use DownloadManager\Entity\Download;

class AvailabilityThreshold extends AbstractJob
{
    /**
     * @var User[]
     */
    protected $recipients;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');
        $connection = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');

        $userIds = $settings->get('downloadmanager_report_recipients');
        if (empty($userIds)) {
            $logger->info('No recipients set for task {task_name}.', ['task_name' => __CLASS__]);
            return;
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $userRepository = $entityManager->getRepository(\Omeka\Entity\User::class);
        $users = $userRepository->findBy(['id' => $userIds]);
        if (empty($userIds)) {
            $logger->warn('Users set for task {task_name} does not exist.', ['task_name' => __CLASS__]);
            return;
        }
        if (count($users) != count($userIds)) {
            $logger->warn('Some users set for task {task_name} does not exist.', ['task_name' => __CLASS__]);
        }
        $this->recipients = $users;

        $thresholdLimit = $settings->get('downloadmanager_report_threshold_limit');

        // Get all items where the download count is >= threshold % of available
        // exemplars of this item.

        // Two simple queries are used instead of a complex one with percent.

        // First, get the total exemplars for all items, if set.
        $result = $api
            ->search('properties', ['term' => 'download:totalExemplars'])->getContent();
        $property = reset($result);
        $resourceType = Item::class;

        $qb = $connection->createQueryBuilder()
            ->select([
                'resource.id AS id',
                'value.value AS total_exemplars',
                'sum(case when download.status = :downloaded then 1 else 0 end) AS downloaded',
            ])
            ->setParameter('downloaded', Download::STATUS_DOWNLOADED)
            ->from('value', 'value')
            ->innerJoin('value', 'resource', 'resource', 'resource.id = value.resource_id')
            ->innerJoin('resource', 'download', 'download', 'download.resource_id = resource.id')
            ->andWhere('value.property_id = :property_id')
            ->setParameter('property_id', $property->id())
            ->andWhere('resource.resource_type = :resource_type')
            ->setParameter('resource_type', $resourceType)
            ->andWhere('value.value IS NOT NULL')
            ->andWhere('value.value > 0')
            ->having('sum(case when download.status = :downloaded then 1 else 0 end) > 0')
            // Only first value by resource.
            ->groupBy(['value.resource_id'])
            ->addOrderBy('resource.id', 'ASC')
            ->addOrderBy('value.id', 'ASC');
        ;
        $stmt = $connection->executeQuery($qb, $qb->getParameters());
        $result = $stmt->fetchAll();

        // Second, filter by the percent by item.
        $result = array_filter($result, function ($v) use ($thresholdLimit) {
            return ($v['downloaded'] * 100 / $v['total_exemplars']) > $thresholdLimit;
        });

        if (empty($result)) {
            $message = new PsrMessage(
                'No item with threshold ({threshold_limit}%).', // @translate
                ['threshold_limit' => $thresholdLimit]
            );
            $logger->info($message->getMessage(), $message->getContext());
            return $this->notifyEmail($message, 'None');
        }

        // Prepare the report with each resulting values.
        $message = new PsrMessage(
            '{total} simultaneous downloaded items > {threshold_limit}%.', // @translate
            ['total' => count($result), 'threshold_limit' => $thresholdLimit]
        );
        $logger->info($message->getMessage(), $message->getContext());

        $report = [];
        $report[] = $message;

        // TODO Get the server url from the true server.
        $serverUrl = $settings->get('downloadmanager_server_url');

        foreach ($result as $value) {
            /** @var \Omeka\Api\Representation\ItemRepresentation $item */
            $item = $api->read('items', $value['id'])->getContent();
            // $report[] = sprintf('#%s %s: %d/%d (%d%%)',
            //     '<a href="' . $serverUrl . $item->adminUrl('show') . '">' . $item->id() . '</a>',
            //     $item->displayTitle(),
            //     (int) $value['downloaded'],
            //     (int) $value['total_exemplars'],
            //     (int) $value['downloaded'] * 100 / (int) $value['total_exemplars']
            // );
            $report[] = sprintf('#%d (%s) %s: %d/%d (%d%%)',
                $item->id(),
                $serverUrl . $item->adminUrl('show'),
                $item->displayTitle(),
                (int) $value['downloaded'],
                (int) $value['total_exemplars'],
                (int) $value['downloaded'] * 100 / (int) $value['total_exemplars']
            );
        }
        $report = implode("\n\r", $report);

        return $this->notifyEmail($report, count($result));
    }

    /**
     * Notify the report by email.
     *
     * @param string $report
     * @param string $appendToSubject
     */
    protected function notifyEmail($report, $appendToSubject = '')
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        /** @var \Omeka\Stdlib\Mailer $mailer */
        $mailer = $services->get('ControllerPluginManager')->get('mailer');
        $mailer = $mailer();

        $subject = new PsrMessage(
            '[{site}] Threshold report ({append})', // @translate
            ['site' => $settings->get('installation_title'), 'append' => $appendToSubject]
        );

        $body = $report;
        $body .= "\r\n\r\n";

        $message = $mailer->createMessage();
        foreach ($this->recipients as $user) {
            $message->addTo($user->getEmail(), $user->getName());
        }
        $message
            ->setSubject($subject)
            ->setBody($body);
        $mailer->send($message);
    }
}
