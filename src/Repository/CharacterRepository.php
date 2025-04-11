<?php

namespace App\Repository;

use App\Entity\Character;
use App\Model\CharacterFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Character>
 */
class CharacterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Character::class);
    }

    //    /**
    //     * @return Character[] Returns an array of Character objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Character
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function getFilteredQuery(CharacterFilter $filter): Query
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.league', 'l')
            ->leftJoin('l.universe', 'u');

        if ($filter->search) {
            $qb->andWhere('c.name LIKE :search')
                ->setParameter('search', '%' . $filter->search . '%');
        }

        if (!empty($filter->leagues)) {
            $qb->andWhere('c.league IN (:leagues)')
                ->setParameter('leagues', $filter->leagues);
        }

        if (!empty($filter->universes)) {
            $qb->andWhere('u IN (:universes)')
                ->setParameter('universes', $filter->universes);
        }

        return $qb->orderBy('c.name', 'ASC')->getQuery();
    }
}
