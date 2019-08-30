<?php
namespace VatiruLibrary;

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
 * @var array $config
 * @var array $config
 * @var \Omeka\Mvc\Controller\Plugin\Api $api
 */
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');

if (version_compare($oldVersion, '3.4.0', '<')) {
    // Set all items to 0 exemplars, since there is an unlimited number of
    // exemplars for authenticated users.
    $propertyTotalExemplarsId = $api
        ->searchOne('properties', ['term' => 'download:totalExemplars'])->getContent()
        ->id();
    $sql = <<<SQL
UPDATE value SET value = "0" WHERE property_id = $propertyTotalExemplarsId;
SQL;
    $connection->exec($sql);

    // Set public all items (Download Manager controls access to files).
    $sql = <<<SQL
UPDATE resource SET is_public = "1" WHERE `resource_type` != 'Omeka\\Entity\\ItemSet';
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.4.1', '<')) {
    // Remove all credentials.
    $sql = <<<SQL
DELETE FROM credential;
SQL;
    $connection->exec($sql);

    // Remove all api keys.
    $sql = <<<SQL
DELETE FROM api_key WHERE label IN ("server_key", "main", "user_key");
SQL;
    $connection->exec($sql);

    // Remove all prepared downloads.
    $sql = <<<SQL
DELETE FROM download WHERE status = "ready";
SQL;
    $connection->exec($sql);

    // Create new api keys for all users.
    $userApiKeys = $plugins->get('userApiKeys');
    $users = $api->search('users', ['limit' => 1000], ['responseContent' => 'resource'])->getContent();
    foreach ($users as $user) {
        $userApiKeys($user);
    }
}

if (version_compare($oldVersion, '3.5.0', '<')) {
    // Add the property "vatiru:isExternal".
    $vocabulary = $api
        ->searchOne('vocabularies', ['prefix' => 'vatiru'])->getContent();
    $vocabularyId = $vocabulary->id();
    $ownerId = $vocabulary->owner()->id();
    $sql = <<<SQL
INSERT INTO property
(owner_id, vocabulary_id, local_name, label, comment)
VALUES
($ownerId, $vocabularyId, "isExternal", "Is external", "Specify if the item is managed externally or not."),
($ownerId, $vocabularyId, "externalData", "External data", "Cache for external data."),
($ownerId, $vocabularyId, "externalSource", "External source", "Url to the external source.")
;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.5.1', '<')) {
    // Add the property "vatiru:isExternal".
    $vocabulary = $api
        ->searchOne('vocabularies', ['prefix' => 'vatiru'])->getContent();
    $vocabularyId = $vocabulary->id();
    $ownerId = $vocabulary->owner()->id();
    $sql = <<<SQL
INSERT INTO property
(owner_id, vocabulary_id, local_name, label, comment)
VALUES
($ownerId, $vocabularyId, "userQuery", "User query", "The query of a user.")
;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.5.2', '<')) {
    // Add the property "vatiru:resourcePriority".
    $vocabulary = $api
        ->searchOne('vocabularies', ['prefix' => 'vatiru'])->getContent();
    $vocabularyId = $vocabulary->id();
    $ownerId = $vocabulary->owner()->id();
    $sql = <<<SQL
INSERT INTO property
(owner_id, vocabulary_id, local_name, label, comment)
VALUES
($ownerId, $vocabularyId, "resourcePriority", "Resource Priority", "The priority of a resource used in Vatiru, for example “100 book”, “200 article”, “999 undefined”.")
;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.5.3', '<')) {
    $vocabulary = $api
        ->searchOne('vocabularies', ['prefix' => 'vatiru'])->getContent();
    $vocabularyId = $vocabulary->id();
    $ownerId = $vocabulary->owner()->id();

    // Modify the property "vatiru:resourcePriority".
    $sql = <<<SQL
UPDATE property
SET comment = "The priority of a resource used in Vatiru (for example “100” (book), “200” (article), “999” (undefined))."
WHERE vocabulary_id = $vocabularyId AND local_name = "resourcePriority";
SQL;
    $connection->exec($sql);

    // Add the property "vatiru:sourceRepository".
    $sql = <<<SQL
INSERT INTO property
(owner_id, vocabulary_id, local_name, label, comment)
VALUES
($ownerId, $vocabularyId, "sourceRepository", "Source repository", "The repository that is the source of the data.")
;
SQL;
    $connection->exec($sql);

    // Add the property "vatiru:publicationType".
    $sql = <<<SQL
INSERT INTO property
(owner_id, vocabulary_id, local_name, label, comment)
VALUES
($ownerId, $vocabularyId, "publicationType", "Publication type", "The main publication type of a resource used in Vatiru (for example “book”, “article”, “academic”, “news”).")
;
SQL;
    $connection->exec($sql);

    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:sourceRepository'])->getContent();
    $sourceRepositoryId = $property->id();
    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:publicationType'])->getContent();
    $publicationTypeId = $property->id();
    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:resourcePriority'])->getContent();
    $resourcePriorityId = $property->id();

    // Fill the new values for all the items (source repository and publication
    // type), then update all existing (priority only).
    $sql = <<<SQL
INSERT INTO value (resource_id, property_id, value_resource_id, type, lang, value, uri)
SELECT resource_id, $sourceRepositoryId, value_resource_id, type, lang, "ebsco", uri
FROM value
WHERE property_id = $resourcePriorityId;

INSERT INTO value (resource_id, property_id, value_resource_id, type, lang, value, uri)
SELECT resource_id, $publicationTypeId, value_resource_id, type, lang, TRIM(SUBSTRING(value, 5)), uri
FROM value
WHERE property_id = $resourcePriorityId;

UPDATE value
SET value = TRIM(SUBSTRING(value, 1, 3))
WHERE property_id = $resourcePriorityId;
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }

    // Fill the vatiru type and priority for all local items.
    // The process uses two queries: it's simpler to remove all duplicate values
    // and it's quicker, since there are less than 10000 books.
    $type = 'book';
    $priority = '100';
    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:isExternal'])->getContent();
    $isExternalId = $property->id();
    $sql = <<<SQL
