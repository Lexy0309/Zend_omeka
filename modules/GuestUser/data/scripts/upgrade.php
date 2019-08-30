<?php
namespace GuestUser;


/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '0.1.3', '<')) {
    foreach ($config[$space]['config'] as $name => $value) {
        $oldName = str_replace('guestuser_', 'guest_user_', $name);
        $settings->set($name, $settings->get($oldName, $value));
        $settings->delete($oldName);
    }
}

if (version_compare($oldVersion, '0.1.4', '<')) {
    $sql = <<<'SQL'
ALTER TABLE guest_user_tokens RENAME TO guest_user_token, ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE guest_user_token CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE token token VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE confirmed confirmed TINYINT(1) NOT NULL;
ALTER TABLE guest_user_token ADD CONSTRAINT FK_80ED0AF2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;
CREATE INDEX IDX_80ED0AF2A76ED395 ON guest_user_token (user_id);
SQL;
    $connection->exec($sql);;
}

if (version_compare($oldVersion, '3.2.0', '<')) {
    $this->resetAgreementsBySql($serviceLocator, true);

    $settings->set(
        'guestuser_terms_text',
        $config[$space]['config']['guestuser_terms_text']
    );
    $settings->set(
        'guestuser_terms_page',
        $config[$space]['config']['guestuser_terms_page']
    );
    $settings->set(
        'guestuser_terms_request_regex',
        $config[$space]['config']['guestuser_terms_request_regex']
    );
}
