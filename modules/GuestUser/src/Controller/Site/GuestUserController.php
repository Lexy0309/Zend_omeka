<?php
namespace GuestUser\Controller\Site;

use Doctrine\ORM\EntityManager;
use GuestUser\Entity\GuestUserToken;
use GuestUser\Form\AcceptTermsForm;
use GuestUser\Form\EmailForm;
use GuestUser\Form\PhoneForm;
use GuestUser\Stdlib\PsrMessage;
use Omeka\Entity\User;
use Omeka\Form\ForgotPasswordForm;
use Omeka\Form\LoginForm;
use Omeka\Form\UserForm;
use Omeka\Stdlib\Message;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Mvc\MvcEvent;
use Zend\Session\Container;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * Manage guest users pages.
 */
class GuestUserController extends AbstractActionController
{
    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var array
     */
    protected $config;

    protected $defaultRoles = [
        \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        \Omeka\Permissions\Acl::ROLE_AUTHOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
    ];

    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManager $entityManager
     * @param array $config
     */
    public function __construct(
        AuthenticationService $authenticationService,
        EntityManager $entityManager,
        array $config
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    public function loginAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $auth = $this->getAuthenticationService();

        $isExternalApp = $this->isExternalApp();
        $requestApiVersion = $this->requestVatiruApiVersion();

        // Check if there is a fail from a third party authenticator.
        $externalAuth = $this->params()->fromQuery('auth');
        if ($externalAuth === 'error') {
            $siteSlug = $this->params()->fromRoute('site-slug');
            $params = $this->params()->fromPost();
            $params += $this->params()->fromQuery();
            if (array_key_exists('password', $params)) {
                $params['password'] = '***';
            }
            $this->logger()->err(sprintf('A user failed to log on the site "%s"; params: %s.', // @translate
                $siteSlug, json_encode($params, 320)));
            $response = $this->getResponse();
            $response->setStatusCode(400);
            if ($isExternalApp && version_compare($requestApiVersion, '3.2.1', '>=')) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => 'Unable to authenticate. Contact the administrator.', // @translate
                ]);
            }
            return $this->redirect()->toRoute('site/guest-user', ['action' => 'auth-error', 'site-slug' => $siteSlug]);
        }

        $view = new ViewModel;

        /** @var LoginForm $form */
        $form = $this->getForm(LoginForm::class);
        $view->setVariable('form', $form);

        if ($externalAuth === 'local') {
            return $view;
        }

        $view->setVariable('form', $form);

        if (!$this->checkPostAndValidForm($form)) {
            $email = $this->params()->fromPost('email') ?: $this->params()->fromQuery('email');
            if ($email) {
                $form->get('email')->setValue($email);
            }
            return $view;
        }

        $validatedData = $form->getData();
        $sessionManager = Container::getDefaultManager();
        $sessionManager->regenerateId();

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($validatedData['email']);
        $adapter->setCredential($validatedData['password']);
        $result = $auth->authenticate();
        if (!$result->isValid()) {
            $this->messenger()->addError(implode(';', $result->getMessages()));
            return $view;
        }

        $user = $auth->getIdentity();

        if ($isExternalApp && version_compare($requestApiVersion, '3.2.1', '>=')) {
            $userSettings = $this->userSettings();
            $userSettings->setTargetId($user->getId());
            $result = [];
            $result['user_id'] = $user->getId();
            $result['agreed'] = $userSettings->get('guestuser_agreed_terms');
            return new JsonModel($result);
        }

        $this->messenger()->addSuccess('Successfully logged in'); // @translate
        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.login', $auth->getIdentity());

        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }
        return $this->redirect()->toUrl($this->currentSite()->url());
    }

    public function authErrorAction()
    {
        return new ViewModel;
    }

    public function logoutAction()
    {
        $auth = $this->getAuthenticationService();
        $auth->clearIdentity();

        $sessionManager = Container::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $this->messenger()->addSuccess('Successfully logged out'); // @translate
        $redirectUrl = $this->params()->fromQuery('redirect');

        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toUrl($this->currentSite()->url());
    }

    public function forgotPasswordAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $form = $this->getForm(ForgotPasswordForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->getRequest()->getPost();
        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addError('Activation unsuccessful'); // @translate
            return $view;
        }

        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $data['email'],
                'isActive' => true,
            ]);
        if ($user) {
            $entityManager->persist($user);
            $passwordCreation = $entityManager
                ->getRepository('Omeka\Entity\PasswordCreation')
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $entityManager->remove($passwordCreation);
                $entityManager->flush();
            }
            $this->mailer()->sendResetPassword($user);
        }

        $this->messenger()->addSuccess('Check your email for instructions on how to reset your password'); // @translate

        return $view;
    }

    public function registerAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $user = new User();
        $user->setRole(\GuestUser\Permissions\Acl::ROLE_GUEST);

        $form = $this->_getForm($user);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $registerLabel = $this->getOption('guestuser_capabilities')
            ? $this->getOption('guestuser_capabilities')
            : $this->translate('Register'); // @translate

        $view->setVariable('registerLabel', $registerLabel);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        // TODO Add password required only for login.
        $values = $form->getData();
        if (empty($values['change-password']['password'])) {
            $this->messenger()->addError('A password must be set.'); // @translate
            return $view;
        }

        $userInfo = $values['user-information'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = \GuestUser\Permissions\Acl::ROLE_GUEST;
        $userInfo['o:is_active'] = false;
        $response = $this->api()->create('users', $userInfo);
        if (!$response) {
            $entityManager = $this->getEntityManager();
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if ($user) {
                $guestUserToken = $entityManager->getRepository(GuestUserToken::class)
                    ->findOneBy(['email' => $userInfo['o:email']], ['id' => 'DESC']);
                if (empty($guestUserToken) || $guestUserToken->isConfirmed()) {
                    $this->messenger()->addError('Already registered.'); // @translate
                } else {
                    $this->messenger()->addError('Check your email to confirm your registration.'); // @translate
                }
                return $this->redirect()->toRoute('site/guest-user', ['action' => 'login'], true);
            }

            $this->messenger()->addError('Unknown error.'); // @translate
            return $view;
        }

        $user = $response->getContent()->getEntity();
        $user->setPassword($values['change-password']['password']);
        $user->setRole(\GuestUser\Permissions\Acl::ROLE_GUEST);
        // The account is active, but not confirmed, so login is not possible.
        // Guest user has no right to set active his account.
        $user->setIsActive(true);

        $id = $user->getId();
        if (!empty($values['user-settings'])) {
            $userSettings = $this->userSettings();
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        $guestUserToken = $this->createGuestUserToken($user);
        $message = $this->prepareMessage('confirm-email', [
            'user_name' => $user->getName(),
            'token' => $guestUserToken,
        ]);
        $result = $this->sendEmail($user->getEmail(), $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $message = new Message($this->translate('An error occurred when the email was sent.')); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[GuestUser] ' . $message);
            return $view;
        }

        $message = $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'login'], [], true);
    }

    public function updateAccountAction()
    {
        if (!$this->isUserLogged()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();
        $id = $user->getId();

        $label = $this->getOption('guestuser_dashboard_label')
            ? $this->getOption('guestuser_dashboard_label')
            : $this->translate('My account'); // @translate

        $userRepr = $this->api()->read('users', $id)->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->_getForm($user);
        $form->get('user-information')->populateValues($data);
        $form->get('change-password')->populateValues($data);

        // The email is updated separately for security.
        $emailField = $form->get('user-information')->get('o:email');
        $emailField->setAttribute('disabled', true);
        $emailField->setAttribute('required', false);

        // The phone is updated separately for security.
        if ($form->get('user-settings')->has('o:phone')) {
            $field = $form->get('user-settings')->get('o:phone');
            $field->setAttribute('disabled', true);
            $field->setAttribute('required', false);
        }

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);
        $view->setVariable('label', $label);
        $view->setVariable('phone', '');

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        // A security.
        unset($postData['user-information']['o:id']);
        unset($postData['user-information']['o:email']);
        unset($postData['user-information']['o:role']);
        unset($postData['user-information']['o:is_active']);
        unset($postData['edit-keys']);
        $postData['user-information'] = array_replace(
            $data,
            array_intersect_key($postData['user-information'], $data)
        );
        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Password invalid'); // @translate
            return $view;
        }
        $values = $form->getData();
        $response = $this->api($form)->update('users', $user->getId(), $values['user-information']);

        // Stop early if the API update fails.
        if (!$response) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        $successMessages = [];
        $successMessages[] = 'Your modifications have been saved.'; // @translate

        // The values were filtered: no hack is possible with added values.
        if (!empty($values['user-settings'])) {
            $userSettings = $this->userSettings();
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        $passwordValues = $values['change-password'];
        if (!empty($passwordValues['password'])) {
            // TODO Add a current password check when update account.
            // if (!$user->verifyPassword($passwordValues['current-password'])) {
            //     $this->messenger()->addError('The current password entered was invalid'); // @translate
            //     return $view;
            // }
            $user->setPassword($passwordValues['password']);
            $successMessages[] = 'Password successfully changed'; // @translate
        }

        $this->entityManager->flush();

        foreach ($successMessages as $message) {
            $this->messenger()->addSuccess($message);
        }
        return $view;
    }

    public function updateEmailAction()
    {
        if (!$this->isUserLogged()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();

        $isExternalApp = $this->isExternalApp();

        $form = $this->getForm(EmailForm::class, []);
        $form->populateValues(['o:email' => $user->getEmail()]);

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            if ($isExternalApp) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => $this->translate('The request should be a POST.'), // @translate
                ]);
            }
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        // TODO Check if the csrf is managed to check validity of the form for external app.
        if ($isExternalApp) {
            $values = $postData;
            $email = $values['o:email'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new Message($this->translate('"%1$s" is not an email.'), $email), // @translate
                ]);
            }

            $guestUserToken = $this->createGuestUserToken($user);
            $message = $this->prepareMessage('update-email', [
                'user_name' => $user->getName(),
                'token' => $guestUserToken,
            ]);
            $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
            if (!$result) {
                $message = new Message($this->translate('An error occurred when the email was sent.')); // @translate
                $this->logger()->err('[GuestUser] ' . $message);
                return new JsonModel([
                    'result' => 'error',
                    'message' => $message,
                ]);
            }

            $message = new Message($this->translate('Check your email "%s" to confirm the change.'), $email); // @translate
            return new JsonModel([
                'result' => 'success',
                'message' => $message,
            ]);
        }

        if (!$form->isValid()) {
            $this->messenger()->addError('Email invalid'); // @translate
            return $view;
        }

        $values = $form->getData();
        $email = $values['o:email'];

        $guestUserToken = $this->createGuestUserToken($user);
        $message = $this->prepareMessage('update-email', [
            'user_name' => $user->getName(),
            'token' => $guestUserToken,
        ]);
        $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $message = new Message($this->translate('An error occurred when the email was sent.')); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[GuestUser] ' . $message);
            return $view;
        }

        $message = new Message($this->translate('Check your email "%s" to confirm the change.'), $email); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
    }

    /**
     * The phone update is generally from an external app.
     *
     * @todo Change process: send the number and fill a short token in a form.
     */
    public function updatePhoneAction()
    {
        if (!$this->isUserLogged()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();
        $id = $user->getId();

        $isExternalApp = $this->isExternalApp();

        $userRepr = $this->api()->read('users', $id)->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->getForm(PhoneForm::class, []);
        $form->populateValues($data);

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            if ($isExternalApp) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => $this->translate('The request should be a POST.'), // @translate
                ]);
            }
            return $view;
        }

        $postData = $this->params()->fromPost();

        // TODO Check if the csrf is managed to check validity of the form for external app.
        if ($isExternalApp) {
            // TODO Add a check of the "full_phone".
            $data = $postData;
            // Warning: there may begin with a "+". It may be formatted via js.
            // Manage the js "intlTelInput" with full phone too.
            $phone = empty($data['full_phone'])
                ? str_replace(['.', '-', ' '], '', $data['o:phone'])
                : $data['full_phone'];
            $phoneCheck = strpos($phone, '+') === 0 ? substr($phone, 1) : $phone;
            if (!ctype_digit($phoneCheck)) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new Message($this->translate('"%1$s" is not a phone number: it must contain only numbers.'), $phone), // @translate
                ]);
            }

            $guestUserToken = $this->createGuestUserToken($user, $phone, true);
            $settings = $this->settings();
            $context = [
                'main_title' => $settings->get('installation_title', 'Omeka S'),
                'token' => $guestUserToken->getToken(),
            ];
            $message = new PsrMessage('Return this sms to confirm your phone for {main_title} [{token}]', $context); // @translate
            $result = $this->sendSms($phone, $message);
            // A string means a message of error.
            if (is_string($result)) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('An error occurred when the message was sent: {message}.', ['message' => $result]), // @translate
                ]);
            }
            // "1" is pending, "3" is delivered.
            if ($result['status']['groupId'] == 1) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('The sms is pending.'), // @translate
                ]);
            }

            $message = new PsrMessage('Check your sms on {phone} to confirm the change.', ['phone' => $phone]); // @translate
            return new JsonModel([
                'result' => 'success',
                'message' => $message,
            ]);
        }

        // Manage the js "intlTelInput" with full phone too.
        // TODO Add a check of the "full_phone".
        if (!empty($postData['full_phone'])) {
            $postData['o:phone'] = $postData['full_phone'];
        }

        $form->setData($postData);
        if (!$form->isValid()) {
            $this->messenger()->addError('Phone invalid'); // @translate
            return $view;
        }

        $data = $form->getData();
        // Manage the js "intlTelInput" with full phone too.
        if (!empty($postData['full_phone'])) {
            $data['full_phone'] = $postData['full_phone'];
        }

        // TODO Move the test and format of the phone inside the form.
        // Warning: there may begin with a "+". It may be formatted via js.
        // Manage the js "intlTelInput" with full phone too.
        $phone = empty($data['full_phone'])
            ? str_replace(['.', '-', ' '], '', $data['o:phone'])
            : $data['full_phone'];
        $phoneCheck = strpos($phone, '+') === 0 ? substr($phone, 1) : $phone;
        if (!ctype_digit($phoneCheck)) {
            $message = (string) new PsrMessage('"{phone}" is not a phone number: it must contain only numbers.', ['phone' => $phone]); // @translate
            $this->messenger()->addError($message);
            return $view;
        }

        $guestUserToken = $this->createGuestUserToken($user, $phone, true);
        $settings = $this->settings();
        $context = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'token' => $guestUserToken->getToken(),
        ];
        $message = new PsrMessage('Return this sms to confirm your phone for {main_title} [{token}]', $context); // @translate
        $result = $this->sendSms($phone, $message);
        // A string means a message of error.
        if (is_string($result)) {
            $message = (string) new PsrMessage('An error occurred when the message was sent: {message}.', ['message' => $result]); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[GuestUser] ' . $message);
            return $view;
        }
        // "1" means pending.
        if ($result['status']['groupId'] != 1) {
            $message = (string) new PsrMessage('The sms is pending.'); // @translate
            $this->messenger()->addError($message);
            $this->logger()->warn('[GuestUser] ' . $message);
            return $view;
        }

        $message = (string) new PsrMessage('Check your sms on {phone} to confirm the change.', ['phone' => $phone]); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
    }

    public function meAction()
    {
        if (!$this->isUserLogged()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $eventManager = $this->getEventManager();
        $partial = $this->viewHelpers()->get('partial');

        $widget = [];
        $widget['label'] = $this->translate('My Account'); // @translate
        $widget['content'] = $partial('common/guest-user-account');

        $args = $eventManager->prepareArgs(['widgets' => []]);
        $args['widgets']['account'] = $widget;

        $event = new MvcEvent('guestuser.widgets', $this, $args);
        $eventManager->triggerEvent($event);

        $view = new ViewModel;
        $view->setVariable('widgets', $args['widgets']);
        return $view;
    }

    public function acceptTermsAction()
    {
        if (!$this->isUserLogged()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $userSettings = $this->userSettings();
        $agreed = $userSettings->get('guestuser_agreed_terms');
        if ($agreed) {
            $message = new Message($this->translate('You already agreed the terms and conditions.')); // @translate
            $this->messenger()->addSuccess($message);
            return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
        }

        $forced = $this->settings()->get('guestuser_terms_force_agree');

        /** @var \GuestUser\Form\AcceptTermsForm $form */
        // $form = $this->getForm(AcceptTermsForm::class, null, ['forced' => $forced]);
        $form = new AcceptTermsForm();
        $form->setOption('forced', $forced);
        $form->init();

        $text = $this->settings()->get('guestuser_terms_text');
        $page = $this->settings()->get('guestuser_terms_page');

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('text', $text);
        $view->setVariable('page', $page);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Form invalid'); // @translate
            return $view;
        }

        $data = $form->getData();
        $accept = (bool) $data['guestuser_agreed_terms'];
        $userSettings->set('guestuser_agreed_terms', $accept);

        if (!$accept) {
            if ($forced) {
                $message = new Message($this->translate('The access to this website requires you accept the current terms and conditions.')); // @translate
                $this->messenger()->addError($message);
                return $view;
            }
            return $this->redirect()->toRoute('site/guest-user', ['action' => 'logout'], [], true);
        }

        $message = new Message($this->translate('Thanks for accepting the terms and condtions.')); // @translate
        $this->messenger()->addSuccess($message);
        switch ($this->settings()->get('guestuser_terms_redirect')) {
            case 'home':
                return $this->redirect()->toRoute('top');
            case 'site':
                return $this->redirect()->toRoute('site', [], [], true);
            case 'me':
            default:
                return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
        }
    }

    public function staleTokenAction()
    {
        $auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
        $auth->clearIdentity();
    }

    public function confirmAction()
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();
        $guestUserToken = $entityManager->getRepository(GuestUserToken::class)->findOneBy(['token' => $token]);
        if (empty($guestUserToken)) {
            $this->messenger()->addError($this->translate('Invalid token stop')); // @translate
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $guestUserToken->setConfirmed(true);
        $entityManager->persist($guestUserToken);
        $user = $entityManager->find(User::class, $guestUserToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setIsActive(true);
        $entityManager->persist($user);
        $entityManager->flush();

        $siteTitle = $this->currentSite()->title();
        $body = new Message('Thanks for joining %s! You can now log in using the password you chose.', // @translate
            $siteTitle);

        $this->messenger()->addSuccess($body);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'login',
        ]);
        return $this->redirect()->toUrl($redirectUrl);
    }

    public function confirmEmailAction()
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();

        $isExternalApp = $this->isExternalApp();
        $siteTitle = $this->currentSite()->title();

        $guestUserToken = $entityManager->getRepository(GuestUserToken::class)->findOneBy(['token' => $token]);
        if (empty($guestUserToken)) {
            $message = new Message($this->translate('Invalid token: your email was not confirmed for %s.'), // @translate
                $siteTitle);
            if ($isExternalApp) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new Message($message), // @translate
                ]);
            }

            $this->messenger()->addError($message); // @translate
            $redirectUrl = $this->url()->fromRoute('site/guest-user', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'update-email',
            ]);
            return $this->redirect()->toUrl($redirectUrl);
        }

        $guestUserToken->setConfirmed(true);
        $entityManager->persist($guestUserToken);
        $email = $guestUserToken->getEmail();
        $user = $entityManager->find(User::class, $guestUserToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setEmail($email);
        $entityManager->persist($user);
        $entityManager->flush();

        $this->userSettings()->set('guestuserauthentication_verified_email', $email, $user->getId());
        $this->userSettings()->set('guestuserauthentication_confirmed_email', true, $user->getId());

        $message = new Message('Your new email "%s" is confirmed for %s.', // @translate
            $email, $siteTitle);
        if ($isExternalApp) {
            return new JsonModel([
                'result' => 'success',
                'message' => $message,
            ]);
        }

        $this->messenger()->addSuccess($message);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'me',
        ]);
        return $this->redirect()->toUrl($redirectUrl);
    }

    /**
     * @todo Manage confirmation of phone via a trigger, or move all specific code outside.
     * The phone confirmation is always from an external app.
     */
    public function confirmPhoneAction()
    {
        // TODO This params are set by the provider.

        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();

        $isExternalApp = $this->isExternalApp();
        $siteTitle = $this->currentSite()->title();

        $guestUserToken = $entityManager->getRepository(GuestUserToken::class)->findOneBy(['token' => $token]);
        if (empty($guestUserToken)) {
            $message = new Message($this->translate('Invalid token: your phone was not confirmed for %s.'), // @translate
                $siteTitle);
            // TODO Where is the phone when the token is undetermined?
            // No check of the result: it's already a message of error.
            // $this->sendSms($phone, $message);
            if ($isExternalApp) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new Message($message), // @translate
                ]);
            }

            $this->messenger()->addError($message); // @translate
            $redirectUrl = $this->url()->fromRoute('site/guest-user', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'update-phone',
            ]);
            return $this->redirect()->toUrl($redirectUrl);
        }

        $guestUserToken->setConfirmed(true);
        $entityManager->persist($guestUserToken);
        $entityManager->flush();
        $phone = $guestUserToken->getEmail();
        $user = $entityManager->find(User::class, $guestUserToken->getUser()->getId());

        // Currently, the phone is saved in the settings of the user.

        $this->userSettings()->set('guestuserauthentication_verified_phone', $phone, $user->getId());
        $this->userSettings()->set('guestuserauthentication_confirmed_phone', true, $user->getId());

        $message = new Message($this->translate('Your phone number is confirmed for %s.'), // @translate
            $siteTitle);

        // No check of the result: the phone is already confirmed.
        $this->sendSms($phone, $message);
        if ($isExternalApp) {
            return new JsonModel([
                'result' => 'success',
                'message' => $message,
            ]);
        }

        $this->messenger()->addSuccess($message);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'me',
        ]);
        return $this->redirect()->toUrl($redirectUrl);
    }

    /**
     * Check if a user is logged.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return bool
     */
    protected function isUserLogged()
    {
        return $this->getAuthenticationService()->hasIdentity();
    }

    /**
     * Redirect to admin or site according to the role of the user.
     *
     * @return \Zend\Http\Response
     */
    protected function redirectToAdminOrSite()
    {
        $user = $this->getAuthenticationService()->getIdentity();
        return $user && in_array($user->getRole(), $this->defaultRoles)
            ? $this->redirect()->toRoute('admin', [], true)
            : $this->redirect()->toRoute('site', [], true);
    }

    protected function checkPostAndValidForm(\Zend\Form\Form $form)
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if (!$form->isValid()) {
            $this->messenger()->addError('Email or password invalid'); // @translate
            return false;
        }
        return true;
    }

    protected function getOption($key)
    {
        return $this->settings()->get($key);
    }

    /**
     * Prepare the user form for public view.
     *
     * @param User $user
     * @param array $options
     * @return UserForm
     */
    protected function _getForm(User $user = null, array $options = [])
    {
        $defaultOptions = [
            'is_public' => true,
            'user_id' => $user ? $user->getId() : 0,
            'include_password' => true,
            'include_role' => false,
            'include_key' => false,
        ];
        $options += $defaultOptions;

        $form = $this->getForm(UserForm::class, $options);

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

    /**
     * Prepare the template.
     *
     * @param string $template In case of a token message, this is the action.
     * @param array $data
     * @return array Filled subject and body as PsrMessage, from templates
     * formatted with moustache style.
     */
    protected function prepareMessage($template, array $data)
    {
        $settings = $this->settings();
        $currentSite = $this->currentSite();
        $default = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'site_title' => $currentSite->title(),
            'site_url' => $currentSite->siteUrl(null, true),
            'user_name' => '',
            'token' => null,
        ];

        $data += $default;

        if ($data['token']) {
            $data['token'] = $data['token']->getToken();
            $urlOptions = ['force_canonical' => true];
            $urlOptions['query']['token'] = $data['token'];
            $data['token_url'] = $this->url()->fromRoute(
                'site/guest-user',
                ['site-slug' => $currentSite->slug(), 'action' => $template],
                $urlOptions
            );
        }

        switch ($template) {
            case 'confirm-email':
                $subject = 'Your request to join {main_title} / {site_title}'; // @translate
                $body = $settings->get('guestuser_message_confirm_email',
                    $this->getConfig()['guestuser']['config']['guestuser_message_confirm_email']);
                break;

            case 'update-email':
                $subject = 'Update email on {main_title} / {site_title}'; // @translate
                $body = $settings->get('guestuser_message_update_email',
                    $this->getConfig()['guestuser']['config']['guestuser_message_confirm_email']);
                break;

                // Allows to manage derivative modules.
            default:
                $subject = !empty($data['subject']) ? $data['subject'] : '[No subject]'; // @translate
                $body = !empty($data['body']) ? $data['body'] : '[No message]'; // @translate
                break;
        }

        unset($data['subject']);
        unset($data['body']);
        $subject = new PsrMessage($subject, $data);
        $body = new PsrMessage($body, $data);

        return [
            'subject' => $subject,
            'body'=> $body,
        ];
    }

    /**
     * Check if a request is done via an external application, specified in the
     * config.
     *
     * @return bool
     */
    protected function isExternalApp()
    {
        $requestedWith = $this->params()->fromHeader('X-Requested-With');
        if (empty($requestedWith)) {
            return false;
        }

        $checkRequestedWith = $this->settings()->get('guestuser_check_requested_with');
        if (empty($checkRequestedWith)) {
            return false;
        }

        $requestedWith = $requestedWith->getFieldValue();
        return strpos($requestedWith, $checkRequestedWith) === 0;
    }

    /**
     * @return \Zend\Authentication\AuthenticationService
     */
    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return array
     */
    protected function getConfig()
    {
        return $this->config;
    }
}
