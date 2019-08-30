<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\Plugin\Params;

class IdentityBySessionOrKey extends AbstractPlugin
{
    /**
     * @var Acl
     */
    protected $acl;

    /**
     * @var AuthenticationService
     */
    protected $authenticationServiceByKey;

    /**
     * @var Params
     */
    protected $params;

    /**
     * @param Acl $acl
     * @param AuthenticationService $authenticationServiceByKey
     * @param Params $params
     */
    public function __construct(
        Acl $acl,
        AuthenticationService $authenticationServiceByKey,
        Params $params
    ) {
        $this->acl = $acl;
        $this->authenticationServiceByKey = $authenticationServiceByKey;
        $this->params = $params;
    }

    /**
     * Find the api key for a user from a label.
     *
     * @return User|null
     */
    public function __invoke()
    {
        $user = $this->acl->getAuthenticationService()->getIdentity();
        if ($user) {
            return $user;
        }

        $identityKey = $this->params->fromQuery('key_identity');
        $credentialKey = $this->params->fromQuery('key_credential');
        if ($identityKey && $credentialKey) {
            $this->authenticationServiceByKey->getAdapter()
                ->setIdentity($identityKey)
                ->setCredential($credentialKey);
            $result = $this->authenticationServiceByKey
                ->authenticate();
            if ($result->isValid()) {
                $user = $result->getIdentity();
                $this->acl->setAuthenticationService($this->authenticationServiceByKey);
                return $user;
            }
        }
    }
}
