<?php
namespace External\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;
use Zend\Http\Client;

class RetrieveExternal extends AbstractPlugin
{
    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param Client $httpClient
     * @param PluginManager $plugins
     */
    public function __construct(Client $httpClient, PluginManager $plugins)
    {
        $this->httpClient = $httpClient;
        $this->plugins = $plugins;
    }

    /**
     * Get one item from an external provider.
     *
     * For Ebsco, the documentation is not public, neither the open source code:
     * everything should be requested to the support, and it may accept or not.
     *
     * When there is no search string, Ebsco doesn't return anything, so a
     * mitigation is currently done with a search on "e", that is the most
     * frequent letter.
     * TODO Ebsco search without query (get all results).
     *
     * @see vufind/module/VuFindSearch/src/VuFindSearch/Backend/EDS/Base.php
     *
     * @param string $provider
     * @param array $params
     * @return array
     */
    public function __invoke($provider, array $params)
    {
        switch ($provider) {
            case 'ebsco':
                return $this->retrieveEbsco($params);
        }
        return [];
    }

    protected function retrieveEbsco(array $params)
    {
        if (empty($params['url'])) {
            if (empty($params['dbid']) || empty($params['an'])) {
                return [];
            }
            $edsApiHost = 'https://eds-api.ebscohost.com/edsapi/rest';
            $params['ebookpreferredformat'] = 'ebook-pdf';
            $url = $edsApiHost . '/retrieve' . http_build_query($params);
        } else {
            $url = $params['url'];
        }

        $authenticateExternal = $this->plugins->get('authenticateExternal');
        $authentication = $authenticateExternal('ebsco');
        if (empty($authentication)) {
            return [];
        }

        $client = $this->httpClient;
        $client->setMethod(\Zend\Http\Request::METHOD_GET);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];
        $headers += $authentication;

        $client->resetParameters();
        $client->setHeaders($headers);
        $client->setEncType('application/json; charset=utf-8');

        // $client->setParameterGet($params);
        $client->setUri($url);

        $response = $client->send();
        $result = json_decode($response->getBody(), true);
        if (!$response->isSuccess()) {
            $message = empty($result['DetailedErrorDescription'])
                ? 'Unknow error when retrieving ebsco record.' // @translate
                : $result['DetailedErrorDescription'];
            $logger = $this->plugins->get('logger');
            $logger()->warn($message);
            // throw new \SearchExternalException($response);
            return [];
        }
        return $result;
    }
}
