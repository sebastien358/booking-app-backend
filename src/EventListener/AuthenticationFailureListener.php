<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthenticationFailureListener
{
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $msg = $event->getException()->getMessageKey();

        if (str_contains(strtolower($msg), 'suspendu')) {
            $event->setResponse(new JsonResponse([
                'type' => 'ACCOUNT_SUSPENDED',
                'message' => 'Votre compte a été suspendu'
            ], 401));
        }
    }
}