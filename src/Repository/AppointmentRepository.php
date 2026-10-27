<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Staff;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function findForStaffBetween(Staff $staff, \DateTimeImmutable $dayStart, \DateTimeImmutable $dayEnd): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.staff = :staff')
            ->andWhere('a.startAt < :dayEnd')
            ->andWhere('a.endAt > :dayStart')
            ->setParameter('staff', $staff)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getResult();
    }

    public function hasConflict(Staff $staff, \DateTimeImmutable $startUtc, \DateTimeImmutable $endUtc): bool {
        return (bool) $this->createQueryBuilder('a')
            ->andWhere('a.staff = :staff')
            ->andWhere('a.startAt < :endUtc')
            ->andWhere('a.endAt > :startUtc')
            ->setParameter('staff', $staff)
            ->setParameter('startUtc', $startUtc)
            ->setParameter('endUtc', $endUtc)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllAppointments(int $limit, int $offset)
    {
        return $this->createQueryBuilder('a')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllAppointmentsUser(User $user, int $offset, int $limit)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllAppointmentsSearch(string $search): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.firstname LIKE :search OR a.lastname LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->getQuery()
            ->getResult();
    }

    public function findAllAppointmentUser(User $user)
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllCount()
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllCountUser(User $user)
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Appointment[] Returns an array of Appointment objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Appointment
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
