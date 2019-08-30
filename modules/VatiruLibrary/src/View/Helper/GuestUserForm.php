<?php
namespace VatiruLibrary\View\Helper;

use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\GetForm;
use Omeka\Form\UserForm;
use Zend\View\Helper\AbstractHelper;

class GuestUserForm extends AbstractHelper
{
    /**
     * @var GetForm
     */
    protected $getForm;

    public function __construct(GetForm $getForm)
    {
        $this->getForm = $getForm;
    }

    /**
     * Prepare the main user form for public view.
     *
     * @param User $user
     * @param array $options
     * @return UserForm
     */
    public function __invoke(User $user, array $options = [])
    {
        $view = $this->getView();
        $form = $this->_getForm($user, $options);

        // See GuestUser\Controller\Site::updateAccountAction()
        $userRepr = $view->api()->read('users', $user->getId())->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->_getForm($user);
        $form->get('user-information')->populateValues($data);
        $form->get('change-password')->populateValues($data);

        // The email is updated separately for security.
        $emailField = $form->get('user-information')->get('o:email');
        $emailField->setAttribute('disabled', true);
        $emailField->setAttribute('required', false);

        return $form;
    }

    /**
     * Prepare the user form for public view.
     *
     * @see \GuestUser\Controller\Site\GuestUserController::_getForm()
     *
     * @param User $user
     * @param array $options
     * @return UserForm
     */
    protected function _getForm(User $user = null, array $options = [])
    {
        $options = array_merge(
            [
                'is_public' => true,
                'user_id' => $user ? $user->getId() : 0,
                'include_password' => true,
                'include_role' => false,
                'include_key' => false,
            ],
            $options
        );

        $getForm = $this->getForm;
        $form = $getForm(UserForm::class, $options);

        // Remove elements from the admin user form, that shouldn’t be available
        // in public guest form.
        $elements = [
            'default_resource_template' => 'user-settings',
        ];
        foreach ($elements as $element => $fieldset) {
            if ($fieldset) {
                $fieldset = $form->get($fieldset);
                $fieldset ? $fieldset->remove($element) : null;
            } else {
                $form->remove($element);
            }
        }
        return $form;
    }
}
