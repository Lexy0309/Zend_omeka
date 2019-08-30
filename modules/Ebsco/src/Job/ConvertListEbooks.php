<?php
namespace Ebsco\Job;

use LimitIterator;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use SplFileObject;
use Zend\Http\Client;

/**
 * Get the url and the data of ebsco ebooks from the list.
 *
 * Get each row, then request the reference via the ebsco api.
 * Save the first reference, if any, as json.
 */
class ConvertListEbooks extends AbstractJob
{
    protected $filename = 'ebsco_list_ebooks.tsv';
    // The longest is about 1350 in this file, but add a margin.
    // TODO Determine the longest line dynamically, or set 0.
    protected $lenghtLongestLine = 4090;
    protected $folder;

    /**
     * @var string
     */
    protected $requestType = \Zend\Http\Request::METHOD_POST;

    protected $plugins;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $authentication;

    protected $flagAuthenticate = false;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $plugins = $services->get('ControllerPluginManager');
        $this->plugins = $plugins;

        $this->httpClient = $services->get('Omeka\HttpClient');

        // TODO Remove the hard coded "/files".
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $baseFolder = $basePath . DIRECTORY_SEPARATOR . 'ebsco';
        if (!file_exists($baseFolder)) {
            @mkdir($baseFolder, 0755, true);
            if (!file_exists($baseFolder)) {
                $logger->err(
                    'Error when preparing the folder "{folder}".', // @translate
                    ['folder' => $baseFolder]
                );
                return;
            }
        } elseif (!is_writeable($baseFolder)) {
            $logger->err(
                'Folder "{folder}" is not writeable.', // @translate
                ['folder' => $baseFolder]
            );
            return;
        } elseif (!is_dir($baseFolder)) {
            $logger->err(
                'Folder "{folder}" is not a folder.', // @translate
                ['folder' => $baseFolder]
            );
            return;
        }
        $this->folder = $baseFolder;

        $filepath = $basePath . DIRECTORY_SEPARATOR . $this->filename;
        if (!file_exists($filepath)) {
            $logger->err(
                'No file "{file}" to process.', // @translate
                ['file' => $filepath]
            );
            return;
        } elseif (!is_readable($filepath)) {
            $logger->err(
                'File "{file}" is not readable.', // @translate
                ['file' => $filepath]
            );
            return;
        }

        // Prepare session for api ebsco.
        $authenticateExternal = $plugins->get('authenticateExternal');
        $authentication = $authenticateExternal('ebsco');
        if (empty($authentication)) {
            $logger->err(
                'Unable to authenticate on Ebsco.' // @translate
            );
            return;
        }
        $this->authentication = $authentication;

        // When oo is used, not fgetcsv. See previous commits.
        // $iterator = $this->prepareIterator($filepath);
        // $count = iterator_count($iterator);

        $handle = fopen($filepath, 'r');
        if($handle === false) {
            $logger->err(
                'File "{file}" isn’t readable.', // @translate
                ['file' => $filepath]
            );
            return;
        }

        // Process each row.
        // Skip the first (header) row, and blank ones.
        // Skip converted ebooks.
        $offset = $settings->get('ebsco_convert_list_ebooks');
        $removed = 0;
        $offset = $offset - $removed;

        $offset = $offset ? ++$offset : 1;
        $getCsvOffset = 0;
        while(($row = fgetcsv($handle, $this->lenghtLongestLine, "\t")) !== FALSE){
            if (empty($row)) {
                break;
            }
            if ($getCsvOffset < $offset) {
                ++$getCsvOffset;
                continue;
            }
            ++$getCsvOffset;

            $logger->info(
                'Processing row #{offset}.', // @translate
                ['offset' => $offset]
            );
            $itemRow = [];
            $itemRow['title'] = $row[0];
            $itemRow['authors'] = $row[1];
            $itemRow['publisher'] = $row[2];
            $itemRow['year'] = $row[3];
            $itemRow['language'] = $row[4];
            $itemRow['subjects'] = $row[5];
            $itemRow['bisac'] = $row[6];
            $this->processRow($itemRow, $offset + $removed);
            $settings->set('ebsco_convert_list_ebooks', $offset + $removed);
            ++$offset;
        }

