<?php

namespace App\Controller\admin;

use App\Entity\Appointment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

class AppointmentAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/api/admin/appointment/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $limit = (int) $request->query->get('limit');
            $offset = (int) $request->query->get('offset');

            $appointments = $this->entityManager->getRepository(Appointment::class)->findAllAppointments($limit, $offset);
            $count = $this->entityManager->getRepository(Appointment::class)->findAllCount();

            $dataAppointments = $serializer->normalize($appointments, 'json', ['groups' => ['appointments'],
                'circular_reference_handlers' => function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json([
                'appointments' => $dataAppointments,
                'count' => (int) $count,
            ], Response::HTTP_OK);

        } catch(\Throwable $e) {
            $this->logger->error('Erreur liste des rendez-vous clients : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/api/admin/appointment/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $search = trim((string) $request->query->get('search'));
            $search = trim($search);

            if ($search === '') {
                return $this->json([], Response::HTTP_OK);
            }

            $appointments = $this->entityManager->getRepository(Appointment::class)->findAllAppointmentsSearch($search);

            $dataAppointments = $serializer->normalize($appointments, 'json', ['groups' => ['appointments']]);
            return $this->json($dataAppointments, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur liste des rendez-vous clients : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/api/admin/appointment/show/{id}', methods: ['GET'])]
    public function show(int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
            if (!$appointment) {
                return new JsonResponse(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
            }

            $appointment->setIsRead(true);

            $dataAppointment = $serializer->normalize($appointment, 'json', ['groups' =>
                ['appointment', 'service', 'staff'], 'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            $this->entityManager->flush();

            return new JsonResponse($dataAppointment);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur du details d\'un rendez-vous : ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/api/admin/appointment/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Admin non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
            if (!$appointment) {
                return $this->json(['error' => 'Rendez-vous introuvable'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($appointment);
            $this->entityManager->flush();

            return $this->json(['message' => 'Le témoignage a été supprimé'], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur du details d\'un rendez-vous : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted("ROLE_ADMIN")]
    #[Route('/api/appointment/delete/{id}/user', methods: ['DELETE'])]
    public function userBookingDelete(Appointment $appointment): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            if ($appointment->getUser() !== $user) {
                return $this->json(['error' => 'Ce rendez-vous ne vous appartient pas'], Response::HTTP_FORBIDDEN);
            }

            $this->entityManager->remove($appointment);
            $this->entityManager->flush();

            return $this->json(null, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur suppression rendez-vous', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}