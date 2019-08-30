<?php
namespace DownloadManager\Service;

use GuestUserIp\Authentication\Adapter\PasswordOrIpAdapter;
use GuestUser\Authentication\Adapter\PasswordAdapter as GuestUserPasswordAdapter;
use GuestUser\Entity\GuestUserToken;
use Interop\Container\ContainerInterface;
use Omeka\Authentication\Adapter\KeyAdapter;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Omeka\Module\Manager as ModuleManager;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\Callback;
use Zend\Authentication\Storage\NonPersistent;
use Zend\Authentication\Storage\Session;
use Zend\Http\PhpEnvironment\RemoteAddress;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Authentication service factory.
 */
class AuthenticationServiceFactory implements FactoryInterface
{
    /**
     * @var bool
     */
    protected $isDownloadRequestWithKey;

    /**
     * Create the authentication service for DownloadController.
     *
     * The authentication service is by key when there is a key, else session.
     *
     * This authentication service is required because some actions of the
     * DownloadController can be authenticated only with a specific check.
     *
     * @return AuthenticationService
     * @see \Omeka\Service\AuthenticationServiceFactory
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $status = $services->get('Omeka\Status');

        // Skip auth retrieval entirely if we're installing or migrating.
        if (!$status->isInstalled() ||
            ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $storage = new NonPersistent;
            $adapter = new Callback(function () {
                return null;
            });
        } else {
            $userRepository = $entityManager->getRepository(User::class);
            if ($this->isDownloadRequestWithKey($services)) {
                // Authenticate using key for Download requests with credential key.
                $keyRepository = $entityManager->getRepository(ApiKey::class);
                $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
                $adapter = new KeyAdapter($keyRepository, $entityManager);
            } elseif ($status->isApiRequest()) {
                // Authenticate using key for API requests.
                $keyRepository = $entityManager->getRepository(ApiKey::class);
                $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
                $adapter = new KeyAdapter($keyRepository, $entityManager);
            } else {
                $moduleManager = $services->get('Omeka\ModuleManager');
                // Check if the module GuestUserIp is active and ip listed.
                $module = $moduleManager->getModule('GuestUserIp');
                if ($module && $module->getState() === ModuleManager::STATE_ACTIVE
                    && ($email = $this->isIpUser($services))
                ) {
                    $storage = new DoctrineWrapper(new Session, $userRepository);
                    $adapter = new PasswordOrIpAdapter($userRepository, $email);
                } else {
                    // Check if the module GuestUser is active to add its tokens.
                    $module = $moduleManager->getModule('GuestUser');
                    if ($module && $module->getState() === ModuleManager::STATE_ACTIVE) {
                        $storage = new DoctrineWrapper(new Session, $userRepository);
                        $adapter = new GuestUserPasswordAdapter($userRepository);
                        $adapter->setTokenRepository($entityManager->getRepository(GuestUserToken::class));
                    } else {
                        // Authenticate using user/password for all other requests.
                        $storage = new DoctrineWrapper(new Session, $userRepository);
                        $adapter = new PasswordAdapter($userRepository);
                    }
                }
            }
        }

        $authService = new AuthenticationService($storage, $adapter);
        return $authService;
    }

    /**
     * Check whether the current HTTP request has the credential keys.
     *
     * @see \Omeka\Mvc\Status
     * @param ContainerInterface $services
     * @return bool
     */
    protected function isDownloadRequestWithKey(ContainerInterface $services)
    {
        if (is_null($this->isDownloadRequestWithKey)) {
            $request = $services->get('Request');
            $this->isDownloadRequestWithKey =
                !is_null($request->getQuery('key_identity'))
                && !is_null($request->getQuery('key_credential'));
        }
        return $this->isDownloadRequestWithKey;
    }

    /**
     * Check if a user is ip authenticated, and return the email.
     *
     * @param ContainerInterface $services
     * @return string|null
     */
    protected function isIpUser(ContainerInterface $services)
    {
        $ip = $this->getClientIp();
        $ipUserList = $services->get('Omeka\Settings')->get('guestuserip_list_range', []);

        // Check a single ip.
        if (isset($ipUserList[$ip])) {
            return $ipUserList[$ip]['email'];
        }

        // Check an ip range.
        $ipLong = ip2long($ip);
        foreach ($ipUserList as $range) {
            if ($ipLong >= $range['low'] && $ipLong <= $range['high']) {
                return $range['email'];
            }
        }

        return null;
    }

    /**
     * Get the ip of the client.
     *
     * @return string
     */
    protected function getClientIp()
    {
        $ip = (new RemoteAddress())->getIpAddress();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            return $ip;
        }
        return '::';
    }
}
