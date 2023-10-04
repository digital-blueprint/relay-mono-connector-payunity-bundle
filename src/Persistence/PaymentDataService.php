<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Persistence;

use Dbp\Relay\MonoBundle\Persistence\PaymentPersistence;
use Dbp\Relay\MonoConnectorPayunityBundle\PayUnity\Checkout;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class PaymentDataService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->logger = new NullLogger();
    }

    public function checkConnection(): void
    {
        $this->em->getConnection()->getWrappedConnection();
    }

    public function createPaymentData(PaymentPersistence $payment, Checkout $checkout): void
    {
        $paymentDataPersistence = PaymentDataPersistence::fromPaymentAndCheckout($payment, $checkout);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $paymentDataPersistence->setCreatedAt($now);
        $this->em->persist($paymentDataPersistence);
        $this->em->flush();
    }

    public function getByPaymentIdentifier(string $paymentIdentifier): ?PaymentDataPersistence
    {
        /** @var PaymentDataPersistence $paymentDataPersistence */
        $paymentDataPersistence = $this->em
            ->getRepository(PaymentDataPersistence::class)
            ->findOneBy([
                'paymentIdentifier' => $paymentIdentifier,
            ], [
                'createdAt' => 'DESC',
            ]);

        return $paymentDataPersistence;
    }

    public function getByCheckoutId(string $checkoutId): ?PaymentDataPersistence
    {
        /** @var PaymentDataPersistence $paymentDataPersistence */
        $paymentDataPersistence = $this->em
            ->getRepository(PaymentDataPersistence::class)
            ->findOneBy([
                'pspIdentifier' => $checkoutId,
            ]);

        return $paymentDataPersistence;
    }

    public function cleanupByPaymentIdentifier(string $paymentIdentifier): void
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
