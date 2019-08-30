<?php

namespace Coins\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ItemRepresentation;

/**
 * COinS
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * @package Coins\View\Helper
 */
class Coins extends AbstractHelper
{
    /**
     * Return a COinS span tag for every passed item.
     *
     * @param array|Item An array of item records or one item record.
     * @return string
     */
    public function __invoke($items)
    {
        if (!is_array($items)) {
            return $this->_getCoins($items);
        }

        $coins = '';
        foreach ($items as $item) {
            $coins .= $this->_getCoins($item);
        }
        return $coins;
    }

    /**
     * Build and return the COinS span tag for the specified item.
     *
     * @param Item $item
     * @return string
     */
    protected function _getCoins(ItemRepresentation $item)
    {
        $coins = array();

        $coins['ctx_ver'] = 'Z39.88-2004';
        $coins['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
        $coins['rfr_id'] = 'info:sid/omeka.org:generator';

        // Set the Dublin Core elements that don't need special processing.
        $elementNames = array('Creator', 'Subject', 'Publisher', 'Contributor',
                              'Date', 'Format', 'Source', 'Language', 'Coverage',
                              'Rights', 'Relation');
        foreach ($elementNames as $elementName) {
            $elementName = strtolower($elementName);
            $elementText = $this->_getElementText($item, $elementName);
            if (false === $elementText) {
                continue;
            }

            $coins["rft.$elementName"] = $elementText;
        }

        // Set the title key from Dublin Core:title.
        $title = $this->_getElementText($item, 'title');
        if (false === $title || '' == trim($title)) {
            $title = '[unknown title]';
        }
        $coins['rft.title'] = $title;

        // Set the description key from Dublin Core:description.
        $description = $this->_getElementText($item, 'description');
        if (false === $description) {
            return;
        }
        $coins['rft.description'] = $description;

        // Set the type key from item type, map to Zotero item types.
        $resourceClass = $item->resourceClass();
        if ($resourceClass) {
            switch ($resourceClass->localName()) {
                case 'Interview':
                    $type = 'interview';
                    break;
                case 'MovingImage':
                    $type = 'videoRecording';
                    break;
                case 'Sound':
                    $type = 'audioRecording';
                    break;
                case 'Email':
                    $type = 'email';
                    break;
                case 'Website':
                    $type = 'webpage';
                    break;
                case 'Text':
                case 'Document':
                    $type = 'document';
                    break;
                default:
                    $type = $resourceClass;
            }
        } else {
            $type = $this->_getElementText($item, 'type');
        }
        $coins['rft.type'] = $type;

        // Set the identifier key as the absolute URL of the current page.
        $coins['rft.identifier'] = $item->url();

        // Build and return the COinS span tag.
        $coinsSpan = '<span class="Z3988" title="';
        $coinsSpan .= htmlspecialchars_decode(http_build_query($coins));
        $coinsSpan .= '"></span>';
        return $coinsSpan;
    }

    /**
     * Get the unfiltered element text for the specified item.
     *
     * @param Item $item
     * @param string $elementName
     * @return string|bool
     */
    protected function _getElementText(ItemRepresentation $item, $elementName)
    {
        $value = $item->value("dcterms:$elementName", array('type' => 'literal'));
        return "$value";
    }
}
