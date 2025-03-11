<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ody\Core\Foundation\Http\Response;
use Ody\Core\Foundation\Logger;

class UserController
{
    /**
     * @var \PDO Database connection
     */
    private $db;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * UserController constructor
     *
     * Dependencies are automatically injected by the container
     */
    public function __construct(\PDO $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Get all users
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $params): ResponseInterface
    {
        logger()->info('This is an informational message');
        dd('test');
        $this->logger->info('Fetching all users');

        // In a real app, fetch from database
        $stmt = $this->db->query('SELECT id, email FROM users');
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Mock data for example
        // $users = [
        //     ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        //     ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
        // ];

        return $this->jsonResponse($response, $users);
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

        $this->logger->info('Fetching user', ['id' => $id]);

        // In a real app, fetch from database
        // $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ?');
        // $stmt->execute([$id]);
        // $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Mock data for example
        $user = ['id' => (int)$id, 'name' => 'John Doe', 'email' => 'john@example.com'];

        return $this->jsonResponse($response, $user);
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

        $this->logger->info('Creating user', $data);

        // Validate input
        if (empty($data['name']) || empty($data['email'])) {
            return $this->jsonResponse($response->withStatus(422), [
                'error' => 'Name and email are required'
            ]);
        }

        // In a real app, save to database
        // $stmt = $this->db->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
        // $stmt->execute([$data['name'], $data['email']]);
        // $id = $this->db->lastInsertId();

        // Mock data for example
        $id = 3;

        return $this->jsonResponse($response->withStatus(201), [
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email']
        ]);
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

        $this->logger->info('Updating user', ['id' => $id, 'data' => $data]);

        // In a real app, update in database
        // $updateFields = [];
        // $updateParams = [];

        // if (!empty($data['name'])) {
        //     $updateFields[] = 'name = ?';
        //     $updateParams[] = $data['name'];
        // }

        // if (!empty($data['email'])) {
        //     $updateFields[] = 'email = ?';
        //     $updateParams[] = $data['email'];
        // }

        // if (empty($updateFields)) {
        //     return $this->jsonResponse($response->withStatus(422), [
        //         'error' => 'No fields to update'
        //     ]);
        // }

        // $updateParams[] = $id;
        // $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
        // $stmt->execute($updateParams);

        // Mock response for example
        return $this->jsonResponse($response, [
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

        $this->logger->info('Deleting user', ['id' => $id]);

        // In a real app, delete from database
        // $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        // $stmt->execute([$id]);
        // $affected = $stmt->rowCount();

        // Mock data for example
        $affected = 1;

        return $this->jsonResponse($response, [
            'deleted' => $affected > 0,
            'id' => (int)$id
        ]);
    }

    /**
     * Helper method to create JSON responses
     *
     * @param ResponseInterface $response
     * @param mixed $data
     * @return ResponseInterface
     */
    private function jsonResponse(ResponseInterface $response, $data): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json');

        // If using our custom Response class
        if ($response instanceof Response) {
            return $response->withJson($data);
        }

        // For other PSR-7 implementations
        $response->getBody()->write(json_encode($data));
        return $response;
    }
}