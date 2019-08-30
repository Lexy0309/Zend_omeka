<?php
namespace DerivativeImagesEbsco\Form;

use Doctrine\DBAL\Connection;
use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function init()
    {
        $this->add([
            'name' => 'ingesters',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Ingesters to process', // @translate
                'empty_option' => 'All ingesters', // @translate
                'value_options' => $this->listIngesters(),
            ],
            'attributes' => [
                'id' => 'ingesters',
                'class' => 'chosen-select',
                'multiple' => true,
                'placehoder' => 'Select ingesters to process', // @ translate
                'data-placeholder' => 'Select ingesters to process', // @ translate
            ],
        ]);

        $this->add([
            'name' => 'renderers',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Renderers to process', // @translate
                'empty_option' => 'All renderers', // @translate
                'value_options' => $this->listRenderers(),
            ],
            'attributes' => [
                'id' => 'renderers',
                'class' => 'chosen-select',
                'multiple' => true,
                'placehoder' => 'Select renderers to process', // @ translate
                'data-placeholder' => 'Select renderers to process', // @ translate
            ],
        ]);

        $this->add([
            'name' => 'media_types',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Media types to process', // @translate
                'empty_option' => 'All media types', // @translate
                'value_options' => $this->listMediaTypes(),
            ],
            'attributes' => [
                'id' => 'media_types',
                'class' => 'chosen-select',
                'multiple' => true,
                'placehoder' => 'Select media types to process', // @ translate
                'data-placeholder' => 'Select media types to process', // @ translate
            ],
        ]);

        $this->add([
            'name' => 'publication_types',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Publication types to process', // @translate
                'empty_option' => 'All publication types', // @translate
                'value_options' => [
                    'book' => 'Books', // @translate
                    'article' => 'Articles', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'publication_types',
                'class' => 'chosen-select',
                'multiple' => true,
                'placehoder' => 'Select books or articles', // @ translate
                'data-placeholder' => 'Select books or articles', // @ translate
            ],
        ]);

        $this->add([
            'name' => 'skip_check_existing_thumbnails',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Skip check of existing thumbnails', // @translate
                'info' => 'On some systems like amazon, direct access to files can be slow, so the check can be skipped.', // @translate
            ],
            'attributes' => [
                'id' => 'skip_check_existing_thumbnails',
            ],
        ]);

        $this->add([
            'name' => 'original_external_media',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Fetch mode for external media', // @translate
                'info' => 'This option avoids to fetch all existing articles in the database.', // @translate
                'value_options' => [
                    'with_original_only' => 'Only if marked as original', // @translate
                    'with_original_and_file_only' => 'Only if marked as original and with a file (no fetch)', // @translate
                    'with_original_and_no_file_only' => 'Only if marked as original and without a file', // @translate
                    'without_original_only' => 'Only if not marked as original', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'original_external_media',
            ],
        ]);

        $this->add([
            'name' => 'process',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Launch the process to create derivative images in the background', // @translate
            ],
        ]);

        $this->add([
            'name' => 'minimum_id',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'MInimum id', // @translate
                'info' => 'Avoids to reprocess all medias in case of an error.', // @translate
            ],
            'attributes' => [
                'id' => 'minimum_id',
                'value' => '0',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'ingesters',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'renderers',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'media_types',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'publication_types',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'original_external_media',
            'required' => false,
        ]);
    }

    /**
     * @return array
     */
    protected function listIngesters()
    {
        $sql = 'SELECT DISTINCT(ingester) FROM media ORDER BY ingester';
        $stmt = $this->getConnection()->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return ['' => 'All ingesters'] // @translate
            + array_combine($result, $result);
    }

    /**
     * @return array
     */
    protected function listRenderers()
    {
        $sql = 'SELECT DISTINCT(renderer) FROM media ORDER BY renderer';
        $stmt = $this->getConnection()->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return ['' => 'All renderers'] // @translate
            + array_combine($result, $result);
    }

    /**
     * @return array
     */
    protected function listMediaTypes()
    {
        $sql = 'SELECT DISTINCT(media_type) FROM media ORDER BY media_type';
        $stmt = $this->getConnection()->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        return ['' => 'All media types'] // @translate
            + array_combine($result, $result);
    }

    /**
     * @param Connection $connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
