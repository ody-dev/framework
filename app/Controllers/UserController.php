<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use Ody\DB\Doctrine\Facades\DBAL;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(protected UserRepository $usersService)
    {}

    /**
     * Get all users
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
//    #[Middleware(AuthMiddleware::class)]
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // In a real app, fetch from database
//        $users = $this->usersService->getAll();

        $users = [
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com'],
            ['id' => 4, 'name' => 'Alice Brown', 'email' => 'alice@example.com'],
            ['id' => 5, 'name' => 'Charlie Davis', 'email' => 'charlie@example.com'],
            ['id' => 6, 'name' => 'Eve Wilson', 'email' => 'eve@example.com'],
        ];

        return $response->json($users);
    }

    /**
     * Get a specific user by ID
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = $params['id'] ?? null;

        // In a real app, fetch from database

        // Mock data for example
        $user = ['id' => (int)$id, 'name' => 'John Doe', 'email' => 'john@example.com'];

        return $response->json($user);

    }

    /**
     * Create a new user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        // Validate input
        if (empty($data['name']) || empty($data['email'])) {
            return $response->json([
                'error' => 'Name and email are required'
            ], 422);
        }

        // In a real app, save to databaseS

        // Mock data for example
        $id = 3;

        return $response->json([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email']
        ], 201);
    }

    /**
     * Update an existing user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = $params['id'] ?? null;
        $data = $request->getParsedBody() ?? [];

        // In a real app, update in databaseDSS

        // Mock response for example
        return $response->json([
            'id' => (int)$id,
            'name' => $data['name'] ?? 'John Doe',
            'email' => $data['email'] ?? 'john@example.com',
            'updated' => true
        ]);
    }

    /**
     * Delete a user
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        $id = $params['id'] ?? null;

        logger()->info('Deleting user', ['id' => $id]);

        // In a real app, delete from database

        // Mock data for example
        $affected = 1;

        return $response->json([
            'deleted' => $affected > 0,
            'id' => (int)$id
        ]);
    }
}