<?php
/*
 *  This file is part of ODY framework.
 *
 *  @link     https://ody.dev
 *  @document https://ody.dev/docs
 *  @license  https://github.com/ody-dev/ody-foundation/blob/master/LICENSE
 */

namespace App\Http\Controllers;

use App\Entities\User;
use App\Repositories\UserRepository;
use Ody\DB\Doctrine\Facades\ORM;
use Ody\DB\Doctrine\Traits\CoroutineEntityManagerTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    use CoroutineEntityManagerTrait;

    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    /**
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * List all users
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->userRepository->findAll();

        return response()->json([
            'status' => 'success',
            'data' => array_map(fn(User $user) => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $users)
        ]);
    }

    /**
     * Show a specific user
     *
     * @param ServerRequestInterface $request
     * @param int $id
     * @return ResponseInterface
     */
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'preferences' => $user->getPreferences(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $user->getUpdatedAt() ? $user->getUpdatedAt()->format('Y-m-d H:i:s') : null
            ]
        ]);
    }

    /**
     * Create a new user
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        // Basic validation
        if (empty($data['name']) || empty($data['email'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Name and email are required'
            ], 422);
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email already in use'
            ], 422);
        }

        // Create user in a transaction
        return ORM::transaction(function ($em) use ($data) {
            $user = new User();
            $user->setName($data['name']);
            $user->setEmail($data['email']);

            if (!empty($data['password'])) {
                // In a real application, you would hash the password here
                $user->setPassword($data['password']);
            }

            if (!empty($data['preferences']) && is_array($data['preferences'])) {
                $user->setPreferences($data['preferences']);
            }

            $this->userRepository->save($user);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                ]
            ], 201);
        });
    }

    /**
     * Update an existing user
     *
     * @param ServerRequestInterface $request
     * @param int $id
     * @return ResponseInterface
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user = $this->userRepository->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Update user in a transaction
        return ORM::transaction(function ($em) use ($user, $data) {
            if (!empty($data['name'])) {
                $user->setName($data['name']);
            }

            if (!empty($data['email'])) {
                // Check if the new email is already in use by another user
                $existingUser = $this->userRepository->findByEmail($data['email']);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Email already in use'
                    ], 422);
                }

                $user->setEmail($data['email']);
            }

            if (isset($data['password'])) {
                // In a real application, you would hash the password here
                $user->setPassword($data['password']);
            }

            if (isset($data['preferences'])) {
                $user->setPreferences($data['preferences']);
            }

            $this->userRepository->save($user);

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);
        });
    }

    /**
     * Delete a user
     *
     * @param ServerRequestInterface $request
     * @param int $id
     * @return ResponseInterface
     */
    public function destroy(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        // Delete user in a transaction
        return ORM::transaction(function ($em) use ($user) {
            $this->userRepository->remove($user);

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        });
    }

    /**
     * Search for users by name
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function search(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams()['q'] ?? '';
        $limit = (int)($request->getQueryParams()['limit'] ?? 10);
        $offset = (int)($request->getQueryParams()['offset'] ?? 0);

        if (empty($query)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Search query is required'
            ], 422);
        }

        $users = $this->userRepository->searchByName($query, $limit, $offset);

        return response()->json([
            'status' => 'success',
            'data' => array_map(fn(User $user) => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $users),
            'meta' => [
                'query' => $query,
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($users)
            ]
        ]);
    }

    /**
     * Update user preferences
     *
     * @param ServerRequestInterface $request
     * @param int $id
     * @return ResponseInterface
     */
    public function updatePreferences(ServerRequestInterface $request, int $id): ResponseInterface
    {
        $data = $request->getParsedBody();
        $user = $this->userRepository->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        if (!isset($data['preferences']) || !is_array($data['preferences'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Preferences must be an array'
            ], 422);
        }

        // Update preferences in a transaction
        return ORM::transaction(function ($em) use ($user, $data) {
            $updatedUser = $this->userRepository->updatePreferences($user, $data['preferences']);

            return response()->json([
                'status' => 'success',
                'message' => 'Preferences updated successfully',
                'data' => [
                    'id' => $updatedUser->getId(),
                    'preferences' => $updatedUser->getPreferences(),
                    'updated_at' => $updatedUser->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]
            ]);
        });
    }

    /**
     * Example method demonstrating parallel operations with entity managers
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function processInParallel(ServerRequestInterface $request): ResponseInterface
    {
        $userIds = $request->getQueryParams()['ids'] ?? [];

        if (empty($userIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No user IDs provided'
            ], 422);
        }

        // Process multiple users in parallel using separate coroutines
        $results = $this->parallelEntityManagerOperations($userIds, function ($userId, $em) {
            $user = $em->getRepository(User::class)->find($userId);

            if (!$user) {
                return ['status' => 'error', 'message' => "User $userId not found"];
            }

            // Do some processing with the user
            return [
                'status' => 'success',
                'user_id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail()
            ];
        });

        return response()->json([
            'status' => 'success',
            'results' => $results
        ]);
    }
}