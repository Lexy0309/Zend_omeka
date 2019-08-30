<?php
namespace DownloadManager\View\Helper;

use Zend\View\Helper\AbstractHelper;

/**
 * Helper to get the login url depending on the login adapter.
 */
class UrlLogin extends AbstractHelper
{
    /**
     * Generates a url given the name of a route.
     *
     * @uses Zend\View \Helper\Url::__invoke()
     *
     * @param string $redirectUrl
     * @return string
     */
    public function __invoke($redirectUrl = null)
    {
        $view = $this->getView();
        $options = $redirectUrl ? ['query' => ['redirect' => $redirectUrl]] : [];

        // Saml is forced only on user-key.
        // if ($view->isModuleActive('Saml') && $view->siteSetting('saml_enabled', false)) {
        //     $siteSlug = $view->params()->fromRoute('site-slug');
        //     return $view->url('site/saml',
        //         [
        //             'site-slug' => $siteSlug,
        //             'action' => 'login',
        //         ],
        //         $options
        //     );
        // }

        if ($view->isModuleActive('GuestUser')) {
            $siteSlug = $view->params()->fromRoute('site-slug');
            return $view->url('site/guest-user',
                [
                    'site-slug' => $siteSlug,
                    'action' => 'login',
                ],
                $options
            );
        }

        return $view->url('login', [], $options);
    }
}
