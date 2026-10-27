<?php

namespace App\Controller\admin;

use App\Entity\Testimonial;
use App\Services\TestimonialService;
use App\Services\UploadFileService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/admin/testimonial')]
#[IsGranted("ROLE_ADMIN")]
class TestimonialAdminController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TestimonialService $testimonialService;
    private UploadFileService $uploadFileService;
    private LOggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, TestimonialService $testimonialService,
        UploadFileService $fileService, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->testimonialService = $testimonialService;
        $this->uploadFileService = $fileService;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function index(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $offset = trim((int) $request->query->get('offset'));
            $limit = trim((int) $request->query->get('limit'));

            $testimonials = $this->entityManager->getRepository(Testimonial::class)->findAllAdminTestimonials($offset, $limit);

            $testimonials = $this->testimonialService->testimonialDisplay($testimonials, $request, $serializer);
            $count = $this->entityManager->getRepository(Testimonial::class)->testimonialCount();

            return new JsonResponse([
                'testimonials' => $testimonials,
                'count' => (int) $count
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des témoignages : ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $search = trim((string) $request->query->get('search'));

            $testimonials = $this->entityManager->getRepository(Testimonial::class)->findAllTestimonialSearch($search);

            $dataTestimonials = $serializer->normalize($testimonials, 'json', ['groups' => 'testimonials']);

            return new JsonResponse($dataTestimonials, Response::HTTP_OK,);
        } catch(\Throwable $e) {
            $this->logger->error('Erreur search contacts: ', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['GET'])]
    public function show(int $id, Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $testimonials = $this->entityManager->getRepository(Testimonial::class)->find($id);
            if (!$testimonials) {
                return $this->json(['error' => 'Erreur de la récupération d\'un témoignage'], Response::HTTP_NOT_FOUND);
            }

            $testimonials->setIsRead(true);

            $testimonials = $this->testimonialService->testimonialDisplay($testimonials, $request, $serializer);

            $this->entityManager->flush();

            return $this->json($testimonials, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des témoignages : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/show/{id}', methods: ['DELETE'])]
    public function deleteId(int $id): JsonResponse
    {
        try {
            $testimonial = $this->entityManager->getRepository(Testimonial::class)->find($id);
            if (!$testimonial) {
                return $this->json(['error' => 'Erreur de la récupération d\'un témoignage'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($testimonial);
            $this->entityManager->flush();

            return new JsonResponse(null, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des témoignages : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $testimonial = $this->entityManager->getRepository(Testimonial::class)->find($id);
            if (!$testimonial) {
                return $this->json(['error' => 'Erreur de la récupération d\'un témoignage'], Response::HTTP_NOT_FOUND);
            }

            $image = $testimonial->getPicture();

            $this->uploadFileService->removeTestimonialImage($testimonial, $image);

            $this->entityManager->remove($testimonial);
            $this->entityManager->flush();

            return $this->json(['message' => 'Le témoignage a été supprimé'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la suppression d\'un témoignage : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/toggle', methods: ['PUT'])]
    public function toggle(int $id): JsonResponse
    {
        try {
            $testimonial = $this->entityManager->getRepository(Testimonial::class)->find($id);
            if (!$testimonial) {
                return $this->json(['error' => 'Erreur le témoignage est innexistant', Response::HTTP_BAD_REQUEST]);
            }

            $testimonial->setIsPublished(!$testimonial->getIsPublished());
            $this->entityManager->flush();

            return $this->json(['is_published' => $testimonial->getIsPublished()], Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur toggle témoignage : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}