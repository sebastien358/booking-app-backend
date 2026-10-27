<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\SlotHold;
use App\Entity\Staff;
use App\Repository\AppointmentRepository;
use App\Services\MailerProvider;
use DateTimeZone;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use http\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Error\RuntimeError;

#[Route('/api/appointment')]
class AppointmentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AppointmentRepository $appointmentRepository;
    private MailerProvider $mailerProvider;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, AppointmentRepository $appointmentRepository,
        MailerProvider $mailerProvider, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->appointmentRepository = $appointmentRepository;
        $this->mailerProvider = $mailerProvider;
        $this->logger = $logger;
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/user/list')]
    public function accountUser(SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['message' => 'User not found'], Response::HTTP_FORBIDDEN);
            }

            $appointments = $this->appointmentRepository->findAllAppointmentUser($user);
            $dataAppointments = $serializer->normalize($appointments, 'json', ['groups' => ['appointments', 'services', 'staffs'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json($dataAppointments, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur récupération rdv client', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/hold/slots', methods: ['POST'])]
    public function holdSlot(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (
                empty($data['startAt']) ||
                empty($data['serviceId']) ||
                empty($data['staffId'])
            ) {
                return $this->json(['error' => 'No data'], Response::HTTP_BAD_REQUEST);
            }

            $staff = $this->entityManager->getRepository(Staff::class)->find($data['staffId']);
            if (!$staff) {
                return $this->json(['error' => 'Staff not found'], Response::HTTP_NOT_FOUND);
            }

            $service = $this->entityManager->getRepository(Service::class)->find($data['serviceId']);
            if (!$service) {
                return $this->json(['error' => 'Service not found'], Response::HTTP_NOT_FOUND);
            }

            $tzParis = new DateTimeZone('Europe/Paris');
            $tzUtc = new DateTimeZone('UTC');

            $startAtParis = new DateTimeImmutable($data['startAt'], $tzParis);
            $startAtUtc = $startAtParis->setTimezone($tzUtc);
            $endUtc = $startAtUtc->modify("+{$service->getDuration()} minutes");

            $this->entityManager->wrapInTransaction(function () use ($staff, $startAtUtc, $endUtc) {

                $existing = $this->entityManager->getRepository(SlotHold::class)->findActiveHold(
                    $staff,
                    $startAtUtc,
                    $endUtc
                );

                if ($existing) {
                    throw new RuntimeException('ALREADY_HELD');
                }

                $expiresAt = new DateTimeImmutable("+10 minutes", new DateTimeZone("UTC"));

                $slotHold = new SlotHold();

                $slotHold->setStaff($staff);
                $slotHold->setStartsAt($startAtUtc);
                $slotHold->setEndAt($endUtc);
                $slotHold->setExpiresAt($expiresAt);

                $this->entityManager->persist($slotHold);
                $this->entityManager->flush();
            });

            return $this->json([
                'success' => true
            ], Response::HTTP_CREATED);

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'ALREADY_HELD') {

                return $this->json([
                    'type' => 'ALREADY_HELD',
                    'message' => 'Créneau déjà réservé'
                ], Response::HTTP_CONFLICT);
            }

            throw $e;
        }
    }

    #[Route('/slots', methods: ['GET'])]
    public function slots(Request $request): JsonResponse
    {
        $this->entityManager->getRepository(SlotHold::class)->deleteExpired();

        // ✅ Récupération des données depuis le frontend

        $date = (string) $request->query->get('date');
        $categoryId = (int) $request->query->get('categoryId');
        $serviceId = (int) $request->query->get('serviceId');
        $staffId = (int) $request->query->get('staffId');

        if (!$date || !$categoryId || !$serviceId || !$staffId) {
            return $this->json([]);
        }

        // ✅ Supression des créneaux du dimanche

        $dayOffWeek = (int) (new \DateTimeImmutable($date))->format('N');

        if ($dayOffWeek === 7) {
            return $this->json([]);
        }

        // ✅ Vérifier que les éléments existent dans la base de données

        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        $staff = $this->entityManager->getRepository(Staff::class)->find($staffId);

        if (!$service || !$staff || $service->getCategory()->getId() !== $categoryId) {
            return $this->json([]);
        }

        // ✅ UTC gestion backend, heures Paris gestion frontend

        $tzParis = new DateTimeZone('Europe/Paris');
        $tzUtc = new DateTimeZone('UTC');

        // ✅ Date et heure actuelle

        $nowParis = new DateTimeImmutable('now', $tzParis);

        // ✅ Date sélectionné depuis le frontend

        $selectedDate = new DateTimeImmutable($date, $tzParis);

        // ✅ Vérifie si la date sélectionnée correspond à aujourd’hui (timezone Europe/Paris)

        $isToday = $selectedDate->format('Y-m-d') === $nowParis->format('Y-m-d');

        // ✅ Créneaux disponibles de 9h à 18h

        $dayStartParis = new DateTimeImmutable("$date 09:00:00", $tzParis);
        $dayEndParis = new DateTimeImmutable("$date 18:00:00", $tzParis);

        // ✅ Pause du midi : heure debut et fin

        $pauseStart = new DateTimeImmutable("$date 12:00:00", $tzParis);
        $pauseEnd = new DateTimeImmutable("$date 14:00:00", $tzParis);

        // ✅ Gestion des RDV en fonction du staff et des créneaux horraires

        $appointments = $this->entityManager
            ->getRepository(Appointment::class)
            ->findForStaffBetween(
                $staff,
                $dayStartParis->setTimezone($tzUtc),
                $dayEndParis->setTimezone($tzUtc)
            );

        // ✅ Durée d'un rendez-vous

        $duration = (int) $service->getDuration();

        $holds = $this->entityManager->getRepository(SlotHold::class)->findActiveHoldsForDay(
            $staff,
            $dayStartParis->setTimezone($tzUtc),
            $dayEndParis->setTimezone($tzUtc)
        );

        $slots = [];

        for ($slotStartParis = $dayStartParis; $slotStartParis < $dayEndParis; $slotStartParis = $slotStartParis->modify('+ 30 minutes')) {

            // ⛔ Créneaux actuellement plus dispos

            if ($isToday && $slotStartParis < $nowParis) continue;

            // ⛔ Rdv supérieur ou égales audébut de la pause && Rdv < à la fin de la pause

            if ($slotStartParis >= $pauseStart && $slotStartParis < $pauseEnd) continue;

            // ⛔ Fin du RDV

            $slotEndParis = $slotStartParis->modify("+ {$duration} minutes");

            // ⛔ Pause déjeuner complète

            if ($slotStartParis < $pauseEnd && $slotEndParis > $pauseStart) continue;

            // ⛔ La fin d'un rendez commence au début de la fin de journée ou plus..

            if ($slotEndParis > $dayEndParis) continue;

            // ⛔ Début des RDV et fin de RDC au format UTC pour la gestion repository
            // ⛔ Début des RDV et fin de RDC au format UTC pour la gestion repository

            $slotStartUtc = $slotStartParis->setTimezone($tzUtc);
            $slotEndUtc = $slotEndParis->setTimezone($tzUtc);

            foreach ($holds as $hold) {
                if ($slotStartUtc < $hold->getEndAt() && $slotEndUtc > $hold->getStartsAt()) {
                    continue 2;
                }
            }

            foreach ($appointments as $appointment) {
                if ($slotStartUtc < $appointment->getEndAt() && $slotEndUtc > $appointment->getStartAt()) {
                    continue 2;
                }
            }

            $slots[] = [
                'start' => $slotStartParis->format('c'),
                'label' => $slotStartParis->format('H:i'),
            ];
        }

        return $this->json($slots, Response::HTTP_OK);
    }


    #[Route('/create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (empty($data['datetime']) || empty($data['service_id']) || empty($data['staff_id'])) {
                return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
            }

            // ✅  FUSEAUX

            $tzUtc = new \DateTimeZone('UTC');

            // ✅ datetime ISO venant du front → UTC

            try {
                $startUtc = (new \DateTimeImmutable($data['datetime']))->setTimezone($tzUtc);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Datetime invalide'], Response::HTTP_BAD_REQUEST);
            }

            // ✅  SERVICE

            $service = $this->entityManager->getRepository(Service::class)->find($data['service_id']);
            if (!$service) {
                return $this->json(['error' => 'Service invalide'], Response::HTTP_BAD_REQUEST);
            }

            $duration = (int) $service->getDuration(); // minutes
            $endUtc = $startUtc->modify("+{$duration} minutes");

            // ✅  STAFF

            $staff = $this->entityManager->getRepository(Staff::class)->find($data['staff_id']);
            if (!$staff) {
                return $this->json(['error' => 'Staff invalide'], Response::HTTP_BAD_REQUEST);
            }

            // ✅  BLOCAGE DES DOUBLONS (UTC vs UTC)

            $conflict = $this->entityManager->getRepository(Appointment::class)->hasConflict($staff, $startUtc, $endUtc);
            if ($conflict) {
                return $this->json([
                    'type' => 'DATETIME_ALREADY_TAKEN',
                    'message' => 'Ce créneau est déjà réservé'
                ], Response::HTTP_CONFLICT);
            }

            // ✅ CRÉATION

            $appointment = new Appointment();

            $appointment->setStartAt($startUtc);
            $appointment->setEndAt($endUtc);
            $appointment->setService($service);
            $appointment->setStaff($staff);
            $appointment->setFirstname($user->getFirstname());
            $appointment->setLastname($user->getLastname());
            $appointment->setEmail($user->getEmail());
            $appointment->setPhone($user->getPhoneNumber());
            $appointment->setUser($user);

            $this->entityManager->persist($appointment);
            $this->entityManager->flush();

            // ✅ NOTIFICATION EMAIL

            $tzParis = new \DateTimeZone('Europe/Paris');
            $startParis = $startUtc->setTimezone($tzParis);

            $this->sendAdminNotification($appointment, $user, $startParis);
            $this->sendClientNotification($appointment, $user, $startParis);

            return $this->json(['message' => 'Rendez-vous confirmé'], Response::HTTP_CREATED);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur creation d\'un rendez-vous clients : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function sendAdminNotification(Appointment $appointment, $user, $startParis): void
    {
        // ✅ NOTIFICATION ADMIN

        $bodyAdmin = $this->render('emails/appointment-admin-notification.html.twig', [
            'name' => $user->getFirstname() . ' ' . $user->getLastname(),
            'email' => $user->getEmail(),
            'datetime' => $startParis,
            'prestation' => $appointment->getService()->getName()
        ])->getContent();

        $this->mailerProvider->sendEmail($this->getParameter('email_from'), 'Nouveau rendez-vous en ligne', $bodyAdmin);
    }

    private function sendClientNotification(Appointment $appointment, $user, $startParis): void
    {
        // ✅ NOTIFICATION CLIENT

        $bodyClient = $this->render('emails/appointment-notification.html.twig', [
            'name' => $user->getFirstname() . ' ' . $user->getLastname(),
            'datetime' => $startParis,
            'prestation' => $appointment->getService()->getName()
        ])->getContent();

        $this->mailerProvider->sendEmail($user->getEmail(), 'Confirmation de votre rendez-vous', $bodyClient);
    }

    private function getErrorMessages(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors() as $key => $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $child) {
            if ($child->isSubmitted() && !$child->isValid()) {
                $errors[$child->getName()] = $this->getErrorMessages($child);
            }
        }
        return $errors;
    }
}