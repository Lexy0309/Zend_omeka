<?php
namespace VatiruLibrary\Stdlib;

use Omeka\Entity\User;

class Mailer extends \Omeka\Stdlib\Mailer
{
    public function sendUserActivation(User $user)
    {
        $message = $this->defaultOptions['message']['user_activation'];
        if (empty($message)) {
            return parent::sendUserActivation($user);
        }

        $translate = $this->viewHelpers->get('translate');
        $installationTitle = $this->getInstallationTitle();
        $template = $translate($message);

        $passwordCreation = $this->getPasswordCreation($user, true);
        $body = sprintf(
            $template,
            $this->getSiteUrl(),
            $user->getEmail(),
            $this->getCreatePasswordUrl($passwordCreation),
            $this->getExpiration($passwordCreation),
            $installationTitle
        );

        $message = $this->createMessage();
        $message->addTo($user->getEmail(), $user->getName())
            ->setSubject(sprintf(
                $translate('User activation for %s'),
                $installationTitle
            ))
            ->setBody($body);
        $this->send($message);
    }
}
