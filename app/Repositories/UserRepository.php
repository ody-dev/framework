<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace App\Repositories;

use App\Entities\User;
use Ody\DB\Doctrine\Repository\BaseRepository;

/**
 * Repository for the User entity
 *
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    /**
     * @var string
     */
    protected string $entityClass = User::class;

    /**
     * Find a user by email
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Find users with a specific name
     *
     * @param string $name
     * @return array<User>
     */
    public function findByName(string $name): array
    {
        return $this->findBy(['name' => $name]);
    }

    /**
     * Search for users using a partial name match
     *
     * @param string $searchTerm
     * @param int $limit
     * @param int $offset
     * @return array<User>
     */
    public function searchByName(string $searchTerm, int $limit = 10, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.name LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('u.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}