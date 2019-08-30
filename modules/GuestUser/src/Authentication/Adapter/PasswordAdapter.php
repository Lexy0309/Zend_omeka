<?php
namespace GuestUser\Authentication\Adapter;

use Omeka\Authentication\Adapter\PasswordAdapter as OmekaPasswordAdapter;
use Zend\Authentication\Result;

/**
 * Auth adapter for checking passwords through Doctrine.
 */
class PasswordAdapter extends OmekaPasswordAdapter
{
    protected $token_repository;

    public function authenticate()
    {
        $user = $this->repository->findOneBy(['email' => $this->identity]);

        if (!$user || !$user->isActive()) {
            return new Result(
                Result::FAILURE_IDENTITY_NOT_FOUND,
                null,
                ['User not found.']
            ); // @translate
        }

        if ($user->getRole() == 'guest') {
            $guest = $this->token_repository->findOneBy(['email' => $this->identity]);
            // There is no token if the guest is created directly (the role is
            // set to a user).
            if ($guest && !$guest->isConfirmed()) {
                return new Result(Result::FAILURE, null, ['Your account has not been confirmed: check your email.']); // @translate
            }
        }

        if (!$user->verifyPassword($this->credential)) {
            return new Result(
                Result::FAILURE_CREDENTIAL_INVALID,
                null,
                ['Invalid password.']
            );
        }

        return new Result(Result::SUCCESS, $user);
    }

    public function setTokenRepository($token_repository)
    {
        $this->token_repository = $token_repository;
    }
}
