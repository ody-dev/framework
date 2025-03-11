<?php

namespace App\Controllers;

use Ody\Core\Foundation\Http\Request;
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
     */
    public function index(Request $request, Response $response, array $params)
    {
        $this->logger->info('Fetching all users');

        // In a real app, fetch from database
         $stmt = $this->db->query('SELECT id, email FROM users');
         $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Mock data for example
//        $users = [
//            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
//            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
//        ];

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode($users));
    }

    /**
     * Get a specific user by ID
     */
    public function show(Request $request, Response $response, array $params)
    {
        $id = $params['id'] ?? null;

        $this->logger->info('Fetching user', ['id' => $id]);

        // In a real app, fetch from database
        // $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ?');
        // $stmt->execute([$id]);
        // $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Mock data for example
        $user = ['id' => (int)$id, 'name' => 'John Doe', 'email' => 'john@example.com'];

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode($user));
    }

    /**
     * Create a new user
     */
    public function store(Request $request, Response $response, array $params)
    {
        $data = $request->parsedBody ?? [];

        $this->logger->info('Creating user', $data);

        // Validate input
        if (empty($data['name']) || empty($data['email'])) {
            $response->status(422);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode([
                'error' => 'Name and email are required'
            ]));
            return;
        }

        // In a real app, save to database
        // $stmt = $this->db->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
        // $stmt->execute([$data['name'], $data['email']]);
        // $id = $this->db->lastInsertId();

        // Mock data for example
        $id = 3;

        $response->status(201);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email']
        ]));
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, Response $response, array $params)
    {
        $id = $params['id'] ?? null;
        $data = $request->parsedBody ?? [];

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
        //     $response->status(422);
        //     $response->header('Content-Type', 'application/json');
        //     $response->end(json_encode([
        //         'error' => 'No fields to update'
        //     ]));
        //     return;
        // }

        // $updateParams[] = $id;
        // $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
        // $stmt->execute($updateParams);

        // Mock response for example
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'id' => (int)$id,
            'name' => $data['name'] ?? 'John Doe',
            'email' => $data['email'] ?? 'john@example.com',
            'updated' => true
        ]));
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, Response $response, array $params)
    {
        $id = $params['id'] ?? null;

        $this->logger->info('Deleting user', ['id' => $id]);

        // In a real app, delete from database
        // $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        // $stmt->execute([$id]);
        // $affected = $stmt->rowCount();

        // Mock data for example
        $affected = 1;

        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'deleted' => $affected > 0,
            'id' => (int)$id
        ]));
    }
}