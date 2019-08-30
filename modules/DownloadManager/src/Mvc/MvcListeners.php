<?php
namespace DownloadManager\Mvc;

use Zend\Mvc\MvcEvent;

class MvcListeners extends \Omeka\Mvc\MvcListeners
{
    /**
     * @var bool
     */
    protected $isDownloadRequestWithKey;

    public function authenticateApiKey(MvcEvent $event)
    {
        $status = $event->getApplication()->getServiceManager()
            ->get('Omeka\Status');

        if (!$status->isApiRequest()) {
            // This is not an API request.
            if (!$this->isDownloadRequestWithKey($event)) {
                return;
            }
        }

        $request = $event->getRequest();
        $identity = $request->getQuery('key_identity');
        $credential = $request->getQuery('key_credential');

        if (is_null($identity) || is_null($credential)) {
            // No identity/credential key to authenticate against.
            return;
        }

        $auth = $event->getApplication()->getServiceManager()
            ->get('Omeka\AuthenticationService');
        $auth->getAdapter()->setIdentity($identity);
        $auth->getAdapter()->setCredential($credential);
        $auth->authenticate();
    }

    /**
     * Check whether the current HTTP request has the credential keys.
     *
     * @see \Omeka\Mvc\Status
     * @return bool
     */
    protected function isDownloadRequestWithKey(MvcEvent $event)
    {
        if (is_null($this->isDownloadRequestWithKey)) {
            $request = $event->getRequest();
            $this->isDownloadRequestWithKey =
                !is_null($request->getQuery('key_identity'))
                && !is_null($request->getQuery('key_credential'));
        }
        return $this->isDownloadRequestWithKey;
    }
}
