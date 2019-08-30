<?php
namespace DownloadManager\Entity;

use DateTime;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Omeka\Api\Exception\InvalidArgumentException;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\User;

/**
 * Downloads are events, so one person can hold one resource multiple times.
 *
 * @Entity
 * @Table(
 *      indexes={
 *          @Index(columns={"status"}),
 *          @Index(columns={"resource_id"}),
 *          @Index(columns={"owner_id"}),
 *          @Index(columns={"expire"}),
 *          @Index(columns={"hash"}),
 *          @Index(
 *              name="resource_owner",
 *              columns={"resource_id", "owner_id"}
 *          )
 *      }
 * )
 * @HasLifecycleCallbacks
 */
class Download extends AbstractEntity
{
    // Status "ready" is used to prepare a download with hash and salt, for
    // example for a sample. It is used for unheld too.
    const STATUS_READY = 'ready';
    const STATUS_HELD = 'held';
    const STATUS_DOWNLOADED = 'downloaded';
    const STATUS_PAST = 'past';
    // TODO Add a status for cancelled hold (usefull only for history log)?
    // const STATUS_CANCELLED = 'cancelled';

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * Note: Doctrine doesn't recommand enums.
     * @Column(type="string", length=190)
     */
    protected $status;

    /**
     * @ManyToOne(targetEntity="\Omeka\Entity\Resource")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $resource;

    /**
     * @ManyToOne(targetEntity="\Omeka\Entity\User")
     * @JoinColumn(nullable=false, onDelete="CASCADE")
     */
    protected $owner;

    /**
     * @Column(type="datetime", nullable=true)
     */
    protected $expire;

    /**
     * @Column(type="json_array", nullable=true)
     */
    protected $log;

    /**
     * @Column(type="string", length=64, nullable=true)
     */
    protected $hash;

    /**
     * @Column(type="string", length=64, nullable=true)
     */
    protected $hashPassword;

    /**
     * @Column(type="string", length=64, nullable=true)
     */
    protected $salt;

    /**
     * @Column(type="datetime")
     */
    protected $created;

    /**
     * @Column(type="datetime", nullable=true)
     */
    protected $modified;

    public function getId()
    {
        return $this->id;
    }

    public function setStatus($status)
    {
        if (!in_array($status, [
            self::STATUS_READY,
            self::STATUS_HELD,
            self::STATUS_DOWNLOADED,
            self::STATUS_PAST,
        ])) {
            throw new InvalidArgumentException('Invalid download status.'); // @translate
        }
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setResource($resource)
    {
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setOwner(User $owner)
    {
        $this->owner = $owner;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function setExpire(DateTime $dateTime = null)
    {
        $this->expire = $dateTime;
    }

    public function getExpire()
    {
        return $this->expire;
    }

    public function setLog(array $log)
    {
        $this->log = $log;
    }

    public function getLog()
    {
        return $this->log;
    }

    public function setHash($hash)
    {
        $this->hash = $hash;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function setHashPassword($hashPassword)
    {
        $this->hashPassword = $hashPassword;
    }

    public function getHashPassword()
    {
        return $this->hashPassword;
    }

    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function setCreated(DateTime $dateTime)
    {
        $this->created = $dateTime;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setModified(DateTime $dateTime = null)
    {
        $this->modified = $dateTime;
    }

    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @PrePersist
     */
    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $this->created = new DateTime('now');
    }

    /**
     * @PreUpdate
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs)
    {
        $this->modified = new DateTime('now');
    }
}
