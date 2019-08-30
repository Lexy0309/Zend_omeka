<?php
namespace DownloadManager;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Api\Manager $api
 * @var array $config
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$api = $services->get('Omeka\ApiManager');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';

if (version_compare($oldVersion, '3.1', '<')) {
    // Minimum expiration cannot be empty.
    $expiration = $settings->get('downloadmanager_download_expiration');
    if (empty($expiration)) {
        $settings->set('downloadmanager_download_expiration',
            $config['downloadmanager']['config']['downloadmanager_download_expiration']);
    }

    // Reset the pagination per page to 25 for some days.
    $settings->set('pagination_per_page', 25);

    // Remove the uniqueness of downloads (pasts are kept in table).
    $sql = <<<'SQL'
DROP INDEX resource_owner ON download;
CREATE INDEX resource_owner ON download (resource_id, owner_id);
SQL;
    $connection->exec($sql);

    // Reorder the fields (resource and owner first for doctrine).
    $sql = <<<'SQL'
ALTER TABLE `download`
CHANGE `resource_id` `resource_id` INT NOT NULL AFTER `id`,
CHANGE `owner_id` `owner_id` INT NOT NULL AFTER `resource_id`,
CHANGE `status` `status` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `owner_id`;

ALTER TABLE `download_log`
CHANGE `resource_id` `resource_id` INT NOT NULL AFTER `id`,
CHANGE `owner_id` `owner_id` INT NOT NULL AFTER `resource_id`,
CHANGE `status` `status` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `owner_id`;
SQL;
    $connection->exec($sql);

    // Copy back the old download logs as expired and clean it.
    $sql = <<<'SQL'
UPDATE download_log SET status = "expired";
INSERT download SELECT * FROM download_log;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE download_log;
ALTER TABLE download_log AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;
SQL;
    $connection->exec($sql);

    // The entity manager is not available for Download entities.
    $entityManager = $serviceLocator->get('Omeka\EntityManager');
    $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);
    $userRepository = $entityManager->getRepository(\Omeka\Entity\User::class);

    $sqlSelect = <<<'SQL'
SELECT * FROM download WHERE hash IS NULL OR hash = "";
SQL;
    $sqlUpdate = <<<'SQL'
UPDATE download SET hash = "%s", salt = "%s" WHERE id = %d;
SQL;

    $stmt = $connection->query($sqlSelect);
    while ($row = $stmt->fetch()) {
        $media = $mediaRepository->findOneBy(['item' => $row['resource_id']]);
        $owner = $userRepository->find($row['owner_id']);
        if (empty($media)) {
            $media = new \Omeka\Entity\Media;
            $length = 64;
            $randomString = hash('sha256', substr(str_shuffle(md5(time())), 0, $length));
            $media->setSha256($randomString);
        }
        if (empty($owner)) {
            $owner = $userRepository->find(1);
        }

        $hash = hash('sha256',
            $media->getId()
            // Prevent missing file hash.
            . ' ' . ($media->getSha256() ?: hash('sha256', $media->getSource() . ' ' . $media->getStorageId()))
            . ' ' . $owner->getEmail()
            . ' ' . $owner->getId()
            . (new \DateTime('now'))->format('Y-m-d H:i:s')
        );

        $length = 64;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $max)];
        }
        $salt = $randomString;

        $connection->exec(sprintf($sqlUpdate, $hash, $salt, $row['id']));
    }
}

if (version_compare($oldVersion, '3.1.1', '<')) {
    // Replace status "expired" by "past" and add a column "data".
    $sql = <<<'SQL'
UPDATE download_log SET status = "past" WHERE status = "expired";
UPDATE download SET status = "past" WHERE status = "expired";
ALTER TABLE download_log ADD data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)' AFTER expire;
ALTER TABLE download ADD data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)' AFTER expire;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.1.2', '<')) {
    // Replace "data" by "log".
    $sql = <<<'SQL'
ALTER TABLE download_log CHANGE data log LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
ALTER TABLE download CHANGE data log LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)';
UPDATE download_log SET log = CONCAT(SUBSTRING(log, 1, LENGTH(log) - 1), ',"date":"', modified, '"}') WHERE log = '{"action":"released"}' OR log = '{"action":"expired"}';
UPDATE download SET log = CONCAT(SUBSTRING(log, 1, LENGTH(log) - 1), ',"date":"', modified, '"}') WHERE log = '{"action":"released"}' OR log = '{"action":"expired"}';
UPDATE download_log SET log = CONCAT("[", log, "]") WHERE log IS NOT NULL AND log != "";
UPDATE download SET log = CONCAT("[", log, "]") WHERE log IS NOT NULL AND log != "";
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.1.3', '<')) {
    // Replace "data" by "log".
    $sql = <<<'SQL'
