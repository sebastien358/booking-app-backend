<?php

namespace App\Controller\admin;

use App\Entity\Picture;
use App\Entity\Staff;
use App\Form\StaffType;
use App\Services\StaffService;
use App\Services\UploadFileService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/staff')]
#[IsGranted("ROLE_ADMIN")]
class StaffAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UploadFileService $uploadFileService;
    private StaffService $staffService;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, UploadFileService $uploadFileService,
        StaffService $staffService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->uploadFileService = $uploadFileService;
        $this->staffService = $staffService;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $offset = $request->query->getInt('offset');
            $limit = $request->query->getInt('limit');

            if (!is_numeric($offset) || !is_numeric($limit)) {
                return $this->json(['error' => 'Les données ne sont pas valides'], Response::HTTP_NOT_FOUND);
            }

            $staffs = $this->entityManager->getRepository(Staff::class)->findAllAdmin($offset, $limit);
            $dataStaffs = $this->staffService->staffDisplay($staffs, $request, $serializer);

            $countStaffs = $this->entityManager->getRepository(Staff::class)->countAdmin();

            return $this->json([
                'staffs' => $dataStaffs,
                'count' => (int) $countStaffs
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des salariés', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $search = trim($request->query->getString('search'));
            if (empty($search)) {
                return $this->json(['error' => 'Les données ne sont pas valides'], Response::HTTP_NOT_FOUND);
            }

            $staffs = $this->entityManager->getRepository(Staff::class)->findAllSearchAdmin($search);
            $dataStaff = $this->staffService->staffDisplay($staffs, $request, $serializer);

            return $this->json($dataStaff, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des salariés', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['GET'])]
    public function id(int $id, Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $staffs = $this->entityManager->getRepository(Staff::class)->find($id);
            if (!$staffs) {
                return $this->json(['error' => 'Salarié introuvable'], Response::HTTP_NOT_FOUND);
            }

            $dataStaff = $this->staffService->staffDisplay($staffs, $request, $serializer);

            return $this->json($dataStaff, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération d\'un salarié', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/toggle/{id}', methods: ['POST'])]
    public function toggle(int $id, Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $staffs = $this->entityManager->getRepository(Staff::class)->find($id);
            if (!$staffs) {
                return $this->json(['error' => 'Utiliosateur introuvable'], Response::HTTP_NOT_FOUND);
            }

            $dataStaff = $this->staffService->staffDisplay($staffs, $request, $serializer);

            $staffs->setIsActive(!$staffs->isActive());
            $this->entityManager->flush();

            return $this->json($dataStaff, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération d\'un salarié', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/add', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['message' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $staff = new Staff();

            $form = $this->createForm(StaffType::class, $staff);
            $data = $request->request->all();
            $form->submit($data);

            if (!$form->isSubmitted() && !$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return $this->json(['error' => $errors], Response::HTTP_BAD_REQUEST);
            }

            $image = $request->files->get('picture') ?? null;
            $this->staffService->staffImage($staff, $image);

            $this->entityManager->persist($staff);
            $this->entityManager->flush();

            return $this->json(['message' => 'Un employé a été ajouté'], Response::HTTP_CREATED);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de l\'ajout d\'un salarié : ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id, Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $staff = $this->entityManager->getRepository(Staff::class)->find($id);
            if (!$staff) {
                return $this->json(['error' => 'Salarié introuvable'], Response::HTTP_NOT_FOUND);
            }

            $picture = $staff->getPicture();
            $this->uploadFileService->removeImage($staff, $picture);

            $this->entityManager->remove($staff);
            $this->entityManager->flush();

            return $this->json(['message' => 'Le salarié a été supprimé'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération d\'un salarié', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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