<?php

namespace App\Controller\admin;

use App\Entity\Contact;
use App\Services\ContactService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    private ContactService $contactService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, ContactService $contactService, LoggerInterface $logger
    ){
        $this->entityManager = $entityManager;
        $this->contactService = $contactService;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function list(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $offset = (int) $request->query->get('offset');
            $limit = (int) $request->query->get('limit');

            $contacts = $this->entityManager->getRepository(Contact::class)->findAllContacts($offset, $limit);

            $dataContacts = $this->contactService->contactDisplay($contacts, $serializer);
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
    public function search(Request $request, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $search = trim((string) $request->query->get('search'));

            $contacts = $this->entityManager->getRepository(Contact::class)->findAllContactSearch($search);
            $dataContacts = $this->contactService->contactDisplay($contacts, $serializer);

            return $this->json($dataContacts, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur search contacts: ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['GET'])]
    public function show(int $id, SerializerInterface $serializer): Response
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            $contacts = $this->entityManager->getRepository(Contact::class)->find($id);
            if (!$contacts) {
                return $this->json(['error' => 'Message introuvable'], Response::HTTP_NOT_FOUND);
            }

            $dataContact = $this->contactService->contactDisplay($contacts, $serializer);

            $contacts->setIsRead(true);
            $this->entityManager->flush();

            return $this->json($dataContact, Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des contacts : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): Response
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

            $this->entityManager->remove($contact);
            $this->entityManager->flush();

            return $this->json(['message' => 'Le message a été supprimé'], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la suppression des contacts : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}