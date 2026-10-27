<?php

namespace App\Controller\User;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user')]
#[IsGranted("ROLE_USER")]
class AppointmentUserController extends AbstractController
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        LoggerInterface $logger, EntityManagerInterface $entityManager
    ) {
      $this->logger = $logger;
      $this->entityManager = $entityManager;
    }

    #[Route('/bookings/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $offset = (int) $request->query->get('offset');
            $limit = (int) $request->query->get('limit');

            $appointments = $this->entityManager->getRepository(Appointment::class)->findAllAppointmentsUser($user, $offset, $limit);
            $countAppointments = $this->entityManager->getRepository(Appointment::class)->findAllCountUser($user);

            $dataAppointments = $serializer->normalize($appointments, 'json', ['groups' => ['appointments'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json([
                'appointments' => $dataAppointments,
                'count' => (int) $countAppointments,
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            $this->logger->error('Error de la récupération des rendez-vous : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}