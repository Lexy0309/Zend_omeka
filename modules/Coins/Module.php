<?php
namespace Coins;

use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

/**
 * COinS
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * The COinS plugin.
 */
class Module extends AbstractModule
{
    public function getConfig() {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('Omeka\Controller\Site\Item',
            'view.show.after', array($this, 'displayCoinsShow'));
        $sharedEventManager->attach('Omeka\Controller\Site\Item',
            'view.browse.after', array($this, 'displayCoinsBrowse'));

        $sharedEventManager->attach('Omeka\Controller\Admin\Item',
            'view.show.after', array($this, 'displayCoinsShow'));
        $sharedEventManager->attach('Omeka\Controller\Admin\Item',
            'view.browse.after', array($this, 'displayCoinsBrowse'));
    }

    public function displayCoinsShow(Event $event) {
        $view = $event->getTarget();
        $item = $view->item;
        echo $view->coins($item);
    }

    public function displayCoinsBrowse(Event $event) {
        $view = $event->getTarget();
        foreach ($view->items as $item) {
          echo $view->coins($item);
        }
    }
}
