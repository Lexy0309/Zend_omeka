<?php
namespace VatiruLibrary\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityRepository;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the site of a user, or the default one.
 */
class SiteOfUserEntity extends AbstractPlugin
{
    /**
     * @var EntityRepository
     */
    protected $sitePermissionRepository;

    /**
     * @param EntityRepository $sitePermissionRepository
     */
    public function __construct(EntityRepository $sitePermissionRepository)
    {
        $this->sitePermissionRepository = $sitePermissionRepository;
    }

    /**
     * Helper to get the site of an user if any.
     *
     * @param User $user
     * @return \Omeka\Entity\Site|null
     */
    public function __invoke(User $user)
    {
        /** @var \Omeka\Entity\SitePermission[] $sitePermissionEntities */
        $sitePermissionEntities = $this->sitePermissionRepository
            ->findBy(['user' => $user->getId()], ['id' => 'asc'], 1);
        return $sitePermissionEntities
            ? $sitePermissionEntities[0]->getSite()
            : null;
    }
}
