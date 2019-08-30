<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Convert a record from a provider into an Omeka item.
 */
class ConvertExternalRecord extends AbstractPlugin
{
    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @var string
     */
    protected $externalCreate;

    /**
     * @var array
     */
    protected $propertyIds;

    /**
     * @param PluginManager $plugins
     * @param string $externalCreate
     * @param array $propertyIds
     */
    public function __construct(PluginManager $plugins, $externalCreate, array $propertyIds)
    {
        $this->plugins = $plugins;
        $this->externalCreate = $externalCreate;
        $this->propertyIds = $propertyIds;
    }

    /**
     * Convert the results from an external search into Omeka items, as json.
     *
     * @param string $provider
     * @param array $record
     * @param bool $useJobForThumbnail
     * @return array Item as data item for the api.
     */
    public function __invoke($provider, array $record, $useJobForThumbnail = false)
    {
        switch ($provider) {
            case 'ebsco':
                return $this->convertExternalEbsco($record, $useJobForThumbnail);
        }
        return [];
    }

    /**
     * @param array $record
     * @param bool $useJobForThumbnail
     * @return array
     */
    protected function convertExternalEbsco(array $record, $useJobForThumbnail = false)
    {
        $mapEbscoTypes = [
            'ebook-epub' => 'application/epub+zip',
            'ebook-pdf' => 'application/pdf',
            // TODO To check.
            'epublink' => 'application/epub+zip',
            'pdflink' => 'application/pdf',
        ];

        $item = [];
        $item['o:resource_class'] = ['o:id' => null];
        $item['o:resource_template'] =  ['o:id' => null];
        $item['o:media'] = [];
        $item['o:item_set'] = [];

        $item['vatiru:sourceRepository'][] = [
            'property_id' => $this->propertyIds['vatiru:sourceRepository'],
            'type' => 'literal',
            'property_label' => 'Source repository',
            '@value' => 'ebsco',
        ];

        $item['vatiru:isExternal'][] = [
            'property_id' => $this->propertyIds['vatiru:isExternal'],
            'type' => 'literal',
            'property_label' => 'Is external',
            '@value' => '1',
        ];

        $item['vatiru:externalData'][] = [
            'property_id' => $this->propertyIds['vatiru:externalData'],
            'type' => 'literal',
            'property_label' => 'External data',
            '@value' => json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $item['dcterms:identifier'][] = [
            'property_id' => 10,
            'type' => 'literal',
            'property_label' => 'Identifier',
            '@value' => 'ebsco:' . http_build_query([
                'dbid' => $record['Header']['DbId'],
                'an' => $record['Header']['An'],
            ]),
        ];

        if (!empty($record['Header']['PubType'])) {
            $types = $this->mappingPublicationType($record['Header']['PubType']);
            foreach ($types as $type) {
                $item['dcterms:type'][] = [
                    'property_id' => 8,
                    'type' => 'literal',
                    'property_label' => 'Type',
                    '@value' => $type,
                ];
            }
            $item['dcterms:type'][] = [
                'property_id' => 8,
                'type' => 'literal',
                'property_label' => 'Type',
                '@value' => 'ebsco: ' . $record['Header']['PubType'],
            ];

            // Add the special values to determine the vatiru priority and main
            // publication type.
            $vatiruData = $this->vatiruData($record['Header']['PubType']);
        }
        // Add default values.
        else {
            // Add dcterms:BibliographicResource?
            /*
             $item['dcterms:type'][] = [
                'property_id' => 8,
                'type' => 'literal',
                'property_label' => 'Type',
                '@value' => 'dcterms:BibliographicResource',
            ];
            */
            $vatiruData = ['priority' => '999', 'type' => 'undefined'];
        }

        $item['vatiru:resourcePriority'][] = [
            'property_id' => $this->propertyIds['vatiru:resourcePriority'],
            'type' => 'literal',
            'property_label' => 'Resource Priority',
            '@value' => $vatiruData['priority'],
        ];
        $item['vatiru:publicationType'][] = [
            'property_id' => $this->propertyIds['vatiru:publicationType'],
            'type' => 'literal',
            'property_label' => 'Publication Type',
            '@value' => $vatiruData['type'],
        ];

        if (!empty($record['RecordInfo']['BibRecord']['BibEntity']['Languages'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibEntity']['Languages'] as $v) {
                $item['dcterms:language'][] = [
                    'property_id' => 12,
                    'type' => 'literal',
                    'property_label' => 'Language',
                    // TODO Convert text into language code when code is missing.
                    '@value' => empty($v['Code']) ? $v['Text'] : $v['Code'],
                ];
            }
        }

        if (!empty($record['RecordInfo']['BibRecord']['BibEntity']['Subjects'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibEntity']['Subjects'] as $v) {
                $item['dcterms:subject'][] = [
                    'property_id' => 3,
                    'type' => 'literal',
                    'property_label' => 'Subject',
                    '@value' => $v['SubjectFull'],
                ];
            }
        }

        if (!empty($record['RecordInfo']['BibRecord']['BibEntity']['Titles'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibEntity']['Titles'] as $v) {
                if (empty($v['Type']) || $v['Type'] === 'main') {
                    $item['dcterms:title'][] = [
                        'property_id' => 1,
                        'type' => 'literal',
                        'property_label' => 'Title',
                        '@value' => $v['TitleFull'],
                    ];
                } else {
                    $item['dcterms:alternative'][] = [
                        'property_id' => 17,
                        'type' => 'literal',
                        'property_label' => 'Alternative title',
                        '@value' => $v['TitleFull'],
                    ];
                }
            }
        }

        if (!empty($record['RecordInfo']['BibRecord']['BibEntity']['PhysicalDescription'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibEntity']['PhysicalDescription'] as $k => $v) {
                if ($k === 'Pagination') {
                    if (!empty($v['PageCount'])) {
                        $item['bibo:numPages'][] = [
                            'property_id' => 106,
                            'type' => 'literal',
                            'property_label' => 'number of pages',
                            '@value' => $v['PageCount'],
                        ];
                    }
                    if (!empty($v['StartPage'])) {
                        $item['bibo:pageStart'][] = [
                            'property_id' => 111,
                            'type' => 'literal',
                            'property_label' => 'page start',
                            '@value' => $v['StartPage'],
                        ];
                    }
                    if (!empty($v['EndPage'])) {
                        $item['bibo:pageEnd'][] = [
                            'property_id' => 110,
                            'type' => 'literal',
                            'property_label' => 'page end',
                            '@value' => $v['EndPage'],
                        ];
                    }
                }
            }
        }

        if (!empty($record['RecordInfo']['BibRecord']['BibEntity']['Identifiers'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibEntity']['Identifiers'] as $k => $v) {
                if ($v['Type'] === 'doi') {
                    $item['bibo:doi'][] = [
                        'property_id' => 92,
                        'type' => 'literal',
                        'property_label' => 'doi',
                        '@value' => $v['Value'],
                    ];
                } else {
                    $item['bibo:identifier'][] = [
                        'property_id' => 98,
                        'type' => 'literal',
                        'property_label' => 'Identifier',
                        '@value' => $v['Type'] . ':' . $v['Value'],
                    ];
                }
            }
        }

        if (!empty($record['RecordInfo']['BibRecord']['BibRelationships']['HasContributorRelationships'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibRelationships']['HasContributorRelationships'] as $v) {
                $property = 'dcterms:creator';
                $propertyId = 2;
                $propertyLabel = 'Creator';
                $name = $v['PersonEntity']['Name']['NameFull'];
                // Search if the author is a creator (label = Authors) or a
                // contributor (not set in record items).
                foreach ($record['Items'] as $recordItem) {
                    if ($recordItem['Label'] === 'Contributors') {
                        if (strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')) === $name) {
                            $property = 'dcterms:contributor';
                            $propertyId = 6;
                            $propertyLabel = 'Contributor';
                            break;
                        }
                    }
                }
                $item[$property][] = [
                    'property_id' => $propertyId,
                    'type' => 'literal',
                    'property_label' => $propertyLabel,
                    '@value' => $name,
                ];
            }
        }

        // Example: the article is part of a journal, etc.
        if (!empty($record['RecordInfo']['BibRecord']['BibRelationships']['IsPartOfRelationships'])) {
            foreach ($record['RecordInfo']['BibRecord']['BibRelationships']['IsPartOfRelationships'] as $vpor) {
                foreach ($vpor['BibEntity'] as $vbeKey => $vbe) {
                    switch ($vbeKey) {
                        case 'Dates':
                            foreach ($vbe as $v) {
                                if ($v['Type'] === 'published') {
                                    $item['dcterms:date'][] = [
                                        'property_id' => 7,
                                        'type' => 'literal',
                                        'property_label' => 'Date',
                                        '@value' => $v['Y'] . (empty($v['M']) ? '' : (('-' . $v['M']) . (empty($v['D']) ? '' : ('-' . $v['D'])))),
                                    ];
                                } else {
                                    $item['dcterms:date'][] = [
                                        'property_id' => 7,
                                        'type' => 'literal',
                                        'property_label' => 'Date',
                                        '@value' => $v['Type'] . ': ' . $v['Y'] . (empty($v['M']) ? '' : (('-' . $v['M']) . (empty($v['D']) ? '' : ('-' . $v['D'])))),
                                    ];
                                }
                            }
                            break;

                        case 'Identifiers':
                            foreach ($vbe as $v) {
                                if (in_array($v['Type'], ['issn-print', 'issn'])) {
                                    $item['bibo:issn'][] = [
                                        'property_id' => 102,
                                        'type' => 'literal',
                                        'property_label' => 'Issn',
                                        '@value' => $v['Value'],
                                    ];
                                } elseif ($v['Type'] === 'issn-electronic') {
                                    $item['bibo:eissn'][] = [
                                        'property_id' => 95,
                                        'type' => 'literal',
                                        'property_label' => 'Electronic issn',
                                        '@value' => $v['Value'],
                                    ];
                                } elseif (in_array($v['Type'], ['isbn-print', 'isbn'])) {
                                    $item['bibo:isbn'][] = [
                                        'property_id' => 99,
                                        'type' => 'literal',
                                        'property_label' => 'Isbn',
                                        '@value' => $v['Value'],
                                    ];
                                } elseif ($v['Type'] === 'isbn-electronic') {
                                    $item['bibo:isbn13'][] = [
                                        'property_id' => 101,
                                        'type' => 'literal',
                                        'property_label' => 'Isbn',
                                        '@value' => 'electronic: ' . $v['Value'],
                                    ];
                                } else {
                                    $item['bibo:identifier'][] = [
                                        'property_id' => 98,
                                        'type' => 'literal',
                                        'property_label' => 'Identifier',
                                        '@value' => $v['Type'] . ':' . $v['Value'],
                                    ];
                                }
                            }
                            break;

                        case 'Numbering':
                            foreach ($vbe as $v) {
                                if (in_array($v['Type'], ['volume'])) {
                                    $item['bibo:volume'][] = [
                                        'property_id' => 122,
                                        'type' => 'literal',
                                        'property_label' => 'Volume',
                                        '@value' => $v['Value'],
                                    ];
                                } elseif (in_array($v['Type'], ['issue'])) {
                                    $item['bibo:issue'][] = [
                                        'property_id' => 103,
                                        'type' => 'literal',
                                        'property_label' => 'Issue',
                                        '@value' => $v['Value'],
                                    ];
                                } else {
                                    $item['bibo:locator'][] = [
                                        'property_id' => 105,
                                        'type' => 'literal',
                                        'property_label' => 'Locator',
                                        '@value' => $v['Type'] . ':' . $v['Value'],
                                    ];
                                }
                            }
                            break;

                        case 'Titles':
                            foreach ($vbe as $v) {
                                if (empty($v['Type']) || $v['Type'] === 'main') {
                                    $item['dcterms:isPartOf'][] = [
                                        'property_id' => 33,
                                        'type' => 'literal',
                                        'property_label' => 'Is Part Of',
                                        '@value' => $v['TitleFull'],
                                    ];
                                } else {
                                    $item['bibo:reproducedIn'][] = [
                                        'property_id' => 78,
                                        'type' => 'literal',
                                        'property_label' => 'Reproduced in',
                                        '@value' => $v['Type'], $v['TitleFull'],
                                    ];
                                }
                            }
                            break;
                    }
                }
            }
        }

        // These links are true links, but available only in the full record.
        if (!empty($record['FullText']['Links'])) {
            foreach ($record['FullText']['Links'] as $v) {
                // The url may be not available during search.
                if (empty($v['Url'])) {
                    $item['dcterms:format'][] = [
                        'property_id' => 9,
                        'type' => 'literal',
                        'property_label' => 'Format',
                        '@value' => isset($mapEbscoTypes[$v['Type']]) ? $mapEbscoTypes[$v['Type']] : $v['Type'],
                    ];
                    continue;
                }
                if (in_array($v['Type'], ['pdflink', 'epublink'])) {
                    $media = [];
                    $media['o:is_public'] = 1;
                    $media['o:ingester'] = 'external';
                    $media['ingest_url'] = $v['Url'];
                    $media['store_original'] = false;
                    if (!empty($record['ImageInfo'])) {
                        // Try to keep one image, the biggest if possible.
                        // TODO What are the possible size for imageinfo?
                        foreach ($record['ImageInfo'] as $image) {
                            // Warning: xml add a tag "CoverArt" before Target.
                            $media['thumbnail_url'] = $image['Target'];
                            $media['thumbnail_job'] = $useJobForThumbnail;
                            if ($image['Size'] === 'medium') {
                                break;
                            }
                        }
                    } else {
                        $media['thumbnail_url'] = '';
                    }
                    $media['dcterms:format'][] = [
                        'property_id' => 9,
                        'type' => 'literal',
                        'property_label' => 'Format',
                        '@value' => $mapEbscoTypes[$v['Type']],
                    ];
                    $media['vatiru:isExternal'][] = [
                        'property_id' => $this->propertyIds['vatiru:isExternal'],
                        'type' => 'literal',
                        '@value' => 1,
                    ];
                    // $media['vatiru:externalSource'][] = [
                    //     'property_id' => $this->propertyIds['vatiru:externalSource'],
                    //     'type' => 'literal',
                    //     '@value' => $record['Data'],
                    // ];
                    $item['o:media'][] = $media;
                }
            }
        }

        foreach ($record['Items'] as $recordItem) {
            switch ($recordItem['Label']) {
                case 'Abstract':
                    // TODO Use abstract or description? Currently description by simplicity.
                    // $item['dcterms:abstract'][] = [
                    //     'property_id' => 19,
                    //     'type' => 'literal',
                    //     'property_label' => 'Abstract',
                    $value = trim(strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')));
                    $pos = stripos($value, 'abstract');
                    if (!$pos || $pos > 1) {
                        $value = '[Abstract] ' . $value;
                    }
                    $item['dcterms:description'][] = [
                        'property_id' => 4,
                        'type' => 'literal',
                        'property_label' => 'Description',
                        '@value' => strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')),
                    ];
                    break;

                case 'Description':
                    $item['dcterms:description'][] = [
                        'property_id' => 4,
                        'type' => 'literal',
                        'property_label' => 'Description',
                        '@value' => strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')),
                    ];
                    break;

                // Don't repeat the bib record values
                // TODO Check if record BibEntity and record items there are different.
                /*
                case 'Authors':
                    $item['dcterms:creator'][] = [
                        'property_id' => 2,
                        'type' => 'literal',
                        'property_label' => 'Creator',
                        '@value' => strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')),
                    ];
                    break;

                // TODO Where to save the file description? In the media filled by Source.
                case 'File Description':
                    $item['o:media'][0]['dcterms:format'][] = [
                        'property_id' => 9,
                        'type' => 'literal',
                        'property_label' => 'Format',
                        '@value' => strip_tags($recordItem['Data']),
                    ];
                    break;

                case 'Source':
                    // Many sources are not a url. Allows to get free pdf link.
                    $data = $recordItem['Data'];
                    // TODO Clean the process to identify free links.
                    if (strpos($data, 'http') === 0) {
                        $media = [];
                        $media['o:is_public'] = 1;
                        $media['o:ingester'] = 'external';
                        $media['ingest_url'] = strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8'));
                        $media['store_original'] = false;
                        // TODO The thumbnail may be added.
                        $media['thumbnail_url'] = '';
                        $media['vatiru:isExternal'][] = [
                            'property_id' => $this->propertyIds['vatiru:isExternal'],
                            'type' => 'literal',
                            '@value' => 1,
                        ];
                        // $media['vatiru:externalSource'][] = [
                        //     'property_id' => $this->propertyIds['vatiru:externalSource'],
                        //     'type' => 'literal',
                        //     '@value' => $recordItem['Data'],
                        // ];
                        $item['o:media'][] = $media;
                    } else {
                        $item['dcterms:source'][] = [
                            'property_id' => 11,
                            'type' => 'literal',
                            'property_label' => 'Source',
                            '@value' => strip_tags(html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8')),
                        ];
                    }
                    break;
                */

                // Access url is used for free pdf files, on some databases.
                case 'Access URL':
                    // Only find pdf link.
                    $regex = '~<link linkTarget="URL" linkTerm="(http[s]?://[^"]*?\.pdf)"~i';
                    $str = html_entity_decode($recordItem['Data'], ENT_COMPAT | ENT_HTML5, 'utf-8');
                    $matches = [];
                    preg_match($regex, $str, $matches);
                    // TODO Currently manage only one file.
                    if (!empty($matches[1])) {
                        $media = [];
                        $media['o:is_public'] = 1;
                        $media['o:ingester'] = 'external';
                        $media['ingest_url'] = $matches[1];
                        $media['store_original'] = false;
                        if (!empty($record['ImageInfo'])) {
                            // Try to keep one image, the biggest if possible.
                            // TODO What are the possible size for imageinfo?
                            foreach ($record['ImageInfo'] as $image) {
                                $media['thumbnail_url'] = $image['Target'];
                                $media['thumbnail_job'] = $useJobForThumbnail;
                                if ($image['Size'] === 'medium') {
                                    break;
                                }
                            }
                        } else {
                            $media['thumbnail_url'] = '';
                        }
                        $media['dcterms:format'][] = [
                            'property_id' => 9,
                            'type' => 'literal',
                            'property_label' => 'Format',
                            '@value' => 'application/pdf',
                        ];
                        $media['vatiru:isExternal'][] = [
                            'property_id' => $this->propertyIds['vatiru:isExternal'],
                            'type' => 'literal',
                            '@value' => 1,
                        ];
                        // $media['vatiru:externalSource'][] = [
                        //     'property_id' => $this->propertyIds['vatiru:externalSource'],
                        //     'type' => 'literal',
                        //     '@value' => $recordItem['Data'],
                        // ];
                        $item['o:media'][] = $media;
                    }
                    break;
            }
        }

        // Check if there is a file, else save the url to the full record.
        if (isset($item['o:media'][0]['o:ingester'])) {
            // // Store the media type if available (via a quick check). Useless.
            // if (!empty($item['o:media'][0]['dcterms:format'][0]['@value'])) {
            //     $data = $item['o:media'][0]['dcterms:format'][0]['@value'];
            //     if (strpos($data, '/') && !strpos($data, ' ')) {
            //         $item['o:media'][0]['media_type'] = $data;
            //     }
            // }
        }
        // Save a media only if wanted.
        else {
            // The partial media is never kept.
            $item['o:media'] = [];

            if (in_array($this->externalCreate, ['record_url', 'metadata'])) {
                // Create an "ebsco" media for ebooks.
                if ($record['Header']['PubTypeId'] === 'ebook') {
                    $url = $record['PLink'];
                    $media = [];
                    $media['o:is_public'] = 1;
                    $media['o:ingester'] = 'ebsco';
                    $media['ingest_url'] = $url;
                    $media['o:source'] = $url;
                    $media['thumbnail_job'] = $useJobForThumbnail;
                }
                // Else create an "external" media.
                else {
                    $url = $this->retrieveUrlEbsco($record);
                    $media = [];
                    $media['o:is_public'] = 1;
                    $media['o:ingester'] = 'external';
                    $media['ingest_url'] = $url;
                    $media['o:source'] = $url;
                    $media['store_original'] = false;
                    // Set the ingest url is a record url, not a file url.
                    $media['data']['is_record_url'] = true;
                }

                if (!empty($record['ImageInfo'])) {
                    // Try to keep one image, the biggest if possible.
                    // TODO What are the possible size for imageinfo?
                    foreach ($record['ImageInfo'] as $image) {
                        // Warning: xml add a tag "CoverArt" before Target.
                        $media['thumbnail_url'] = $image['Target'];
                        $media['thumbnail_job'] = $useJobForThumbnail;
                        if ($image['Size'] === 'medium') {
                            break;
                        }
                    }
                } else {
                    $media['thumbnail_url'] = '';
                }

                $media['vatiru:isExternal'][] = [
                    'property_id' => $this->propertyIds['vatiru:isExternal'],
                    'type' => 'literal',
                    '@value' => 1,
                ];
                $item['o:media'][] = $media;
            }
        }

        return $item;
    }

    /**
     * Get the url to retrieve full data of a record.
     *
     * @param array $record
     * @return string
     */
    protected function retrieveUrlEbsco(array $record)
    {
        return 'https://eds-api.ebscohost.com/edsapi/rest/retrieve?'
            . http_build_query([
                'dbid' => $record['Header']['DbId'],
                'an' => $record['Header']['An'],
                // Default: ebook-epub.
                // 'ebookpreferredformat' => 'ebook-epub',
                'ebookpreferredformat' => 'ebook-pdf',
            ]);
    }

    /**
     * Mapping ebsco publication type to standard type.
     *
     * The search is done with label publication type only.
     * There is no list for the equivalent pubtypeid (generally the lowercase
     * string without space). Some publication type are not searchable.
     *
     * @link http://support.ebsco.com/knowledge_base/detail.php?id=6674
     *
     * @param string $type
     * @return array
     */
    protected function mappingPublicationType($type)
    {
        $map = include dirname(dirname(dirname(dirname(__DIR__))))
            . '/data/mappings/ebsco_publication_type_to_rdf_classes.php';
        if (isset($map[$type])) {
            return $map[$type];
        }
        $logger = $this->plugins->get('logger');
        $logger()->notice(
            new Message('Publication type unknown for normalization: %s', $type)); // @translate
        return [];
    }

    /**
     * Get the vatiru priority and type from the ebsco publication type.
     *
     * @param string $type
     * @return array
     */
    protected function vatiruData($type)
    {
        $map = include dirname(dirname(dirname(dirname(__DIR__))))
            . '/data/mappings/ebsco_publication_type_to_vatiru.php';
        if (isset($map[$type])) {
            return $map[$type];
        }
        $logger = $this->plugins->get('logger');
        $logger()->notice(
            new Message('No Vatiru data for ebsco type "%s".', $type)); // @translate
        return ['priority' => '999', 'type' => 'undefined'];
    }
}
