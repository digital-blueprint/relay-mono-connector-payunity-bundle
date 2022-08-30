<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\Entity\PaymentDataPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response;

class PaymentDataService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        ManagerRegistry $managerRegistry
    ) {
        $manager = $managerRegistry->getManager('dbp_relay_mono_connector_payunity_bundle');
        assert($manager instanceof EntityManagerInterface);
        $this->em = $manager;
        $this->logger = new NullLogger();
    }

    public function createPaymentData(PaymentPersistence $payment, Checkout $checkout): void
    {
        $paymentDataPersistence = PaymentDataPersistence::fromPaymentAndCheckout($payment, $checkout);
        $createdAt = new \DateTime();
        $paymentDataPersistence->setCreatedAt($createdAt);

        try {
            $this->em->persist($paymentDataPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->logger->error('Payment data could not be created!', ['exception' => $e]);
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'Payment data could not be created!');
        }
    }

    public function getByPaymentIdentifier(string $paymentIdentifier)
    {
        /** @var PaymentDataPersistence $paymentDataPersistence */
        $paymentDataPersistence = $this->em
            ->getRepository(PaymentDataPersistence::class)
            ->findOneBy([
                'paymentIdentifier' => $paymentIdentifier,
            ], [
                'createdAt' => 'DESC',
            ]);

        if (!$paymentDataPersistence) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, 'Payment data was not found!', 'mono:payment-data-not-found');
        }

        return $paymentDataPersistence;
    }

    public function cleanupByPaymentIdentifier(string $paymentIdentifier)
    {
        $paymentDataPersistences = $this->em
            ->getRepository(PaymentDataPersistence::class)
            ->findBy([
                'paymentIdentifier' => $paymentIdentifier,
            ]);

        foreach ($paymentDataPersistences as $paymentDataPersistence) {
            $this->em->remove($paymentDataPersistence);
        }
        $this->em->flush();
    }
}
