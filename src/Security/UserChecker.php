<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Enum\StatutCompteEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        if ($user->getStatutCompte() === StatutCompteEnum::INACTIF->value) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas encore activé. Veuillez vérifier vos emails.'
            );
        }

        if ($user->getStatutCompte() === StatutCompteEnum::SUSPENDU->value) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Veuillez contacter le support.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void {}
}
