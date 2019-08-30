<?php
namespace VatiruLibrary\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Settings\Settings;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the site of the current user, or the default one.
 */
class SiteOfCurrentUser extends AbstractPlugin
{
    /**
     * @var AuthenticationService
     */
    protected $auth;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @param AuthenticationService $auth
     * @param EntityManager $entityManager
     * @param Settings $settings
     */
    public function __construct(AuthenticationService $auth, EntityManager $entityManager, Settings $settings)
    {
        $this->auth = $auth;
        $this->entityManager = $entityManager;
        $this->settings = $settings;
    }

    /**
     * Helper to get the site of the current user if any.
     *
     * @param bool $returnDefaultSite Should be set in main settings of Omeka S.
     * @return \Omeka\Entity\Site|null
     */
    public function __invoke($returnDefaultSite = true)
    {
        if ($this->auth->hasIdentity()) {
            $user = $this->auth->getIdentity();
            /** @var \Omeka\Entity\SitePermission[] $sitePermissionEntities */
            $sitePermissionEntities = $this->entityManager
                ->getRepository(\Omeka\Entity\SitePermission::class)
                ->findBy(['user' => $user->getId()], ['id' => 'asc'], 1);
            if ($sitePermissionEntities) {
                return $sitePermissionEntities[0]->getSite();
            }
        }

        if ($returnDefaultSite) {
            $siteId = $this->settings->get('default_site');
            if ($siteId) {
                $site = $this->entityManager
                    ->getRepository(\Omeka\Entity\Site::class)
                    ->find(['id' => $siteId]);
                if ($site) {
                    return $site;
                }
            }

            $sites = $this->entityManager
                ->getRepository(\Omeka\Entity\Site::class)
                ->findBy([], ['id' => 'asc'], 1);
            if ($sites) {
                return $sites[0];
            }
        }

        return null;
    }
}
