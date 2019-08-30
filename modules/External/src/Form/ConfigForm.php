<?php
namespace External\Form;

use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'external_create_item',
            'type' => Element\Radio::class,
            'options' => [
                'label' =>  'Create item',  // @translate
                'info' => 'Often, the url to the pdf is not available directly, but exists.', // @translate
                'value_options' => [
                    'metadata' => 'When there are metadata, even without link', // @translate
                    'record_url' => 'When the record has a link to get the direct link to a file (search limited to pdf)', // @translate
                    'file_url' => 'When the record has a direct link to a file', // @translate
                    'never' => 'Never', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'external_create_item',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_username',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Account username', // @translate
                'info' => 'Remove it for ip authentication.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_username',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_password',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Account password', // @translate
                'info' => 'Remove it for ip authentication.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_password',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_organization_identifier',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Organization identifier', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_organization_identifier',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_profile',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Api profile', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_profile',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_filter',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Filter searches', // @translate
                'value_options' => [
                    'pdf' => 'Pdf only ("AND FM P")', // @translate
                    'ebook' => 'Ebook only (pdf or epub: "AND PT ebook")', // @translate
                    'fulltext' => 'Full text only ("AND FT y")', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'external_ebsco_filter',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_query_parameters',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Ebsco query parameters', // @translate
                'info' => 'Allows to specify the query for Ebsco (url parameters).',
            ],
            'attributes' => [
                'id' => 'external_ebsco_query_parameters',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_ebook',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Ebsco ebooks', // @translate
                'info' => 'Allows to search external ebooks from ebsco (require a specific viewer); useless if ebooks are imported.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_ebook',
            ],
        ]);
        $this->add([
            'name' => 'external_pagination_per_page',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Pagination per page', // @translate
                'info' => 'Maximum number of resources to fetch by page in an external repository.', // @translate
            ],
            'attributes' => [
                'id' => 'external_pagination_per_page',
            ],
        ]);
        $this->add([
            'name' => 'external_number_of_pages',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Number of pages', // @translate
                'info' => 'Maximum number of pages to fetch in an external repository.', // @translate
            ],
            'attributes' => [
                'id' => 'external_number_of_pages',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_max_fetch_items',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Max items to fetch', // @translate
                'info' => 'Max number of items to fetch asynchronously after a search, in order to prepare files quickly. "0" means disabled.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_max_fetch_items',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_max_fetch_jobs',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Max fetch jobs', // @translate
                'info' => 'Max number of fetch jobs to launch in background, in order to avoid overloading. "0" means disabled', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_max_fetch_jobs',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_process_existing',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Fetch existing items', // @translate
                'info' => 'Launch a process for existing items, not only new ones.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_process_existing',
            ],
        ]);
        $this->add([
            'name' => 'external_ebsco_disable',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Disable ebsco search', // @translate
                'info' => 'To be used only when the ebsco endpoint has issues.', // @translate
            ],
            'attributes' => [
                'id' => 'external_ebsco_disable',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'external_ebsco_filter',
            'required' => false,
        ]);
    }
}
