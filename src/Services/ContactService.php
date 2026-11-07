<?php

namespace App\Services;

use Symfony\Component\Serializer\SerializerInterface;

class ContactService
{
    public function contactDisplay(mixed $contacts, SerializerInterface $serializer)
    {
        if (null === $contacts) {
            return null;
        }

        return $serializer->normalize($contacts, 'json', ['groups' => ['contacts', 'contact'],
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);
    }
}