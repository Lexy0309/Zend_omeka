<?php
namespace DownloadManager\Entity;

use Omeka\Entity\ApiKey;
use Omeka\Entity\Exception\RuntimeException;
use Zend\Crypt\BlockCipher;

/**
 * @Entity
 */
class Credential
{
    /**
     * One Credential references one ApiKey.
     * @Id
     * @OneToOne(targetEntity="Omeka\Entity\ApiKey")
     * @JoinColumn(
     *      nullable=false,
     *      onDelete="CASCADE"
     * )
     * @var ApiKey
     */
    protected $apiKey;

    /**
     * The hashed key credential
     *
     * @Column(length=190)
     */
    protected $credential;

    /**
     * The main key used to protect the credentials.
     *
     * @var string
     */
    private $credentialMainKey;

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function setCredential($credential)
    {
        $blockCipher = $this->getBlockCipher();
        $key = $this->getMainKey();
        $blockCipher->setKey($key);
        $this->credential = $blockCipher->encrypt($credential);
    }

    public function getCredential()
    {
        $blockCipher = $this->getBlockCipher();
        $key = $this->getMainKey();
        $blockCipher->setKey($key);
        $credential = $blockCipher->decrypt($this->credential);
        return $credential;
    }

    protected function getBlockCipher()
    {
        static $blockCipher;

        if ($blockCipher) {
            return $blockCipher;
        }

        if (extension_loaded('openssl')) {
            $cipher = 'openssl';
        } elseif (extension_loaded('mcrypt')) {
            $cipher = 'mcrypt';
        } else {
            throw new RuntimeException('One of the php extensions "openssl" or "mcrypt" should be installed.'); // @translate
        }

        $blockCipher = BlockCipher::factory($cipher, ['algo' => 'aes']);
        return $blockCipher;
    }

    /**
     * Get the main key used to protect the key.
     *
     * @throws \Omeka\Entity\Exception\RuntimeException
     * @return string
     */
    private function getMainKey()
    {
        if (empty($this->credentialMainKey)) {
            throw new RuntimeException('The main key is not set.'); // @translate
        }
        return $this->credentialMainKey;
    }

    public function setCredentialMainKey($credentialMainKey)
    {
        $this->credentialMainKey = $credentialMainKey;
    }
}
