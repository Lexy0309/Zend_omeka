<?php
namespace VatiruLibrary\Controller\Site;

use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class VatiruController extends AbstractActionController
{
    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    protected $defaultRoles = [
        \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        \Omeka\Permissions\Acl::ROLE_AUTHOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
    ];

    /**
     * Constructor
     *
     * @todo Main of the code comes from GuestUserIp.
     *
     * @param AuthenticationService $authenticationService
     */
    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function authAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }
        $view = new ViewModel;
        $view->setTemplate('guest-user/site/guest-user/auth');
        return $view;
    }

    protected function isUserLogged()
    {
        $auth = $this->getAuthenticationService();
        return $auth->hasIdentity()
            && $auth->getIdentity()->getRole() !== 'guest_ip';
    }

    /**
     * Redirect to admin or site according to the role of the user.
     *
     * @return \Zend\Http\Response
     */
    protected function redirectToAdminOrSite()
    {
        $user = $this->getAuthenticationService()->getIdentity();
        return $user && in_array($user->getRole(), $this->defaultRoles)
            ? $this->redirect()->toRoute('admin', [], true)
            : $this->redirect()->toRoute('site', [], true);
    }

    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }
}
