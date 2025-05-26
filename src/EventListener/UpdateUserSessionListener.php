<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class UpdateUserSessionListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        $request = $this->requestStack->getCurrentRequest();

        if ($token && $token->getUser() instanceof UserInterface && $request && $request->hasSession()) {
            $session = $request->getSession();
            $user = $token->getUser();

            // Пересоздаём токен с новым User и ролями
            $newToken = new UsernamePasswordToken(
                $user,
                'main', // имя firewall
                $user->getRoles()
            );
            $this->tokenStorage->setToken($newToken);
            $session->set('_security_main', serialize($newToken));
            $this->logger->info('User session updated with new token and user data');
        }
    }
}
