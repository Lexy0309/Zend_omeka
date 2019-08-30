<?php
namespace GuestUser\Service\ControllerPlugin;

use GuestUser\Mvc\Controller\Plugin\SendSms;
use Interop\Container\ContainerInterface;
use Zend\Http\Request;
use Zend\ServiceManager\Factory\FactoryInterface;

class SendSmsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $baseUrl = $settings->get('guestuser_phone_url', 'https://api.infobip.com/');
        $apiKey = $settings->get('guestuser_phone_api_key', '');

        $headers = [];
        $headers['Authorization'] = 'App ' . $apiKey;
        $headers['Content-type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        $client = $services
            ->get('Omeka\HttpClient')
            ->setHeaders($headers)
            ->setMethod(Request::METHOD_POST);
        return new SendSms(
            $client,
            $baseUrl,
            $services->get('Omeka\Logger')
        );
    }
}
