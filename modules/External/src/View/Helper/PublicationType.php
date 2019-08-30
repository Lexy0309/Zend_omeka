<?php
namespace External\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class PublicationType extends AbstractHelper
{
    /**
     * Get the publication type of a resource (manage external document public).
     *
     * @param Resource $resource
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        // Value::asHtml() is used because it is the simplest way to get values.
        $dctermsTypes = array_map(function ($v) {
            return $v->asHtml();
        }, $resource->value('dcterms:type', ['all' => true, 'default' => []]));
        if (empty($dctermsTypes)) {
            return;
        }

        // If there is an ebsco publication type, use it.
        $result = array_filter($dctermsTypes, function ($v) {
            return strpos($v, 'ebsco:') === 0;
        });
        if ($result) {
           return trim(str_replace('ebsco:', '', reset($result)));
        }

        // Sort the properties in order to get the most precise.
        // Only common classes are checked.
        // TODO Manage the publication type of all classes.
        $types = [
            'bibo:Collection' => 'Collection',
            'dctype:Text' => 'Text',
            'dctype:PhysicalObject' => 'Physical Object',
            'dctype:Sound' => 'Sound',
            'dctype:StillImage' => 'Still Image',
            'dctype:MovingImage' => 'Moving Image',
            'bibo:Book' => 'Book',
            'bibo:Film' => 'Film',
            'bibo:Image'=> 'Image',
            'bibo:AudioDocument' => 'Audio',
            'bibo:Website' => 'Website',
            'dctype:InteractiveResource' => 'Interactive Resource',

            'bibo:Periodical' => 'Periodical',
            'bibo:Newspaper' => 'Newspaper',
            'bibo:Journal' => 'Journal',
            'bibo:Conference' => 'Conference',

            'bibo:Report' => 'Report',
            'bibo:ReferenceSource' => 'Reference Source',
            'bibo:Proceedings' => 'Proceedings',
            'bibo:AudioVisualDocument' => 'Audio Visual',
            'bibo:Map' => 'Map',
            'bibo:LegalDocument' => 'Legal Document',

            'bibo:Chapter' => 'Book Chapter',

            'bibo:Thesis' => 'Thesis',
            'bibo:Article' => 'Article',
            'bibo:AcademicArticle' => 'Academic Article',
            'bibo:Interview' => 'Interview',
            'bibo:Manuscript' => 'Manuscript',
            'bibo:Patent' => 'Patent',
        ];

        $result = array_intersect_key($types, array_flip($dctermsTypes));
        return $result ? array_pop($result) : array_pop($dctermsTypes);
    }
}
