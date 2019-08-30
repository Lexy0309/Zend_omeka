<?php
namespace VatiruLibrary\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the vatiru api version required by the app.
 */
class RequestVatiruApiVersion extends AbstractPlugin
{
    /**
     * Get the api version from the request.
     *
     * @return string|null
     */
    public function __invoke()
    {
        $accept = $this->getController()->params()->fromHeader('Accept');
        if (empty($accept)) {
            return null;
        }

        $matches = [];
        $accept = trim($accept->getFieldValue());
        preg_match('~application/vnd\.vatiru\.api\+json;\s*version\s*=\s*(.*)~', $accept, $matches);
        return empty($matches[1]) ? null : $matches[1];
    }
}
