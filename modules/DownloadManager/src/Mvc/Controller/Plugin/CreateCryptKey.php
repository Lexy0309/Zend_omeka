<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

class createCryptKey extends AbstractPlugin
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
     * Create the password used to encrypt a file.
     *
     * @param MediaRepresentation $media
     * @param User|null $user
     * @return string|null
     */
    public function __invoke(MediaRepresentation $media, User $user = null)
    {
        // TODO Keys are unique now. Anyway, since download are not created automatically, it is not possible to create a user key.
        // $keys = empty($user) ? $this->uniqueKeys($media): $this->userKeys($media, $user);
        $keys = $this->uniqueKeys($media);
        if (empty($keys)) {
            return;
        }

        // TODO Convert to a full alphanumeric string, not only hexadecimal. See determineAccessPath.
        $password = md5(implode('/', $keys));

        // The password should be 4 to 32 character (pdf limit).
        $password = substr($password, 0, 32);
        return $password;
    }

    /**
     * Return the unique user, server and document keys for a media.
     *
     * @param MediaRepresentation $media
     * @return array|null
     */
    protected function uniqueKeys(MediaRepresentation $media)
    {
        $plugins = $this->plugins;

        $hasUniqueKeys = $plugins->get('hasUniqueKeys');
        $hasUniqueKeys = $hasUniqueKeys();
        if (!$hasUniqueKeys) {
            return;
        }

        $createDocumentKey = $plugins->get('createDocumentKey');
        $documentKey = $createDocumentKey($media);
        if (empty($documentKey)) {
            return;
        }

        $createUserKey = $plugins->get('createUserKey');
        $userKey = $createUserKey();
        if (empty($userKey)) {
            return;
        }

        $createServerKey = $plugins->get('createServerKey');
        $serverKey = $createServerKey();
        if (empty($serverKey)) {
            return;
        }

        return [
            'user_key' => $userKey,
            'document_key' => $documentKey,
            'server_key' => $serverKey,
        ];
    }

    /**
     * Return the user, server and document keys for a media and a user.
     *
     * @param MediaRepresentation $media
     * @param User $user
     * @return array|null
     */
    protected function userKeys(MediaRepresentation $media, User $user)
    {
        $plugins = $this->plugins;

        $getCurrentDownload = $plugins->get('getCurrentDownload');
        $download = $getCurrentDownload($media->item(), $user);
        if (empty($download)) {
            return;
        }

        // Check if keys exists and create them if needed.
        $apiKeyFromUserAndLabel = $plugins->get('apiKeyFromUserAndLabel');
        $apiUserKey = $apiKeyFromUserAndLabel($user, UserApiKeys::LABEL_USER_KEY);
        $apiServerKey = $apiKeyFromUserAndLabel($user, UserApiKeys::LABEL_SERVER_KEY);
        if (empty($apiUserKey) || empty($apiServerKey)) {
            $userApiKeys = $plugins->get('userApiKeys');
            $keys = $userApiKeys($user);
            if (empty($keys)) {
                return;
            }
        }

        $createDocumentKey = $plugins->get('createDocumentKey');
        $documentKey = $createDocumentKey($media, $download);
        if (empty($documentKey)) {
            return;
        }

        $createUserKey = $plugins->get('createUserKey');
        $userKey = $createUserKey($user);
        if (empty($userKey)) {
            return;
        }

        $createServerKey = $plugins->get('createServerKey');
        $serverKey = $createServerKey($download);
        if (empty($serverKey)) {
            return;
        }

        return [
            'user_key' => $userKey,
            'document_key' => $documentKey,
            'server_key' => $serverKey,
        ];
    }
}
