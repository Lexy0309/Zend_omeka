<?php

namespace DownloadManager\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class DownloadLogRepresentation extends AbstractEntityRepresentation
{
    /**
     * {@inheritdoc}
     *
     * Note: Currently, it uses the same controller than Download.
     */
    public function getControllerName()
    {
        return 'download';
    }

    public function getJsonLdType()
    {
        return 'o-module-access:DownloadLog';
    }

    public function getJsonLd()
    {
        // TODO Check if the resource and the owner are still available.

        $resource = $this->resourceId();
        $owner = $this->ownerId();

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

    public function resourceId()
    {
        return $this->resource->getResourceIdentifier();
    }

    public function ownerId()
    {
        return $this->resource->getOwnerId();
    }

    public function expire()
    {
        return $this->resource->getExpire();
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
