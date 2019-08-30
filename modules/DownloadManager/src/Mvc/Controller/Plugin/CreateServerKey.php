<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Api\Representation\DownloadRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class CreateServerKey extends AbstractPlugin
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
     * Create a server key for a file and a user, using the unique server key
     * if no download is provided.
     *
     * The server key may be the unique one, if set, but we cannot know it here.
     *@todo Add a true server master key to this key? It must be stable.
     *
     * @param DownloadRepresentation $download
     * @return string|null
     */
    public function __invoke(DownloadRepresentation $download = null)
    {
        if ($download) {
            $api = $this->plugins->get('api');
            $user = $api->read('users', $download->owner()->id(), [], ['responseContent' => 'resource'])->getContent();
            $apiKeyFromUserAndLabel = $this->plugins->get('apiKeyFromUserAndLabel');
            $apiKey = $apiKeyFromUserAndLabel($user, UserApiKeys::LABEL_SERVER_KEY);
            $salt = $download->salt();
        } else {
            $uniqueServerKey = $this->plugins->get('uniqueServerKey');
            $apiKey = $uniqueServerKey();
            $createServerSalt = $this->plugins->get('createServerSalt');
            $salt = $createServerSalt(true);
        }

        if (empty($apiKey) || empty($salt)) {
            return;
        }

        $serverKey = hash('sha256', $apiKey . '/' . $salt);
        return $serverKey;
    }
}