UPDATE download_log
SET log = CONCAT('[{"action":"downloaded","date":"', IF (modified IS NULL OR modified = "", created, modified), '"},{"action":"expiration","date":"', expire, '"}]')
WHERE status = "downloaded" AND (log IS NULL OR log = "");
UPDATE download
SET log = CONCAT('[{"action":"downloaded","date":"', IF (modified IS NULL OR modified = "", created, modified), '"},{"action":"expiration","date":"', expire, '"}]')
WHERE status = "downloaded" AND (log IS NULL OR log = "");

UPDATE download_log
SET log = CONCAT('[{"action":"downloaded","date":"', created, '"},{"action":"expiration","date":"', expire, '"},', SUBSTRING(log, 2))
WHERE `log` LIKE '[{\"action\":\"released%' OR `log` LIKE '[{\"action\":\"expired%';
UPDATE download
SET log = CONCAT('[{"action":"downloaded","date":"', created, '"},{"action":"expiration","date":"', expire, '"},', SUBSTRING(log, 2))
WHERE `log` LIKE '[{\"action\":\"released%' OR `log` LIKE '[{\"action\":\"expired%';

UPDATE download_log
SET log = CONCAT('[{"action":"held","date":"', created, '"}]')
WHERE status = "held" AND (log IS NULL OR log = "");
UPDATE download
SET log = CONCAT('[{"action":"held","date":"', created, '"}]')
WHERE status = "held" AND (log IS NULL OR log = "");
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.2.1', '<')) {
    $sql = <<<'SQL'
ALTER TABLE download_log ADD hash_password VARCHAR(64) DEFAULT NULL AFTER `hash`;
ALTER TABLE download ADD hash_password VARCHAR(64) DEFAULT NULL AFTER `hash`;
UPDATE download SET hash_password = hash;
UPDATE download_log SET hash_password = hash;
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.3.0', '<')) {
    // The entity manager is not available for Download entities.
    $entityManager = $serviceLocator->get('Omeka\EntityManager');
    $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);
    $userRepository = $entityManager->getRepository(\Omeka\Entity\User::class);

    $config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
    $uniqueUserKey = $config['downloadmanager']['config']['downloadmanager_unique_user_key'];
    $uniqueServerKey = $config['downloadmanager']['config']['downloadmanager_unique_server_key'];

    $hash = $uniqueUserKey . strrev($uniqueUserKey);
    $hashPassword = $hash;
    $salt = $uniqueServerKey . strrev($uniqueServerKey);

    // Remove all empty downloads (unused prepared downloads).
    $sql = <<<SQL
DROP INDEX UNIQ_781A8270D1B862B8 ON download;
DELETE FROM download WHERE log IS NULL;
UPDATE download
SET hash = "$hash", hash_password = "$hashPassword", salt = "$salt"
WHERE status NOT IN ("downloaded", "past");
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.4.0', '<')) {
    $config = require dirname(dirname(__DIR__)) . '/config/module.config.php';

    $settings->set('downloadmanager_public_visibility',
        $config['downloadmanager']['config']['downloadmanager_public_visibility']);
}

// The upgrade to 3.4.1 is done via the module VatiruLibrary because the plugin
// userApiKeys is not available.

if (version_compare($oldVersion, '3.4.4', '<')) {
    $remove = [
        'download_manager_access_route_site_media',
        'download_manager_certificate_path',
        'download_manager_credential_key_path',
        'download_manager_download_expiration',
        'download_manager_encrypter_path',
        'download_manager_max_copies_by_user',
        'download_manager_max_simultaneous_copies_by_user',
        'download_manager_owner_password',
        'download_manager_pdf_permissions',
        'download_manager_pfx_password',
        'download_manager_public_visibility',
        'download_manager_show_availability',
        'download_manager_sign_append_block',
        'download_manager_sign_comment',
        'download_manager_sign_image_path',
        'download_manager_sign_location',
        'download_manager_sign_reason',
        'download_manager_signer_path',
    ];
    foreach ($remove as $key) {
        $settings->delete($key);
    }

    // Remove all empty downloads (unused prepared downloads).
    $sql = <<<SQL
DELETE FROM download WHERE log IS NULL;
DELETE FROM download_log WHERE log IS NULL;
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}
