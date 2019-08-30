<?php
namespace DownloadManager\Job;

use DownloadManager\Entity\Download;
use Omeka\Job\AbstractJob;

class PrepareMediaDownloads extends AbstractJob
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
        $plugins = $services->get('ControllerPluginManager');
        $userApiKeys = $plugins->get('userApiKeys');

        $mediaId = $this->getArg('media_id');
        /** @var \Omeka\Api\Representation\MediaRepresentation $media */
        $media = $api->read('media', $mediaId)->getContent();
        if (!$media->hasOriginal()) {
            return;
        }

        if (!in_array($media->mediaType(), $this->mediaTypes)) {
            return;
        }

        $item = $media->item();
        $primaryMedia = $item->primaryMedia();
        if ($primaryMedia->id() !== $media->id()) {
            return;
        }

        $itemId = $item()->id();

        $offset = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\UserRepresentation[] $users */
            $users = $api->search('users', [
                'limit' => self::SQL_LIMIT,
                'offset' => $offset,
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
            }

            $offset += self::SQL_LIMIT;
        }
    }
}
