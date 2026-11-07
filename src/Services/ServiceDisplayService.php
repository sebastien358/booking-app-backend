<?php

namespace App\Services;

use Symfony\Component\Serializer\SerializerInterface;

class ServiceDisplayService
{
    public function serviceDisplay(mixed $services, SerializerInterface $serializer)
    {
        if (null === $services) {
            return null;
        }

        return $serializer->normalize($services, 'json', ['groups' => ['services', 'categories'],
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);
    }
}