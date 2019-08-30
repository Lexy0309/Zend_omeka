<?php
namespace GuestUser\Form;

use Omeka\Form\Element\ResourceSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\EventManager\Event;

class UserForm extends \Omeka\Form\UserForm
{
    /**
     * {@inheritDoc}
     *
     * Fixed copy of the parent form.
     * @todo To remove after merge of pull request https://github.com/omeka/omeka-s/pull/1138.
     *
     * @see \Omeka\Form\UserForm::init()
     */
    public function init()
    {
        $this->add([
            'name' => 'user-information',
            'type' => Fieldset::class,
        ]);
        $this->add([
            'name' => 'user-settings',
            'type' => Fieldset::class,
        ]);
        $this->add([
            'name' => 'change-password',
            'type' => Fieldset::class,
        ]);
        $this->add([
            'name' => 'edit-keys',
            'type' => Fieldset::class,
        ]);
        $this->get('user-information')->add([
            'name' => 'o:email',
            'type' => Element\Email::class,
            'options' => [
                'label' => 'Email', // @translate
            ],
            'attributes' => [
                'id' => 'email',
                'required' => true,
            ],
        ]);
        $this->get('user-information')->add([
            'name' => 'o:name',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Display name', // @translate
            ],
            'attributes' => [
                'id' => 'name',
                'required' => true,
            ],
        ]);

        if ($this->getOption('include_role')) {
            $excludeAdminRoles = !$this->getOption('include_admin_roles');
            $roles = $this->getAcl()->getRoleLabels($excludeAdminRoles);
            $this->get('user-information')->add([
                'name' => 'o:role',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Role', // @translate
                    'empty_option' => 'Select role…', // @translate
                    'value_options' => $roles,
                ],
                'attributes' => [
                    'id' => 'role',
                    'required' => true,
                ],
            ]);
        }

        if ($this->getOption('include_is_active')) {
            $this->get('user-information')->add([
                'name' => 'o:is_active',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Is active', // @translate
                ],
                'attributes' => [
                    'id' => 'is-active',
                ],
            ]);
        }

        $userId = $this->getOption('user_id');
        $locale = $userId ? $this->userSettings->get('locale', null, $userId) : null;
        if (null === $locale) {
            $locale = $this->settings->get('locale');
        }
        $this->get('user-settings')->add([
            'name' => 'locale',
            'type' => 'Omeka\Form\Element\LocaleSelect',
            'options' => [
                'label' => 'Locale', // @translate
                'info' => 'Global locale/language code for all interfaces.', // @translate
            ],
            'attributes' => [
                'value' => $locale,
                'class' => 'chosen-select',
            ],
        ]);
        $this->get('user-settings')->add([
            'name' => 'default_resource_template',
            'type' => ResourceSelect::class,
            'options' => [
                'label' => 'Default resource template', // @translate
                'empty_option' => '',
                'resource_value_options' => [
                    'resource' => 'resource_templates',
                    'query' => [],
                    'option_text_callback' => function ($resourceTemplate) {
                        return $resourceTemplate->label();
                    },
                ],
            ],
            'attributes' => [
                'value' => $userId ? $this->userSettings->get('default_resource_template', null, $userId) : '',
                'class' => 'chosen-select',
                'data-placeholder' => 'Select a template', // @translate
            ],
        ]);

        if ($this->getOption('include_password')) {
            if ($this->getOption('current_password')) {
                $this->get('change-password')->add([
                    'name' => 'current-password',
                    'type' => Element\Password::class,
                    'options' => [
                        'label' => 'Current password', // @translate
                    ],
                ]);
            }
            $this->get('change-password')->add([
                'name' => 'password',
                'type' => Element\Password::class,
                'options' => [
                    'label' => 'New password', // @translate
                ],
                'attributes' => [
                    'id' => 'password',
                ],
            ]);
            $this->get('change-password')->add([
                'name' => 'password-confirm',
                'type' => Element\Password::class,
                'options' => [
                    'label' => 'Confirm new password', // @translate
                ],
                'attributes' => [
                    'id' => 'password-confirm',
                ],
            ]);
        }

        if ($this->getOption('include_key')) {
            $this->get('edit-keys')->add([
                'name' => 'new-key-label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'New key label', // @translate
                ],
                'attributes' => [
                    'id' => 'new-key-label',
                ],
            ]);
        }

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        // separate input filter stuff so that the event work right
        $inputFilter = $this->getInputFilter();

        $inputFilter->get('user-settings')->add([
            'name' => 'locale',
            'allow_empty' => true,
        ]);
        $inputFilter->get('user-settings')->add([
            'name' => 'default_resource_template',
            'allow_empty' => true,
        ]);

        if ($this->getOption('include_password')) {
            $inputFilter->get('change-password')->add([
                'name' => 'password',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 6,
                        ],
                    ],
                ],
            ]);
            $inputFilter->get('change-password')->add([
                'name' => 'password',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'Identical',
                        'options' => [
                            'token' => 'password-confirm',
                            'messages' => [
                                'notSame' => 'Password confirmation must match new password', // @translate
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if ($this->getOption('include_key')) {
            $inputFilter->get('edit-keys')->add([
                'name' => 'new-key-label',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => 255,
                        ],
                    ],
                ],
            ]);
        }

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}
