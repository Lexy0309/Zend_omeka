<?php

namespace DownloadManager\Api\Representation;

use DateTime;
use DownloadManager\Entity\Download;
use Omeka\Api\Representation\AbstractEntityRepresentation;

class DownloadRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var array
     */
    protected $statusLabels = [
        Download::STATUS_READY => 'Ready', // @translate
        Download::STATUS_HELD => 'Held', // @translate
        Download::STATUS_DOWNLOADED => 'Downloaded', // @translate
        Download::STATUS_PAST => 'Past', // @translate
    ];

    public function getControllerName()
    {
        return 'download-manager';
    }

    public function getJsonLdType()
    {
        return 'o-module-access:Download';
    }

    public function getJsonLd()
    {
        $resource = $this->resource();
        if ($resource) {
            $resource = $resource->getReference();
        }

        $owner = $this->owner();
        if ($owner) {
            $owner = $owner->getReference();
        }

        $expire = $this->expire();
        if ($expire) {
            $expire = [
                '@value' => $this->getDateTime($expire),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ];
        }

        $created = [
            '@value' => $this->getDateTime($this->created()),
            '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        ];

        $modified = $this->modified();
        if ($modified) {
            $modified = [
                '@value' => $this->getDateTime($modified),
                '@type' => 'http://www.w3.org/2001/XMLSchema#dateTime',
            ];
        }

        // TODO Describe parameters of the download (@id, not only o:id, etc.)?

        return [
            'o:id' => $this->id(),
            'o:status' => $this->status(),
            'o:resource' => $resource,
            'o:owner' => $owner,
            'o-module-access:expire' => $expire,
            'o-module-access:log' => $this->log(),
            // 'o-module-access:hash' => $this->hash(),
            // 'o-module-access:hash_password' => $this->hashPassword(),
            // 'o-module-access:salt' => $this->salt(),
            'o:created' => $created,
            'o:modified' => $modified,
        ];
    }

    public function status()
    {
        return $this->resource->getStatus();
    }

    public function statusLabel()
    {
        $status = $this->resource->getStatus();
        // May avoid a notice.
        return isset($this->statusLabels[$status])
            ? $this->statusLabels[$status]
            : 'Undefined'; // @translate
    }

    public function resource()
    {
        // TODO Check if true resource (normally always).
        return $this->getAdapter('resources')
            ->getRepresentation($this->resource->getResource());
    }

    public function owner()
    {
        return $this->getAdapter('users')
            ->getRepresentation($this->resource->getOwner());
    }

    public function expire()
    {
        return $this->resource->getExpire();
    }

    public function isReady()
    {
        return $this->status() === Download::STATUS_READY;
    }

    public function isHeld()
    {
        return $this->status() === Download::STATUS_HELD;
    }

    public function isDownloaded()
    {
        return $this->status() === Download::STATUS_DOWNLOADED;
    }

    public function isPast()
    {
        return $this->status() === Download::STATUS_PAST;
    }

    public function isExpiring()
    {
        if ($this->isPast()) {
            return true;
        }
        if (!$this->isDownloaded()) {
            return false;
        }
        $expire = $this->expire();
        $result = !empty($expire) && $expire < new DateTime('now');
        if ($result) {
            $services = $this->getServiceLocator();
            $controllerPlugins = $services->get('ControllerPluginManager');

            // Clean file first.
            $ownerEntity = $this->getAdapter('users')->findEntity($this->owner()->id());
            $removeAccessFiles = $controllerPlugins->get('removeAccessFiles');
            $removeAccessFiles($ownerEntity, $this->resource()->primaryMedia());

            // Clean data.
            $this->resource->setStatus(Download::STATUS_PAST);
            $log = $this->log() ?: [];
            $log[] = ['action' => 'expired', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
            $this->resource->setLog($log);
            $entityManager = $services->get('Omeka\EntityManager');
            $entityManager->persist($this->resource);
            $entityManager->flush();

            // Launch a background job to warn the first user in the list.
            $dispatcher = $services->get('Omeka\Job\Dispatcher');
            $jobArgs = ['downloadId' => $this->id()];
            $dispatcher->dispatch(\DownloadManager\Job\NotificationAvailability::class, $jobArgs);
        }
        return $result;
    }

    public function holdingRank()
    {
        $holdingRank = $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('holdingRank');
        return $holdingRank($this);
    }

    public function log()
    {
        return $this->resource->getLog();
    }

    public function hash()
    {
        return $this->resource->getHash();
    }

    public function hashPassword()
    {
        return $this->resource->getHashPassword();
    }

    public function salt()
    {
        return $this->resource->getSalt();
    }

    public function created()
    {
        return $this->resource->getCreated();
    }

    public function modified()
    {
        return $this->resource->getModified();
    }
}
