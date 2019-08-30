<?php
namespace VatiruLibrary\Job;

use Omeka\Job\AbstractJob;

/**
 * Fix old metadata imported with the old spreadsheet.
 *
 * TODO To be enabled in cron and disabled in a near future.
 */
class FixMetadataViaDirectQuery extends AbstractJob
{
    public function perform()
    {
        // $jobId = $this->getArg('jobId');
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $api = $controllerPluginManager ->get('api');

        // Set resource class "bibo:Book" (40) to all null or Text (31) items.
        $biboVocabulary = $api->read('vocabularies', ['prefix' => 'bibo'])->getContent();
        $biboBook = $api->read('resource_classes', [
            'vocabulary' => $biboVocabulary->id(),
            'localName' => 'Book',
        ])->getContent();
        $biboBookId = (int) $biboBook->id();
        $dctypeText = $api->searchOne('resource_classes', ['term' => 'dctype:Text'])->getContent();
        $dctypeTextId = (int) $dctypeText->id();
        $sql = <<<SQL
UPDATE resource
SET resource_class_id = $biboBookId
WHERE resource_type = "Omeka\\\\Entity\\\\Item"
AND (resource_class_id IS NULL OR resource_class_id = $dctypeTextId);
SQL;
        $connection->exec($sql);

        // Move number of pages from dcterms:format (9) to bibo:numPages (106).
        $dctermsFormat = $api->searchOne('properties', ['term' => 'dcterms:format'])->getContent();
        $biboNumPages = $api->searchOne('properties', ['term' => 'bibo:numPages'])->getContent();
        $dctermsFormatId = (int) $dctermsFormat->id();
        $biboNumPagesId = (int) $biboNumPages->id();
        // The process uses two queries: it's simpler to remove all duplicate
        // values (and it's quick, since there are less than 10000 books).
        // For compatibility with third parties, dcterms:format is kept.
/*
UPDATE value
SET property_id = $biboNumPagesId, value = trim(SUBSTRING(value, 1, CHAR_LENGTH(value) - 6))
WHERE property_id = $dctermsFormatId
AND value LIKE "% pages";
*/
        $sql = <<<SQL
INSERT INTO value (resource_id, property_id, value_resource_id, type, lang, value, uri)
SELECT resource_id, $biboNumPagesId, value_resource_id, type, lang, TRIM(SUBSTRING(value, 1, CHAR_LENGTH(value) - 6)), uri
FROM value
WHERE property_id = $dctermsFormatId AND value LIKE "% pages";

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
}
