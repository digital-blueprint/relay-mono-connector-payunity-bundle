<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\MonoBundle\Entity\PaymentPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\Api\PaymentData;
use Dbp\Relay\MonoConnectorPayunityBundle\Entity\PaymentDataPersistence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

class PaymentDataService
{
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
    }

    public function createPaymentData(PaymentPersistence $payment, PaymentData $paymentData): PaymentData
    {
        $paymentDataPersistence = PaymentDataPersistence::fromPaymentAndPaymentData($payment, $paymentData);
        $createdAt = new \DateTime();
        $paymentDataPersistence->setCreatedAt($createdAt);

        try {
            $this->em->persist($paymentDataPersistence);
            $this->em->flush();
        } catch (\Exception $e) {
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'Payment data could not be created!', 'mono:payment-data-not-created', ['message' => $e->getMessage()]);
        }

        return $paymentData;
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
}