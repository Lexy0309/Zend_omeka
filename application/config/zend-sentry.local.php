<?php
/**
 * ZendSentry Configuration
 *
 * If you have a ./config/autoload/ directory set up for your project, you can
 * drop this config file in it, remove the .dist extension add your configuration details.
 */
$settings = array(
    /**
     * Your Sentry API key
     */
    'sentry-api-key' => '01d4eea600e0413fa741f6b6a18b2b2a9e4b8b61d3434b919e1602891a3ca90f',
);

/**
 * You do not need to edit below this line
 */
return array(
    'zend-sentry' => $settings,
);