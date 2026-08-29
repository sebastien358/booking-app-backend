<?php

namespace App\Controller\User;

use App\Entity\Service;
use Stripe\StripeClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user')]
#[IsGranted("ROLE_USER")]
final class PaymentController extends AbstractController
{
    private StripeClient $stripe;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(string $stripeSecretKey, LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->stripe = new StripeClient($stripeSecretKey);
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    #[Route('/payment', methods: ['POST'])]
    public function payment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $token = $data['token'] ?? null;
            $serviceId = $data['service_id'] ?? null;

            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            if (!$token || !$serviceId) {
                return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
            }

            $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
            if (!$service) {
                return $this->json(['error' => 'Service not found'], Response::HTTP_NOT_FOUND);
            }

            $price = $service->getPrice();
            $amountInCents = (int) round($price * 100);

            if ($amountInCents <= 0) {
                return $this->json(['error' => 'Montant invalide'], Response::HTTP_BAD_REQUEST);
            }

            $total = 100;

            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'name' => trim($user->getFirstname() . ' ' . $user->getLastname()),
                'metadata' => [
                    'app_user_id' => (string) $user->getId(),
                    'service_id' => (string) $service->getId()
                ]
            ]);

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $total,
                'currency' => 'eur',
                'customer' => $customer->id,
                'payment_method_data' => [
                    'type' => 'card',
                    'card' => ['token' => $token]
                ],
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ],
                'metadata' => [
                    'service_id' => (string) $service->getId(),
                    'user_id' => (string) $user->getId()
                ]
            ], [
                'idempotency_key' => 'pi_' . $user->getId() . '_' . $service->getId() . '_' . $token
            ]);

            if ($paymentIntent->status === 'succeeded') {
                $this->entityManager->flush();
                return $this->json([
                    'type' => 'SUCCESS_PAYMENT',
                    'message' => 'Paiement accepté',
                    'payment_intent_id' => $paymentIntent->id
                ], Response::HTTP_CREATED);
            }

            if ($paymentIntent->status === 'requires_action') {
                return $this->json(['type' => 'REQUIRES_ACTION', 'clientSecret' => $paymentIntent->client_secret], Response::HTTP_OK);
            }

            return $this->json(['type' => 'ERROR_PAYMENT', 'message' => 'Paiement refusé'], Response::HTTP_CONFLICT);
        } catch (\Throwable $e) {
            $this->logger->error('Le paiement a échoué : ', [$e->getMessage()]);
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/payment/refund', methods: ['POST'])]
    public function refund(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $paymentIntentId = $data['payment_intent_id'] ?? null;

            $user = $this->getUser();
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
            }

            if (!$paymentIntentId) {
                return $this->json(['error' => 'payment_intent_id manquant'], Response::HTTP_BAD_REQUEST);
            }

            $this->stripe->refunds->create([
                'payment_intent' => $paymentIntentId,
            ]);

            $this->logger->warning('Remboursement effectué suite à échec de réservation', [
                'payment_intent_id' => $paymentIntentId,
                'user_id' => $user->getId(),
            ]);

            return $this->json(['type' => 'REFUNDED', 'message' => 'Remboursement effectué']);

        } catch (\Throwable $e) {
            $this->logger->error('Échec du remboursement : ', ['error' => $e->getMessage()]);
            return $this->json(['message' => 'Le remboursement a échoué'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}