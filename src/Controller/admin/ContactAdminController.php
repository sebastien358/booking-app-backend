<?php

namespace App\Controller\admin;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/contact')]
#[IsGranted("ROLE_ADMIN")]
class ContactAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $offset = (int) $request->query->get('offset');
            $limit = (int) $request->query->get('limit');

            $contacts = $this->entityManager->getRepository(Contact::class)->findAllContacts($offset, $limit);

            $dataContacts = $serializer->normalize($contacts, 'json', ['groups' => ['contacts']]);
            $count = $this->entityManager->getRepository(Contact::class)->findAllCount();

            return $this->json([
                    'contacts' => $dataContacts,
                    'count' => (int) $count
                ], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des contacts : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $search = trim((string) $request->query->get('search'));

            $contacts = $this->entityManager->getRepository(Contact::class)->findAllContactSearch($search);
            $dataContacts = $serializer->normalize($contacts, 'json', ['groups' => 'contacts']);

            return $this->json($dataContacts, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur search contacts: ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['GET'])]
    public function show(int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $contact = $this->entityManager->getRepository(Contact::class)->find($id);
            if (!$contact) {
                return $this->json(['error' => 'Message introuvable'], Response::HTTP_NOT_FOUND);
            }

            $contact->setIsRead(true);
            $dataContact = $serializer->normalize($contact, 'json', ['groups' => 'contact']);

            $this->entityManager->flush();

            return $this->json($dataContact, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des contacts : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $contact = $this->entityManager->getRepository(Contact::class)->find($id);
            if (!$contact) {
                return new JsonResponse(['error' => 'Message introuvable'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($contact);
            $this->entityManager->flush();

            return new JsonResponse(null, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la suppression des contacts : ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}