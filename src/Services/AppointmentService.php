<?php

namespace App\Services;

use Symfony\Component\Serializer\SerializerInterface;

class AppointmentService
{
    public function appointmentDisplay(mixed $appointments, SerializerInterface $serializer)
    {
        return $serializer->normalize($appointments, 'json', ['groups' =>
            ['appointments', 'appointment', 'service', 'staff'], 'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);
    }
}