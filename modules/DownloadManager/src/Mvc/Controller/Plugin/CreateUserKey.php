<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class CreateUserKey extends AbstractPlugin
{
    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param PluginManager $plugins
     */
    public function __construct(PluginManager $plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * Create a user key, using the unique user key if no user is provided.
     *
     * The user key may be the unique one, if set, but we cannot know it here.
     *
     * @param User $user
     * @return string|null
     */
    public function __invoke(User $user = null)
    {
        if ($user) {
            $apiKeyFromUserAndLabel = $this->plugins->get('apiKeyFromUserAndLabel');
            $apiKey = $apiKeyFromUserAndLabel($user, UserApiKeys::LABEL_USER_KEY);
        } else {
            $uniqueUserKey = $this->plugins->get('uniqueUserKey');
            $apiKey = $uniqueUserKey();
        }

        if (empty($apiKey)) {
            return;
        }

        $userKey = hash('sha256', $apiKey);
        return $userKey;
    }
}
