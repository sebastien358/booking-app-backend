<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Services\StaffService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/staffs')]
class StaffController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private StaffService $staffService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, StaffService $staffService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->staffService = $staffService;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $staffs = $this->entityManager->getRepository(Staff::class)->findAllActive();
            $dataStaffs = $this->staffService->staffDisplay($staffs, $request, $serializer);

            return $this->json($dataStaffs, Response::HTTP_OK);
       } catch(\Throwable $e) {
           $this->logger->error('Erreur de la récupération des salariés : ', [$e->getMessage()]);
          return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}