INSERT INTO value (resource_id, property_id, value_resource_id, type, lang, value, uri)
SELECT resource_id, $publicationTypeId, value_resource_id, type, lang, "$type", uri
FROM value
WHERE resource_id IN (
    SELECT DISTINCT(resource.id)
    FROM item INNER JOIN resource ON item.id = resource.id
    LEFT JOIN value ON resource.id = value.resource_id AND (value.property_id = $isExternalId AND (value.value = "1" OR value.uri = "1"))
    WHERE value.id IS NULL
    GROUP BY resource.id
);

INSERT INTO value (resource_id, property_id, value_resource_id, type, lang, value, uri)
SELECT resource_id, $resourcePriorityId, value_resource_id, type, lang, "$priority", uri
FROM value
WHERE resource_id IN (
    SELECT DISTINCT(resource.id)
    FROM item INNER JOIN resource ON item.id = resource.id
    LEFT JOIN value ON resource.id = value.resource_id AND (value.property_id = $isExternalId AND (value.value = "1" OR value.uri = "1"))
    WHERE value.id IS NULL
    GROUP BY resource.id
);

DELETE FROM value
WHERE id NOT IN (
    SELECT id FROM (
        SELECT MIN(id) as id
        FROM value
        GROUP BY resource_id, property_id, value_resource_id, type, lang, value, uri
    ) AS value_keep
);
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.5.5', '<')) {
    // Add the property "dcterms:type" = "book" for all local items.
    $property = $api
        ->searchOne('properties', ['term' => 'dcterms:type'])->getContent();
    $dctermsTypeId = $property->id();
    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:isExternal'])->getContent();
    $isExternalId = $property->id();
    $property = $api
        ->searchOne('properties', ['term' => 'vatiru:resourcePriority'])->getContent();
    $resourcePriorityId = $property->id();
    $sql = <<<SQL
INSERT INTO value (resource_id, property_id, type, value)
SELECT resource.id, $dctermsTypeId, "literal", "dctype:Text"
FROM item
INNER JOIN resource ON item.id = resource.id
LEFT JOIN value ON resource.id = value.resource_id AND (value.property_id = $isExternalId AND value.id IS NOT NULL)
WHERE value.id IS NULL;

INSERT INTO value (resource_id, property_id, type, value)
SELECT resource.id, $dctermsTypeId, "literal", "bibo:Book"
FROM item
INNER JOIN resource ON item.id = resource.id
LEFT JOIN value ON resource.id = value.resource_id AND (value.property_id = $isExternalId AND value.id IS NOT NULL)
WHERE value.id IS NULL;

DELETE FROM value
WHERE resource_id IN (
    SELECT id FROM (
        SELECT DISTINCT item.id
        FROM item
        INNER JOIN resource ON item.id = resource.id
        LEFT JOIN value value2 ON resource.id = value2.resource_id AND (value2.property_id = $isExternalId AND value2.id IS NOT NULL)
        WHERE value2.id IS NULL
    ) rid
) AND property_id = $resourcePriorityId;

INSERT INTO value (resource_id, property_id, type, value)
SELECT resource.id, $resourcePriorityId, "literal", "050"
FROM item
INNER JOIN resource ON item.id = resource.id
LEFT JOIN value ON resource.id = value.resource_id AND (value.property_id = $isExternalId AND value.id IS NOT NULL)
WHERE value.id IS NULL;

DELETE FROM value
WHERE id NOT IN (
    SELECT id FROM (
        SELECT MIN(id) as id
        FROM value
        GROUP BY resource_id, property_id, value_resource_id, type, lang, value, uri
    ) AS value_keep
);
SQL;
    $sqls = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($sqls as $sql) {
        $connection->exec($sql);
    }
}

if (version_compare($oldVersion, '3.5.6', '<')) {
    $sql = <<<SQL
UPDATE user_setting SET value = "1" WHERE `id` = "csvimport_rows_by_batch";
SQL;
    $connection->exec($sql);
}
