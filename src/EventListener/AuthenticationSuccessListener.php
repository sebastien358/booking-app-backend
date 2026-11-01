<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $response = $event->getResponse();
        $token = $event->getData()['token'];

        $response->headers->setCookie(
            Cookie::create('token')
                ->withValue($token)
                ->withHttpOnly(true)
                ->withSecure(false)
                ->withSameSite('strict')
                ->withPath('/')
        );
    }
}