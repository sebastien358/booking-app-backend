<?php

namespace App\Repository;

use App\Entity\Staff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Staff>
 */
class StaffRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Staff::class);
    }

    public function findAllAdmin(int $offset, int $limit)
    {
        return $this->createQueryBuilder('staff')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('staff')
            ->andWhere('staff.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('staff.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllSearchAdmin(string $search)
    {
        return $this->createQueryBuilder('staff')
            ->where('LOWER(staff.firstname) LIKE :search')
            ->orWhere('LOWER(staff.lastname) LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->getQuery()
            ->getResult();
    }

    public function countAdmin()
    {
        return $this->createQueryBuilder('staff')
            ->select('count(staff.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Staff[] Returns an array of Staff objects
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

    //    public function findOneBySomeField($value): ?Staff
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
