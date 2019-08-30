<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;
use Zend\Http\Client;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Prepare authentication on an external server.
 */
class AuthenticateExternal extends AbstractPlugin
{
    /**
     * The ebsco timeout is 480, minus a margin of 30 seconds. See ebsco Info.
     *
     * @var int
     */
    const EBSCO_TIMEOUT = 450;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var bool
     */
    protected $hasIdentity;

    /**
     * @param Client $httpClient
     * @param Settings $settings
     * @param bool $hasIdentity
     */
    public function __construct(Client $httpClient, Settings $settings, $hasIdentity)
    {
        $this->httpClient = $httpClient;
        $this->settings = $settings;
        $this->hasIdentity = $hasIdentity;
    }

    /**
     * Prepare authentication on an external server.
     *
     * @param string $provider
     * @return array
     */
    public function __invoke($provider)
    {
        switch ($provider) {
            case 'ebsco':
                return $this->authenticateEbsco();
            default:
                return [];
        }
    }

    /**
     * Get authentication and session headers from username and password.
     *
     * Use the process find in Vufind: there are various possible process,
     * various version, various api and the documentation is private.
     * Warning: the authentication url is not the same than the session url…
     *
     * The session has a timeout of 480 seconds
     *
     * @return array
     */
    protected function authenticateEbsco()
    {
        /* TODO Save authentication in session. The session is 480 seconds by default (account admin). */
        static $authentication;

        // Avoid multiple authentication during the same query.
        if (!is_null($authentication)) {
            return $authentication;
        }

        $settings = $this->settings;

        // Check if there is a non expired session token.
        $token = $settings->get('external_ebsco_token');
        if ($token) {
            if (time() < $token['expire']) {
                $authentication = $token['token'];
                return $authentication;
            }
            // Reset in case of issue.
            $settings->set('external_ebsco_token', null);
        }

        $authentication = [];

        // Check if there is username and password, else this is an ip auth.
        $authToken = null;
        $username = $settings->get('external_ebsco_username');
        $password = $settings->get('external_ebsco_password');
        if ($username && $password) {
            $orgId = $settings->get('external_ebsco_organization_identifier');
            $authToken = $this->authenticateEbscoAuth($username, $password, $orgId);
            if (empty($authToken)) {
                return $authentication;
            }
        }

        $profile = $settings->get('external_ebsco_profile');
        $sessionToken = $this->authenticateEbscoSession($profile, $authToken);
        if (empty($sessionToken)) {
            return $authentication;
        }

        if ($authToken) {
            $authentication['x-authenticationToken'] = $authToken;
        }
        $authentication['x-sessionToken'] = $sessionToken;

        $settings->set('external_ebsco_token', [
            'expire' => time() + self::EBSCO_TIMEOUT,
            'token' => $authentication,
        ]);

        return $authentication;
    }

    /**
     * Get the authentication token for ebsco.
     *
     * @param string $username
     * @param string $password
     * @param string $orgId
     * @return string
     */
    protected function authenticateEbscoAuth($username, $password, $orgId = null)
    {
        $authUrl = 'https://eds-api.ebscohost.com/Authservice/rest';

        /** @var \Zend\Http\Client $client */
        $client = $this->httpClient;
        $client->resetParameters();
        $client->setMethod(\Zend\Http\Request::METHOD_POST);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];
        $client->setHeaders($headers);
        $client->setEncType('application/json; charset=utf-8');
        $client->setUri($authUrl . '/uidauth');

        $params = [];
        $params['UserId'] = $username;
        $params['Password'] = $password;
        if ($orgId) {
            $params['orgid'] = $orgId;
        }
        $client->setRawBody(json_encode($params));

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            // throw new \SearchExternalException($response);
            $this->getController()->logger()
                ->err(new Message('Unable to connect: %s', // @translate
                    $e));
            return;
        }
        $result = json_decode($response->getBody(), true);
        if (!$response->isSuccess()) {
            // throw new \SearchExternalException($response);
            $this->getController()->logger()
                ->err(new Message('External search error (%s): %s', // @translate
                    $result['ErrorNumber'], $result['DetailedErrorDescription']));
            return;
        }

        return $result['AuthToken'];
    }

    /**
     * Get the session token for ebsco.
     *
     * @param string $profile
     * @param string $authToken Not required for ip auth.
     * @return string
     */
    protected function authenticateEbscoSession($profile, $authToken = null)
    {
        $authUrl = 'https://eds-api.ebscohost.com/edsapi/rest';

        /** @var \Zend\Http\Client $client */
        $client = $this->httpClient;
        $client->resetParameters();
        $client->setMethod(\Zend\Http\Request::METHOD_GET);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Accept-Encoding' => 'gzip,deflate',
        ];
        if ($authToken) {
            $headers['x-authenticationToken'] = $authToken;
        }
        $client->setHeaders($headers);
        $client->setEncType('application/json; charset=utf-8');
        $client->setUri($authUrl . '/createsession');

        // A guest on ebsco is an anonymous people.
        // Anyway, this is not really manageable for ip auth.
        $isGuest = (empty($authToken) || $this->hasIdentity) ? 'n' : 'y';
        $params = [
            'profile' => $profile,
            'guest' => $isGuest,
        ];
        $client->setParameterGet($params);

        $response = $client->send();
        $result = json_decode($response->getBody(), true);
        if (!$response->isSuccess()) {
            // throw new \SearchExternalException($response);
            $this->getController()->logger()
                ->err(new Message('External search error (%s): %s',  // @translate
                    $result['ErrorNumber'], $result['DetailedErrorDescription']));
            return;
        }

        return $result['SessionToken'];
    }
}
