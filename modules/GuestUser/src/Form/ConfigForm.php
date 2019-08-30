<?php
namespace GuestUser\Form;

use Omeka\Form\Element\CkeditorInline;
use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'guestuser_open',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Allow open registration', // @translate
                'info' => 'Allow guest user registration without administrator approval. The link to use is "/s/my-site/guest-user/register".', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-open',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_recaptcha',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Require ReCaptcha', // @translate
                'info' => 'Check this to require passing a ReCaptcha test when registering', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-recaptcha',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_login_text',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Login Text', // @translate
                'info' => 'The text to use for the "Login" link in the user bar', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-login-text',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_register_text',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Register Text', // @translate
                'info' => 'The text to use for the "Register" link in the user bar', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-register-text',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_dashboard_label',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Dashboard Label', // @translate
                'info' => 'The text to use for the label on the user’s dashboard', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-dashboard-label',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_capabilities',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Registration Features', // @translate
                'info' => 'Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest user, please give them guidance about what they can and cannot do.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-capabilities',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_short_capabilities',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Short Registration Features', // @translate
                'info' => 'Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-short-capabilities',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_message_confirm_email',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Email sent to confirm registration', // @translate
                'info' => 'The text of the email to confirm the registration and to send the token.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser_message_confirm_email',
                'placeholder' => 'Hi {user_name},
You have registered for an account on {main_title} / {site_title} ({site_url}).
Please confirm your registration by following this link: {token_url}.
If you did not request to join {main_title} please disregard this email.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_message_update_email',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Email sent to update email', // @translate
                'info' => 'The text of the email sent when the user wants to update it.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser_message_update_email',
                'placeholder' => 'Hi {user_name},
You have requested to update email on {main_title} / {site_title} ({site_url}).
Please confirm your email by following this link: {token_url}.
If you did not request to update your email on {main_title}, please disregard this email.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_text',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Text for terms and conditions', // @translate
                'info' => 'The text to display to accept condtions.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-terms-text',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_page',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Page slug of the terms and conditions', // @translate
                'info' => 'If the text is on a specific page, or for other usage.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-terms-page',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_redirect',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Redirect page after acceptance', // @translate
                'value_options' => [
                    'home' => 'Main home page', // @translate
                    'site' => 'Home site', // @translate
                    'me' => 'User account', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'guestuser-terms-redirect',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_request_regex',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Pages not to redirect', // @translate
                'info' => 'Allows to keep some pages available when terms are not yet agreed. Default pages are included (logout, terms page…). This is a regex, with "~" delimiter, checked against the end of the url.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-terms-request-regex',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_force_agree',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Force terms agreement', // @translate
                'info' => 'If unchecked, the user will be logged out if terms are not accepted.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-termes-force-agree',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_reset_agreement_terms',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Reset terms agreement for all guest users', // @translate
                'info' => 'When terms and conditions are updated, you may want guest users agree them one more time. Warning: to set false will impact all guest users. So warn them some time before.', // @translate
                'value_options' => [
                    'keep' => 'No change', // @translate
                    'unset' => 'Set false', // @translate
                    'set' => 'Set true', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'guestuser-reset-agreement-terms',
                'value' => 'keep',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'guestuser_check_requested_with',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Check webview', // @translate
                'info' => 'In complex authentication flows where the view may be used by an external application, the view should return a json after login. The value of the header "X-Requested-With" is used to identify such a flow.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-check-requested-with',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_phone_url',
            'type' => Element\Url::class,
            'options' => [
                'label' => 'Url for phone/sms over https', // @translate
                'info' => 'The url of  the phone/sms provider to send the sms over https. It may contains a subdomain. If empty, the default url of the provider will be used.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser_phone_url',
                'placeholder' => 'https://xxxxx.api.infobip.com/',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_phone_api_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Phone/sms api key', // @translate
                'info' => 'The api key of the phone/sms provider that is set in the http headers.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-phone-api-key',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_phone_token_bypass',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Phone bypass token', // @translate
                // TODO Use a time limit or a hop, and send email when used.
                'info' => 'Warning: to use such a code is a security hole. Keep it only for a short time and for testing purpose only.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser_phone_token_bypass',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'guestuser_phone_url',
            'required' => false,
        ]);
    }
}
