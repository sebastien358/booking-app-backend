<?php

namespace App\Controller\admin;

use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[IsGranted("ROLE_ADMIN")]
#[Route('/api/admin')]
class UserAdminController extends AbstractController
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        LoggerInterface $logger, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/account', methods: ['GET'])]
    public function infoMe(SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Itilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $dataUser = $serializer->normalize($user, 'json', ['groups' => ['user'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json($dataUser, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des données utilisateur', [$e->getMessage()]);
            return $this->json(['error' => 'Erreur de la récupération des données utilisateur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/account/edit', methods: ['POST'])]
    public function edit(Request $request): Response
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Itilisateur introuvable'], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $form = $this->createForm(UserType::class, $user);
            $form->submit($data, false);

            if (!$form->isSubmitted() || !$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            if (!empty($data['password'])) {
                $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashed);
            }

            $this->entityManager->flush();

            return $this->json([ 'message' => 'Profil mis à jour'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des données utilisateur', [$e->getMessage()]);
            return $this->json(['error' => 'Erreur de la récupération des données utilisateur'], Response::HTTP_INTERNAL_SERVER_ERROR);
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