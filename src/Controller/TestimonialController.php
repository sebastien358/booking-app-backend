<?php

namespace App\Controller;

use App\Entity\Testimonial;
use App\Form\TestimonialType;
use App\Services\MailerProvider;
use App\Services\TestimonialService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/testimonial')]
class TestimonialController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TestimonialService $testimonialService;
    private MailerProvider $mailerProvider;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager, TestimonialService $testimonialService,
        MailerProvider $mailerProvider, LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->testimonialService = $testimonialService;
        $this->mailerProvider = $mailerProvider;
        $this->logger = $logger;
    }

    #[Route('/list', methods: ['GET'])]
    public function index(Request $request, SerializerInterface $serializer): JsonResponse
    {
        try {
            $offset = (int) $request->query->get('offset');
            $limit = (int) $request->query->get('limit');

            $testimonials = $this->entityManager->getRepository(Testimonial::class)->findAllTestimonials($limit, $offset);

            $dataList = $this->testimonialService->testimonialDisplay($testimonials, $request, $serializer);
            return $this->json($dataList, Response::HTTP_OK);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur de la récupération des témoignages : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/create', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $testimonial = new Testimonial();

            $form = $this->createForm(TestimonialType::class, $testimonial);

            $data = $request->request->all();
            $form->submit($data, false);

            if (!$form->isSubmitted() || !$form->isValid()) {
                $errors = $this->getErrorMessages($form);
                return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
            }

            $image = $request->files->get('image') ?? null;

            $this->testimonialService->testimonialImage($image, $testimonial);

            $this->entityManager->persist($testimonial);
            $this->entityManager->flush();

            $body = $this->render('emails/testimonial-notification.html.twig', [
                'author' => $testimonial->getAuthor(),
                'message' => $testimonial->getMessage()
            ])->getContent();

            $emailFrom = $this->getParameter('email_from');
            $this->mailerProvider->sendEmail($emailFrom, 'Nouveau témoignage publié sur votre site', $body);

            return $this->json([
                'id' => $testimonial->getId(),
                'author' => $testimonial->getAuthor(),
                'job' => $testimonial->getJob(),
                'rating' => $testimonial->getRating(),
                'message' => $testimonial->getMessage(),
                'picture' => $testimonial->getPicture()
                    ? ['filename' => $testimonial->getPicture()->getFilename()]
                    : null,
            ], Response::HTTP_CREATED);
        } catch(\Throwable $e) {
            $this->logger->error('Error le témoignage n\'a pas pu etre envoyé', [$e->getMessage()]);
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
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