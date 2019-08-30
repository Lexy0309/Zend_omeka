<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ApiKeyFromUserAndLabel extends AbstractPlugin
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var string
     */
    private $credentialMainKey;

    /**
     * @param EntityManager $entityManager
     * @param string $credentialMainKey
     */
    public function __construct(EntityManager $entityManager, $credentialMainKey)
    {
        $this->entityManager = $entityManager;
        $this->credentialMainKey = $credentialMainKey;
    }

    /**
     * Find the api key for a user from a label.
     *
     * @param User $user
     * @param string $label
     * @return string|null
     */
    public function __invoke(User $user, $label)
    {
        $apiKeyRepository = $this->entityManager->getRepository(\Omeka\Entity\ApiKey::class);
        $accessKeyRepository = $this->entityManager->getRepository(\DownloadManager\Entity\Credential::class);

        $apiKey = $apiKeyRepository->findOneBy(['owner' => $user, 'label' => $label]);
        if ($apiKey) {
            $accessKey = $accessKeyRepository->findOneBy(['apiKey' => $apiKey]);
            if ($accessKey) {
                $accessKey->setCredentialMainKey($this->credentialMainKey);
                return $accessKey->getCredential();
            }
        }
    }
}
