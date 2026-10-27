<?php

namespace App\Repository;

use App\Entity\SlotHold;
use App\Entity\Staff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SlotHold>
 */
class SlotHoldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SlotHold::class);
    }

    public function findActiveHold(Staff $staff, \DateTimeImmutable $start, \DateTimeImmutable $end): ?SlotHold
    {
        return $this->createQueryBuilder('h')
            ->where('h.staff = :staff')
            ->andWhere('h.startsAt = :start')
            ->andWhere('h.endAt = :end')
            ->setParameter('staff', $staff)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveHoldsForDay(Staff $staff, \DateTimeImmutable $dayStartUtc, \DateTimeImmutable $dayEndUtc): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.staff = :staff')
            ->andWhere('h.expiresAt > :now')
            ->andWhere('h.startsAt < :dayEnd')
            ->andWhere('h.endAt > :dayStart')
            ->setParameter('staff', $staff)
            ->setParameter('dayStart', $dayStartUtc)
            ->setParameter('dayEnd', $dayEndUtc)
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->getQuery()
            ->getResult();
    }

    public function deleteExpired(): int {
        return $this->createQueryBuilder('h')->delete()
            ->where('h.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->getQuery()
            ->execute();
    }

    //    /**
    //     * @return SlotHold[] Returns an array of SlotHold objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?SlotHold
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
