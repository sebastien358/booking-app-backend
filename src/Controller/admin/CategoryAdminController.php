<?php

namespace App\Controller\admin;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/category')]
#[IsGranted("ROLE_ADMIN")]
class CategoryAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/list' , methods: ['GET'])]
    public function index(SerializerInterface $serializer): Response
    {
        try {
            $categories = $this->entityManager->getRepository(Category::class)->findAll();

            $dataCategories = $serializer->normalize($categories, 'json', ['groups' => 'categories']);
            return $this->json($dataCategories);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des catégories : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}