<?php

namespace App\Controller\admin;

use App\Entity\Category;
use App\Entity\Service;
use App\Form\ServiceType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/service')]
#[IsGranted("ROLE_ADMIN")]
class ServiceAdminController extends AbstractController
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
            $offset = (int) $request->query->get('offset');
            $limit = (int) $request->query->get('limit');

            $services = $this->entityManager->getRepository(Service::class)->findAllServicesLazyLoad($offset, $limit);
            $count = $this->entityManager->getRepository(Service::class)->findAllCount();

            $dataServices = $serializer->normalize($services, 'json', ['groups' => ['services', 'categories']]);

            return $this->json([
                'services' => $dataServices,
                'count' => (int) $count
            ], Response::HTTP_OK);

        } catch(\Throwable $e) {
            $this->logger->error('Admin service error : ', ['message' => $e->getMessage()]);
            return $this->json(['error' => 'Admin service error : '], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $search = trim((string) $request->query->get('search'));

            if (empty($search)) {
                return $this->json(['error' => 'Search parameter is empty'], Response::HTTP_BAD_REQUEST);
            }

            $searchTerm = $this->entityManager->getRepository(Service::class)->findAllServiceSearch($search);

            $dataSearch = $serializer->normalize($searchTerm, 'json', ['groups' => ['services', 'categories']]);
            return $this->json($dataSearch, Response::HTTP_OK);

        } catch(\Throwable $e) {
            $this->logger->error('Erreur liste des rendez-vous clients : ', ['message' => $e->getMessage()]);
            return $this->json(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['GET'])]
    public function show(int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $service= $this->entityManager->getRepository(Service::class)->find($id);
            if (!$service) {
                return $this->json(['error' => 'Service introuvable'], Response::HTTP_NOT_FOUND);
            }

            $dataService = $serializer->normalize($service, 'json', ['groups' => ['services', 'cat'],
                'circulation_reference_handler', function ($object) {
                    return $object->getId();
                }
            ]);

            return $this->json($dataService, Response::HTTP_OK,);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des contacts : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/create', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

            $service = new Service();

            $form = $this->createForm(ServiceType::class, $service);
            $form->submit($data);

            if (!$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return $this->json(['error' => $errors], Response::HTTP_BAD_REQUEST);
            }

            $category = $this->entityManager->getRepository(Category::class)->find($data['category']);
            if (!$category) {
                return $this->json(['error' => 'Category introuvable'], Response::HTTP_BAD_REQUEST);
            }

            $service->setCategory($category);

            $this->entityManager->persist($service);
            $this->entityManager->flush();

            return $this->json(['message' => 'Service create'], Response::HTTP_CREATED);
        } catch(\Throwable $e) {
            $this->logger->error('Admin service create error : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $service = $this->entityManager->getRepository(Service::class)->find($id);
            if (!$service) {
                return $this->json(['error' => 'Service introuvable'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($service);
            $this->entityManager->flush();

            return $this->json(['message' => 'Le service a été supprimé'], Response::HTTP_OK);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur de la récupération des contacts : ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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