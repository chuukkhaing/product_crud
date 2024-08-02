<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findByFilters($filters, $sortField, $sortOrder, $limit, $offset)
    {
        $qb = $this->createQueryBuilder('p');

        if (isset($filters['name'])) {
            $qb->andWhere('p.name LIKE :name')
               ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (isset($filters['minPrice']) && isset($filters['maxPrice'])) {
            $qb->andWhere('p.price BETWEEN :minPrice AND :maxPrice')
               ->setParameter('minPrice', $filters['minPrice'])
               ->setParameter('maxPrice', $filters['maxPrice']);
        }

        if (isset($filters['stockQuantity'])) {
            $qb->andWhere('p.stockQuantity = :stockQuantity')
               ->setParameter('stockQuantity', $filters['stockQuantity']);
        }

        if (isset($filters['dateFrom']) && isset($filters['dateTo'])) {
            $qb->andWhere('p.createdDatetime BETWEEN :dateFrom AND :dateTo')
               ->setParameter('dateFrom', $filters['dateFrom'])
               ->setParameter('dateTo', $filters['dateTo']);
        }

        $qb->orderBy('p.' . $sortField, $sortOrder)
           ->setMaxResults($limit)
           ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findAllProducts(): array
    {
        return $this->findAll();
    }
}
