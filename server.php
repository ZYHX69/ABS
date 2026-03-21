<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'php/config/db.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SalonWebSocket implements MessageComponentInterface {
    protected $clients;
    private $pdo;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        global $pdo;
        $this->pdo = $pdo;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->resourceId = spl_object_hash($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        if ($data['action'] === 'authenticate') {
            $token = $data['token'] ?? '';
            $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            if ($user) {
                $from->user = $user;
                $from->send(json_encode(['event' => 'authenticated', 'success' => true]));
            } else {
                $from->send(json_encode(['event' => 'authenticated', 'success' => false]));
                $from->close();
            }
            return;
        }

        if (!isset($from->user)) {
            $from->send(json_encode(['error' => 'Not authenticated']));
            $from->close();
            return;
        }

        $user = $from->user;
        $action = $data['action'] ?? '';
        $module = $data['module'] ?? '';

        switch ($action) {
            case 'get':
                $this->handleGet($from, $module);
                break;
            case 'create':
                $this->handleCreate($from, $module, $data['data'] ?? []);
                break;
            case 'update':
                $this->handleUpdate($from, $module, $data['id'], $data['data'] ?? []);
                break;
            case 'delete':
                $this->handleDelete($from, $module, $data['id']);
                break;
            default:
                $from->send(json_encode(['error' => 'Unknown action']));
        }
    }

    private function handleGet($from, $module) {
        if ($module === 'client') {
            $stmt = $this->pdo->query("SELECT * FROM clients ORDER BY id DESC");
            $records = $stmt->fetchAll();
            $from->send(json_encode(['event' => 'data', 'module' => 'client', 'data' => $records]));
        } elseif ($module === 'appointment') {
            $stmt = $this->pdo->query("SELECT a.*, c.name as client_name FROM appointments a JOIN clients c ON a.client_id = c.id ORDER BY a.id DESC");
            $records = $stmt->fetchAll();
            $from->send(json_encode(['event' => 'data', 'module' => 'appointment', 'data' => $records]));
        }
    }

    private function handleCreate($from, $module, $data) {
        global $pdo;
        $user = $from->user;
        try {
            if ($module === 'client') {
                $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone) VALUES (?, ?, ?)");
                $stmt->execute([$data['name'], $data['email'], $data['phone']]);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'created_at' => date('Y-m-d H:i:s')];
                $this->logAudit($user['id'], 'CREATE', $module, $id, "Created {$module} {$data['name']}");
                $this->broadcast(['event' => 'cud', 'action' => 'create', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'appointment') {
                $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$data['client_id'], $data['service'], $data['date'], $data['time'], $data['status'] ?? 'scheduled']);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'client_id' => $data['client_id'], 'service' => $data['service'], 'appointment_date' => $data['date'], 'appointment_time' => $data['time'], 'status' => $data['status'] ?? 'scheduled'];
                $this->logAudit($user['id'], 'CREATE', $module, $id, "Created appointment for client {$data['client_id']}");
                $this->broadcast(['event' => 'cud', 'action' => 'create', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => $module, 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    private function handleUpdate($from, $module, $id, $data) {
        $user = $from->user;
        try {
            if ($module === 'client') {
                $stmt = $this->pdo->prepare("UPDATE clients SET name=?, email=?, phone=? WHERE id=?");
                $stmt->execute([$data['name'], $data['email'], $data['phone'], $id]);
                $record = ['id' => $id, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']];
                $this->logAudit($user['id'], 'UPDATE', $module, $id, "Updated client {$data['name']}");
                $this->broadcast(['event' => 'cud', 'action' => 'update', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'update', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'appointment') {
                $stmt = $this->pdo->prepare("UPDATE appointments SET client_id=?, service=?, appointment_date=?, appointment_time=?, status=? WHERE id=?");
                $stmt->execute([$data['client_id'], $data['service'], $data['date'], $data['time'], $data['status'], $id]);
                $record = ['id' => $id, 'client_id' => $data['client_id'], 'service' => $data['service'], 'appointment_date' => $data['date'], 'appointment_time' => $data['time'], 'status' => $data['status']];
                $this->logAudit($user['id'], 'UPDATE', $module, $id, "Updated appointment $id");
                $this->broadcast(['event' => 'cud', 'action' => 'update', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'update', 'module' => $module, 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    private function handleDelete($from, $module, $id) {
        $user = $from->user;
        if ($user['role'] !== 'admin') {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => 'Permission denied']));
            return;
        }
        try {
            if ($module === 'client') {
                $stmt = $this->pdo->prepare("DELETE FROM clients WHERE id=?");
                $stmt->execute([$id]);
                $this->logAudit($user['id'], 'DELETE', $module, $id, "Deleted client $id");
                $this->broadcast(['event' => 'cud', 'action' => 'delete', 'module' => $module, 'id' => $id]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'delete', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'appointment') {
                $stmt = $this->pdo->prepare("DELETE FROM appointments WHERE id=?");
                $stmt->execute([$id]);
                $this->logAudit($user['id'], 'DELETE', $module, $id, "Deleted appointment $id");
                $this->broadcast(['event' => 'cud', 'action' => 'delete', 'module' => $module, 'id' => $id]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'delete', 'module' => $module, 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    private function logAudit($userId, $action, $module, $recordId, $details) {
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $module, $recordId, $details]);
    }

    private function broadcast($message) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($message));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require_once __DIR__ . '/vendor/autoload.php';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SalonWebSocket()
        )
    ),
    8080
);

$server->run();