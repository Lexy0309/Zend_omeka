<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\CreateUserKey;
use DownloadManager\Mvc\Controller\Plugin\ApiKeyFromUserAndLabel;
use Omeka\Entity\User;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get the user key.
 */
class ReadUserKey extends AbstractHelper
{
    /**
     * @var CreateUserKey
     */
    protected $createUserKey;

    /**
     * @param ApiKeyFromUserAndLabel $apiKeyFromUserAndLabel
     */
    public function __construct(CreateUserKey $createUserKey)
    {
        $this->createUserKey = $createUserKey;
    }

    /**
     * Read the user key.
     *
     * @param User $user
     * @return string|null
     */
    public function __invoke(User $user = null)
    {
        $createUserKey = $this->createUserKey;
        return $createUserKey($user);
    }
}
