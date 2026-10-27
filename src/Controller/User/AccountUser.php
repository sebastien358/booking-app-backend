<?php

namespace App\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user')]
#[IsGranted('ROLE_USER')]
class AccountUser extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/account', methods: ['GET'])]
    public function account(SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $dataUser = $serializer->normalize($user, 'json', ['groups' => ['user'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json($dataUser, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération du compte de l\'utilisateur', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}