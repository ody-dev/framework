<?php

namespace App\Repositories;

use Ody\DB\Doctrine\Facades\DBAL;

class UserRepository
{
    public function getAll()
    {
        return DBAL::fetchAllAssociative("SELECT * FROM users");
    }

    public function findByEmail(string $email)
    {
        // First try by username
        $user = DBAL::fetchAllAssociative("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user) {
            return false;
        }

        return $user;
    }

    public function findById($id)
    {
        return DBAL::fetchAllAssociative("SELECT * FROM users WHERE id = ?", [$id]);
    }
}