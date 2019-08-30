<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class CreateServerSalt extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $uniqueServerKey;

    /**
     * @param string $uniqueServerKey
     */
    public function __construct($uniqueServerKey)
    {
        $this->uniqueServerKey = $uniqueServerKey;
    }

    /**
     * Get the server salt, using the unique server key if wanted.
     *
     * @param bool $useUniqueServerKey
     * @return string
     */
    public function __invoke($useUniqueServerKey = false)
    {
        return  $useUniqueServerKey
            ? $this->generateUniqueServerSalt()
            : $this->generateRandomString(64);
    }

    /**
     * Generate the unique server salt.
     *
     * @return string
     */
    protected function generateUniqueServerSalt()
    {
        $salt = str_rot13($this->uniqueServerKey) . strrev($this->uniqueServerKey);
        return $salt;
    }

    /**
     * Generate a random string.
     *
     * @param int $length
     * @return string
     */
    protected function generateRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $max)];
        }
        return $randomString;
    }
}
