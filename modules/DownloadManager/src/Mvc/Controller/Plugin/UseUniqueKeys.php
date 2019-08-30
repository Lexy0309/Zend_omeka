<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class UseUniqueKeys extends AbstractPlugin
{
    /**
     * @var array
     */
    protected $uniqueKeys;

    /**
     * @var UserApiKeys
     */
    protected $userApiKeys;

    /**
     * @param array $uniqueKeys
     * @param UserApiKeys $userApiKeys
     */
    public function __construct($uniqueKeys, UserApiKeys $userApiKeys)
    {
        $this->uniqueKeys = $uniqueKeys;
        $this->userApiKeys = $userApiKeys;
    }

    /**
     * Check if a user uses uinque keys or specific keys.
     *
     * WARNING
     * Now, all users have unique keys. So it always returns true. The plugin is
     * kept to simplify upgrade.
     *
     * @todo Find a quicker way to check if the user has unique keys (or store it).
     *
     * @param User $user
     * @return bool
     */
    public function __invoke(User $user)
    {
        return true;

        static $users = [];

        if (empty($this->uniqueKeys)) {
            return true;
        }

        if (!isset($users[$user->getId()])) {
            $userApiKeys = $this->userApiKeys;
            $userKeys = $userApiKeys($user);
            $users[$user->getId()] = $userKeys[UserApiKeys::LABEL_USER_KEY]['key_credential'] === $this->uniqueKeys[UserApiKeys::LABEL_USER_KEY]
                && $userKeys[UserApiKeys::LABEL_SERVER_KEY]['key_credential'] === $this->uniqueKeys[UserApiKeys::LABEL_SERVER_KEY];
        }

        return $users[$user->getId()];
    }
}
