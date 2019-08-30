<?php
namespace DownloadManager\Job;

use DownloadManager\Entity\Download;
use Log\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

class PrepareDownloads extends AbstractJob
{
    const SQL_LIMIT = 20;

    protected $mediaTypes = [
        'application/pdf',
    ];

    public function perform()
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $userApiKeys = $plugins->get('userApiKeys');

        $totalItems = 0;
        $totalDownloads = 0;

        $offset = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\ItemRepresentation[] $items */
            $items = $api->search('items', [
                'limit' => self::SQL_LIMIT,
                'offset' => $offset,
                // // Start from the newest, because it is displayed first.
                // // No.
                // 'sort_by' => 'id',
                // 'sort_order' => 'DESC',
            ])->getContent();
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $media = $item->primaryMedia();
                if (empty($media)) {
                    continue;
                }

                if (!$media->hasOriginal()) {
                    continue;
                }

                if (!in_array($media->mediaType(), $this->mediaTypes)) {
                    continue;
                }

                $itemId = $item->id();

                ++$totalItems;

                $offsetUsers = 0;
                while (true) {
                    /** @var \Omeka\Api\Representation\UserRepresentation[] $users */
                    $users = $api->search('users', [
                        'limit' => self::SQL_LIMIT,
                        'offset' => $offsetUsers,
                    ])->getContent();
                    if (empty($users)) {
                        break;
                    }

                    foreach ($users as $user) {
                        // Check/create api keys of the user.
                        /** @var \Omeka\Entity\User $userEntity */
                        $userEntity = $user->getEntity();
                        $userApiKeys($userEntity);

                        $userId = $user->id();

                        $totalDownloads = $api->search('downloads', [
                            'resource_id' => $itemId,
                            'owner_id' => $userId,
                            'limit' => 1,
                        ])->getTotalResults();
                        if ($totalDownloads) {
                            continue;
                        }

                        $data = [];
                        $data['o:status'] = Download::STATUS_READY;
                        $data['o:resource']['o:id'] = $itemId;
                        $data['o:owner']['o:id'] = $userId;
                        $api->create('downloads', $data);

                        ++$totalDownloads;
                    }

                    $offsetUsers += self::SQL_LIMIT;
                }
            }

            $offset += self::SQL_LIMIT;
        }

        $logger->info(new PsrMessage(
            '{total_downloads} downloads have been prepared for {total_items} items.', // @translate
            ['total_downloads' => $totalDownloads, 'total_items' => $totalItems]
        ));
    }
}
