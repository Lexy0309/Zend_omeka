<?php
namespace DownloadManager\Form;

use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Element;
use Zend\Form\Form;
use Zend\View\Helper\Url;
use Omeka\Form\Element\ItemSetSelect;

class ConfigForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init()
    {
        $urlHelper = $this->getUrlHelper();

        // Main parameters.

        $this->add([
            'name' => 'downloadmanager_public_visibility',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Visibility of public resources', // @translate
                'info' => 'If checked, the public resources will be available for public, else the value "-1" will be required in property "download:totalExemplars".', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_download_expiration',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Download expiration', // @translate
                'info' => 'Number of seconds before expiration of a downloaded file (default: 30 days = 2592000 seconds).', // @translate
            ],
            'attributes' => [
                'min' => '86400',
                // Default step is one day.
                'step' => '86400',
            ],
        ]);

        // $this->add([
        //     'name' => 'downloadmanager_offline_link_expiration',
        //     'type' => Element\Number::class,
        //     'options' => [
        //         'label' => 'Offline link expiration', // @translate
        //         'info' => 'Number of seconds before expiration of a offline link file (default: 1 day = 86400 seconds).', // @translate
        //     ],
        //     'attributes' => [
        //         'min'  => '0',
        //         // Default step is one day.
        //         'step' => '86400',
        //     ],
        // ]);

        $this->add([
            'name' => 'downloadmanager_max_copies_by_user',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Max copies by user', // @translate
                'info' => 'Maximum copies downloadable by a user (0 means unlimited).', // @translate
            ],
            'attributes' => [
                'min' => '0',
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_max_simultaneous_copies_by_user',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Max simultaneous copies by user', // @translate
                'info' => 'Maximum copies downloadable simultaneously by a user (0 means unlimited).', // @translate
            ],
            'attributes' => [
                'min' => '0',
            ],
        ]);

        // Credentials.

        $this->add([
            'name' => 'downloadmanager_credential_key_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Credentials key file', // @translate
                'info' => 'The path to the file used to crypt credentials.', // @translate
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        // Signature.

        $this->add([
            'name' => 'downloadmanager_certificate_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'PFX certificate file', // @translate
                'info' => 'The path to the x509 pfx certificate file.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_pfx_password',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'PFX password', // @translate
                'info' => 'The password of the pfx certificate file.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_sign_reason',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sign reason', // @translate
                'info' => 'If any, this reason will be added when a pdf file will be signed (user name, server name, and date may be added).', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_sign_location',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sign location', // @translate
                'info' => 'If any, this location will be added when a pdf file will be signed.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_sign_append_block',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sign append block', // @translate
                'info' => 'A page can be added at the end of the pdf file to display a block with a logo and the details of the signature.' // @translate
                    . ' ' . 'The text will be in one of these languages: cs, de, en, it, pl, es.' // @translate
                    . ' ' . 'If empty, the signature will be displayed only via the properties of the document.', // @translate'
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_sign_comment',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sign comment', // @translate
                'info' => 'If any, this comment will be added when a pdf file will be signed (user name, server name, and date may be added).', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_sign_image_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Sign image', // @translate
                'info' => 'If any, this image will be added when a pdf file will be signed.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_signer_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Signer path', // @translate
                'info' => 'The pdf files are signed with PortableSigner, that should be installed.' // @translate
                    . ' ' . 'Set the name of the executable or the full path.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_skip_signing_signed_file',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Skip signing signed files', // @translate
                'info' => 'If checked, the files that are signed won’t be signed with the key above.', // @translate
            ],
        ]);

        // Encryption.

        $this->add([
            'name' => 'downloadmanager_owner_password',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Master password', // @translate
                'info' => 'This password allows to set the permissions on files.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_pdf_permissions',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'PDF permissions', // @translate
                'info' => 'When a master password is set, the permissions of files can be limited with this comma or space separated values:' // @translate
                    . ' ' . 'Printing, DegradedPrinting, ModifyContents, Assembly, CopyContents, ScreenReaders, ModifyAnnotations, FillIn, AllFeatures.' // @translate
                    . ' ' . 'If empty, only reading will be allowed.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_send_unencryptable',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Send clear unencryptable files', // @translate
                'info' => 'If checked, the files that are not encryptable (not standard, too big…) will be send clear.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_encrypter_path',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Encrypter path', // @translate
                'info' => 'The pdf files are encrypted with pdftk, that should be installed.' // @translate
                    . ' ' . 'Set the name of the executable or the full path.', // @translate
            ],
        ]);

        // Reports.

        $this->add([
            'name' => 'downloadmanager_report_recipients',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Recipients of reports', // @translate
                'resource_value_options' => [
                    'resource' => 'users',
                    'query' => [],
                    'option_text_callback' => function ($user) {
                        return sprintf('%s (%s)', $user->email(), $user->name());
                    },
                ],
                'empty_option' => '',
            ],
            'attributes' => [
                'id' => 'select-owner',
                'value' => '',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select users…', // @translate
                'data-api-base-url' => $urlHelper('api/default', ['resource' => 'users']),
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_report_threshold_limit',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Availability threshold (percent)', // @translate
                'info' => 'Triggers when book availability approaches a threshold', // @translate
            ],
            'attributes' => [
                'min' => '0',
                'max' => '100',
            ],
        ]);

        // Various

        $this->add([
            'name' => 'downloadmanager_show_availability',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Show availability form automatically', // @translate
                'info' => 'If checked, the partial will be automatically appended to the item view, else the helper should be called in the theme.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_access_route_site_media',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Access to media', // @translate
                'info' => 'If checked, the media pages will be reachable on the public site.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_show_media_terminal',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Display pdf as full window', // @translate
                'info' => 'If checked, the media page will be a full window, without layout (header, margin, footer).', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_notification_availability_subject',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Notification for availability (subject)', // @translate
                'info' => 'When a file is released or expired, it becomes available for the user who hold it. This text is the subject of the mail that is sent. Use {site_name} if needed.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_notification_availability_message',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Notification for availability (message)', // @translate
                'info' => 'Message of the mail when an item becomes available. Use {user_name}, {resource_title}, {resource_link}, {site_name} and {site_link}.', // @translate
            ],
            'attributes' => [
                'rows' => 5,
                'placeholder' => 'Message to notify availability of a reserved file…', // @translate'
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_item_set_top_pick',
            'type' => ItemSetSelect::class,
            'options' => [
                'label' => 'Top pick item set', // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
            ],
            'attributes' => [
                'id' => 'downloadmanager_item_set_top_pick',
                'class' => 'chosen-select',
                'multiple' => false,
                'required' => false,
                'data-placeholder' => 'Select the item set to use as top pick', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_item_set_trending',
            'type' => ItemSetSelect::class,
            'options' => [
                'label' => 'Trending item set', // @translate
                'empty_option' => '',
                'disable_group_by_owner' => true,
            ],
            'attributes' => [
                'id' => 'downloadmanager_item_set_trending',
                'class' => 'chosen-select',
                'multiple' => false,
                'required' => false,
                'data-placeholder' => 'Select the item set to use for trending', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_debug_disable_encryption_sites',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Disable file encryption for sites', // @translate
                'info' => 'For debug purpose only. Set the slugs of the sites.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'downloadmanager_debug_disable_encryption_groups',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Disable file encryption for groups', // @translate
                'info' => 'For debug purpose only.', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'downloadmanager_report_recipients',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'downloadmanager_item_set_top_pick',
            'required' => false,
        ]);
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
