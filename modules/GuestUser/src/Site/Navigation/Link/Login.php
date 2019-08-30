<?php
namespace GuestUser\Site\Navigation\Link;

use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Stdlib\ErrorStore;

class Login implements LinkInterface
{
    public function getName()
    {
        return 'Guest Login'; // @translate
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/login';
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: login link missing label');
            return false;
        }
        return true;
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return isset($data['label']) && '' !== trim($data['label'])
            ? $data['label']
            : 'Login'; // @translate

    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
            'route' => 'site/guest-user',
            'class' => 'loginlink',
            'params' => [
                'site-slug' => $site->slug(),
                'controller' => \GuestUser\Controller\Site\GuestUserController::class,
                'action' => 'login',
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        return [
            'label' => $data['label'],
        ];
    }
}
