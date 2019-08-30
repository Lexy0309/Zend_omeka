<?php
namespace VatiruLibrary\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * Check if the request comes from an external app (not managed by Omeka).
 *
 * The check is done simply with the header 'X-Requested-With', that is not
 * added by Omeka.
 */
class IsRequestedWithVatiruLibraryApp extends AbstractPlugin
{
    const REQUESTED_WITH = 'com.vatiru';

    /**
     * Get the current vatiru api version.
     *
     * @param Params $params
     * @return bool
     */
    public function __invoke(Params $params)
    {
        $requestedWith = $params->fromHeader('X-Requested-With');
        if (empty($requestedWith)) {
            return false;
        }

        $requestedWith = $requestedWith->getFieldValue();
        return strpos($requestedWith, self::REQUESTED_WITH) === 0;
    }
}
