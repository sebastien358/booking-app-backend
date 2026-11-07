<?php

namespace App\Services;

use Symfony\Component\Serializer\SerializerInterface;

class UserService
{
    public function UserDisplay(mixed $users, SerializerInterface $serializer)
    {
        if (null === $users) {
            return null;
        }

        return $serializer->normalize($users, 'json', ['groups' => ['user'],
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);
    }
}