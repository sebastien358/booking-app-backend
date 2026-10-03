<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Services\MailerProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/user')]
class UserController extends AbstractController
{
    private LoggerInterface $logger;
    private MailerProvider $mailerProvider;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        LoggerInterface $logger, MailerProvider $mailerProvider,
        UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->mailerProvider = $mailerProvider;
        $this->passwordHasher = $passwordHasher;
        $this->entityManager = $entityManager;
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/me', methods: ['GET'])]
    public function me(SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();

            $dataUser = $serializer->normalize($user, 'json', ['groups' => ['user'],
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                }
            ]);
            return $this->json($dataUser, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération de l\'utilisateur connecté', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/update/me', methods: ['POST'])]
    public function updateMe(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (
                empty($data['firstname']) ||
                empty($data['lastname']) ||
                empty($data['phoneNumber']) ||
                empty($data['email'])
            ) {
                return $this->json(['error' => 'Donnees manquantes'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur inexistant'], Response::HTTP_FORBIDDEN);
            }

            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setPhoneNumber($data['phoneNumber']);
            $user->setEmail($data['email']);

            if (!empty($data['newPassword'])) {
                $newPassword = $this->passwordHasher->hashPassword($user, $data['newPassword']);
                $user->setPassword($newPassword);
            }

            $this->entityManager->flush();

            return $this->json(['message' => 'Vos données ont été modifiées'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération de l\'utilisateur connecté', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/delete/account', methods: ['DELETE'])]
    public function deleteAccount(): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur inexistant'], Response::HTTP_FORBIDDEN);
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->json(['message' => 'Compte supprimé'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la suppression du compte', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (
                empty($data['email']) ||
                empty($data['password']) ||
                empty($data['firstname']) ||
                empty($data['lastname'] ||
                empty($data['phoneNumber'])))
            {
                return $this->json(['error' => 'Donnees manquantes'], Response::HTTP_BAD_REQUEST);
            }

            $userExist = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($userExist) {
                return $this->json(['type' => 'EMAIL_ALREADY_EXISTS', 'message' => 'Un compte existe déjà avec cet email.'], Response::HTTP_CONFLICT);
            }

            $user = new User();
            $user->setRoles(['ROLE_USER']);

            $form = $this->createForm(UserType::class, $user);
            $form->submit($data);

            if (!$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return $this->json($errors, Response::HTTP_BAD_REQUEST);
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Utilisateur ajouté'
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            $this->logger->error("Erreur de l'enregistrement d'un utilisateur", ['error' => $e->getMessage()]);
            return $this->json(['error' => "Erreur de l'enregistrement d'un utilisateur"], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/existing', methods: ['POST'])]
    public function existing(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $email = $data['email'] ?? null;

            if (!$email) {
                return new JsonResponse(['error' => 'Email manquant'], Response::HTTP_BAD_REQUEST);
            }

            $userExist = (bool) $userRepository->findOneBy(['email' => $email]);

            return $this->json(['existing' => $userExist], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur verification utilissateur', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/request-password', methods: ['POST'])]
    public function requestPassword(Request $request, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $email = $data['email'] ?? null;

            if (!$email) {
                return $this->json(['error' => 'Email manquant'], Response::HTTP_BAD_REQUEST);
            }

            $userExist = $userRepository->findOneBy(['email' => $email]);
            if (!$userExist) {
                return $this->json([
                    'type' => 'ERROR_REQUEST_PASSWORD',
                    'message' => 'Cet email n\'existe pas dans nos données'
                ], Response::HTTP_NOT_FOUND);
            }

            $token = bin2hex(random_bytes(32));
            $date = new \DateTimeImmutable('+1 hour');

            $userExist->setToken($token);
            $userExist->setTokenExpire($date);

            $this->entityManager->flush();

            $url = $this->getParameter('FRONTEND_URL') . '/reset-password/' . $token;
            $this->sendResetNotification($userExist, $url);

            return $this->json(['message' => 'Un email a été envoyé'], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur verification utilissateur', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reset-password/{token}', methods: ['POST'])]
    public function resetPassword(Request $request, string $token, UserRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (!$data || empty($data['password'])) {
                return $this->json(['error' => 'Mot de passe requis'], Response::HTTP_BAD_REQUEST);
            }

            $user = $userRepository->findOneBy(['resetToken' => $token]);
            if (!$user) {
                return $this->json(['error' => 'Token non valide'], Response::HTTP_FORBIDDEN);
            }

            $expiresAt = $user->getResetTokenExpiresAt();

            if (!$expiresAt) {
                return $this->json(['type' => 'RESET-PASSWORD', 'message' => 'La demande de réinitialisation a expirée'], Response::HTTP_BAD_REQUEST);
            }

            $datetime = new \DateTimeImmutable();

            if ($expiresAt < $datetime) {
                return $this->json(['type' => 'RESET-PASSWORD', 'message' => 'La demande de réinitialisation a expirée'], Response::HTTP_CONFLICT);
            }

            $newPassword = $data['password'];
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);

            $user->setPassword($hashedPassword);
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $this->entityManager->flush();

            return $this->json(['message' => 'Mot de passe modifié'], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur modification de mot de passe', ['error' => $e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendResetNotification(User $userExist, string $url): void
    {
        $body = $this->render('emails/reset-password.html.twig', [
            'firstname' => $userExist->getFirstname(),
            'url' => $url,
        ])->getContent();

        $this->mailerProvider->sendEmail($userExist->getEmail(), 'Demande de réinitialisation de mot de passe', $body);
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