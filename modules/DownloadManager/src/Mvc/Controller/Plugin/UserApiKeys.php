<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use DownloadManager\Entity\Credential;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Zend\Crypt\Password\Bcrypt;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class UserApiKeys extends AbstractPlugin
{
    const LABEL_MAIN_KEY = 'main';
    const LABEL_USER_KEY = 'user_key';
    const LABEL_SERVER_KEY = 'server_key';

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    private $credentialMainKey;

    /**
     * @var array
     */
    private $uniqueKeys;

    /**
     * @param EntityManager $entityManager
     * @param string $credentialMainKey
     * @param string|null $uniqueUserKey
     * @param string|null $uniqueServerKey
     */
    public function __construct(EntityManager $entityManager, $credentialMainKey, $uniqueUserKey = null, $uniqueServerKey = null)
    {
        $this->entityManager = $entityManager;
        $this->credentialMainKey = $credentialMainKey;
        $this->uniqueKeys[self::LABEL_USER_KEY] = $uniqueUserKey;
        $this->uniqueKeys[self::LABEL_SERVER_KEY] = $uniqueServerKey;
    }

    /**
     * Check and get the existing user keys, or prepare new ones.
     *
     * The check is important, because the keys may have be changed manually
     * and be duplicate by label.
     *
     * @param User $user
     * @return array
     */
    public function __invoke(User $user)
    {
        $entityManager = $this->entityManager;
        $apiKeyRepository = $entityManager->getRepository(\Omeka\Entity\ApiKey::class);
        $credentialRepository = $entityManager->getRepository(\DownloadManager\Entity\Credential::class);

        $labels = [self::LABEL_MAIN_KEY, self::LABEL_USER_KEY, self::LABEL_SERVER_KEY];

        $result = [];
        foreach ($labels as $label) {
            $apiKeys = $apiKeyRepository->findBy(['label' => $label, 'owner' => $user]);
            if (count($apiKeys) !== 1) {
                $result = [];
                break;
            }
            $apiKey = reset($apiKeys);
            $credential = $credentialRepository->findOneBy(['apiKey' => $apiKey]);
            if (empty($credential)) {
                $result = [];
                break;
            }
            $credential->setCredentialMainKey($this->credentialMainKey);
            $credential = $credential->getCredential();
            // Manage a possible error during decrypt.
            if (empty($credential)) {
                $result = [];
                break;
            }
            $result[$label]['key_identity'] = $apiKey->getId();
            $result[$label]['key_credential'] = $credential;
        }

        // Fine, so return the keys.
        if (count($result) == 3) {
            return $result;
        }

        // Some keys are not available or there is a duplicate, so remove and
        // recreate them all.

        // Remove all api keys (and the credentials automatically).
        foreach ($labels as $label) {
            $apiKeys = $apiKeyRepository->findBy(['label' => $label, 'owner' => $user]);
            foreach ($apiKeys as $apiKey) {
                $entityManager->remove($apiKey);
            }
        }
        $entityManager->flush();

        // Add a "main" key, a "user_key" and a "server_key".
        $result = [];
        // The process is done in two steps to avoid a persist issue.
        $apiKeys = [];

        // Random keys.
        if (empty($this->uniqueKeys[self::LABEL_USER_KEY]) || empty($this->uniqueKeys[self::LABEL_SERVER_KEY])) {
            foreach ($labels as $label) {
                $result[$label] = $this->createRandomApiKey($label, $user);
            }
            $entityManager->flush();
        }

        // Forced keys.
        else {
            foreach ($labels as $label) {
                $result[$label] = $label === self::LABEL_MAIN_KEY
                    ? $this->createRandomApiKey($label, $user)
                    :  $this->createForcedApiKey($label, $user);
            }
            $entityManager->flush();
        }

        foreach ($labels as $label) {
            $apiKey = $apiKeyRepository->findOneBy(['label' => $label, 'owner' => $user]);
            $credential = new Credential();
            $credential->setCredentialMainKey($this->credentialMainKey);
            $credential->setApiKey($apiKey);
            $credential->setCredential($result[$label]['key_credential']);
            $entityManager->persist($credential);
        }
        $entityManager->flush();

        return $result;
    }

    /**
     * Create a random api key.
     *
     * @param string $label
     * @param User $user
     * @return array
     */
    protected function createRandomApiKey($label, User $user)
    {
        $result = [];
        $apiKey = new ApiKey();
        $apiKey->setId();
        $apiKey->setLabel($label);
        $apiKey->setOwner($user);
        $result['key_identity'] = $apiKey->getId();
        $result['key_credential'] = $apiKey->setCredential();
        $this->entityManager->persist($apiKey);
        return $result;
    }

    /**
     * Create a forced api key (the api key id is random, the value is forced).
     *
     * @param string $label
     * @param User $user
     * @return array
     */
    protected function createForcedApiKey($label, User $user)
    {
        $apiKey = new ApiKey();
        $apiKey->setId();
        $id = $apiKey->getId();
        $credential = $this->uniqueKeys[$label];
        $credentialHash = (new Bcrypt)->create($credential);
        $created = (new \DateTime('now'))->format('Y-m-d H:i:s');

        $connection = $this->entityManager->getConnection();
        $sql = <<<SQL
INSERT api_key (id, owner_id, label, credential_hash, last_ip, last_accessed, created)
VALUES ("$id", "{$user->getId()}", "$label", "$credentialHash", NULL, NULL, "$created");
SQL;
        $connection->exec($sql);

        $result = [];
        $result['key_identity'] = $id;
        $result['key_credential'] = $credential;
        return $result;
    }
}
