<?php
namespace DownloadManager\Job;

use DownloadManager\Entity\Download;
use Omeka\Job\AbstractJob;

class PrepareUserDownloads extends AbstractJob
{
    const SQL_LIMIT = 20;

    protected $mediaTypes = [
        'application/pdf',
    ];

    public function perform()
    {
        // $jobId = $this->getArg('jobId');
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $userId = $this->getArg('user_id');

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
            }

            $offset += self::SQL_LIMIT;
        }
    }
}