        fclose($handle);
    }

    /**
     * Do the request to api ebsco to get data of the book.
     *
     * @param array $row
     * @param int $offset
     */
    protected function processRow($row, $offset)
    {
        $filepath = $this->folder . DIRECTORY_SEPARATOR . $offset . '.json';

        $query = [];
        $queries = [];
        $queries[] = [
            'BooleanOperator' => 'And',
            'Term' => 'TI ' . $row['title'],
        ];
        // Keep only the first author to find the good book (ebsco doesn't seem
        // to manage multiple authors).
        $author = preg_replace('/(?![\s-])\p{P}/u', '', $row['authors']);
        $author = strtok($author, ' ');
        $queries[] = [
            'BooleanOperator' => 'And',
            'Term' => 'AU ' . $author,
        ];
        /*
        // No code for publisher or year.
        $queries[] = [
            'BooleanOperator' => 'And',
            'Term' => 'PT ' . $row['publisher'],
        ];
        $queries[] = [
            'BooleanOperator' => 'And',
            'Term' => 'PT ' . $row['year'],
        ];
        */
        $queries[] = [
            'BooleanOperator' => 'And',
            'Term' => 'PT eBook',
        ];

        $query['SearchCriteria'] = [
            'Queries' => $queries,
            // May be "all" (default), "any", "bool" or "smart".
            'SearchMode' => 'all',
            'IncludeFacets' => 'n',
            'Sort' => 'relevance',
            'AutoSuggest' => 'n',
            'AutoCorrect' => 'n',
        ];
        $query['RetrievalCriteria'] = [
            'View' => 'detailed',
            // Theoretically, there is only one answer, the good one.
            // In some cases, they may be many, so all are imported.
            'ResultsPerPage' => 100,
            'PageNumber' => 1,
            'Highlight' => 'n',
            'IncludeImageQuickView' => 'y',
        ];
        $query['Actions'] = null;

        // Reset the authentication flag for expired session.
        $this->flagAuthenticate = false;
        $result = $this->processQuery($query);

        if (empty($result)) {
            return;
        }

        // Save it as json.
        file_put_contents($filepath, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Process an external search query.
     *
     * @param array $query
     * @return array
     */
    protected function processQuery(array $query)
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];
        $headers += $this->authentication;

        $client = $this->httpClient;
        $client->resetParameters();
        $client->setHeaders($headers);
        $client->setEncType('application/json; charset=utf-8');

        $client->setMethod($this->requestType);
        $client->setRawBody(json_encode($query));

        $edsApiHost = 'https://eds-api.ebscohost.com/edsapi/rest';
        $client->setUri($edsApiHost . '/search');

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $logger = $this->plugins->get('logger');
            $logger()->warn(new Message('Issue with http client: %s',  // @translate
                $e));
            sleep(90);
            $response = $client->send();
        }
        $result = json_decode($response->getBody(), true);
        if (!$response->isSuccess()) {
            // Try to reauthenticate.
            if ($this->flagAuthenticate) {
                // throw new \SearchExternalException($response);
                $logger = $this->plugins->get('logger');
                if (empty($result['ErrorNumber'])) {
                    $logger()->err('External search error unknown.'); // @translate
                } else {
                    $logger()->err(new Message('External search error (%s): %s',  // @translate
                        $result['ErrorNumber'], $result['DetailedErrorDescription']));
                }
                return [];
            }
            $this->flagAuthenticate = true;
            $authenticateExternal = $this->plugins->get('authenticateExternal');
            $authentication = $authenticateExternal('ebsco');
            if (empty($authentication)) {
                // throw new \SearchExternalException($response);
                $logger = $this->plugins->get('logger');
                if (empty($result['ErrorNumber'])) {
                    $logger()->err('Unknown external search error.'); // @translate
                } else {
                    $logger()->err(new Message('External search / authentication error (%s): %s',  // @translate
                        $result['ErrorNumber'], $result['DetailedErrorDescription']));
                }
                return [];
            }
            $this->authentication = $authentication;
            return $this->processQuery($query);
        }

        return $result;
    }

    protected function getRow($iterator, $offset = 0)
    {
        $rows = $this->getRows($iterator, $offset, 1);
        if (is_array($rows)) {
            return reset($rows);
        }
    }

    protected function getRows($iterator, $offset = 0, $limit = 1)
    {
        $limitIterator = new LimitIterator($iterator, $offset, $limit);
        $rows = [];
        foreach ($limitIterator as $row) {
            $rows[] = $this->cleanRow($row);
        }
        return $rows;
    }

    protected function cleanRow(array $row)
    {
        return array_map(function ($v) {
            return trim($v, "\t\n\r   ");
        }, $row);
    }

    protected function prepareIterator($filepath)
    {
        $iterator = new SplFileObject($filepath);
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD
                | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $iterator->setCsvControl("\t");
        return $iterator;
    }
}
