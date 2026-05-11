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

        // Guest connection for public booking
        if ($data['action'] === 'guest_connect') {
            $from->guest = true;
            $from->send(json_encode(['event' => 'guest_authenticated', 'success' => true]));
            return;
        }

        // Allow guests to fetch services list
        if (isset($from->guest) && $from->guest === true && $data['action'] === 'get' && $data['module'] === 'service') {
            $this->handleGet($from, $data['module'], $data['page'] ?? 1, $data['limit'] ?? 10);
            return;
        }

        // Regular authentication
        if ($data['action'] === 'authenticate') {
            $token = $data['token'] ?? '';
            $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            if ($user) {
                $from->user = $user;
                $from->send(json_encode(['event' => 'authenticated', 'success' => true, 'role' => $user['role']]));
            } else {
                $from->send(json_encode(['event' => 'authenticated', 'success' => false]));
                $from->close();
            }
            return;
        }

        // For public_booking create, allow guests
        $isGuest = isset($from->guest) && $from->guest === true;
        $isAuthenticated = isset($from->user);
        
        if (!$isGuest && !$isAuthenticated) {
            $from->send(json_encode(['error' => 'Not authenticated']));
            $from->close();
            return;
        }

        $user = $isAuthenticated ? $from->user : null;
        $action = $data['action'] ?? '';
        $module = $data['module'] ?? '';

        // Guests can only create public_booking
        if ($isGuest && !($action === 'create' && $module === 'public_booking')) {
            $from->send(json_encode(['error' => 'Guests can only create public bookings']));
            return;
        }

        switch ($action) {
            case 'get':
                $this->handleGet($from, $module, $data['page'] ?? 1, $data['limit'] ?? 10);
                break;
            case 'create':
                $this->handleCreate($from, $module, $data['data'] ?? [], $user);
                break;
            case 'update':
                $this->handleUpdate($from, $module, $data['id'], $data['data'] ?? [], $user);
                break;
            case 'delete':
                $this->handleDelete($from, $module, $data['id'], $user);
                break;
            case 'search':
                $this->handleSearch($from, $data['query'] ?? '');
                break;
            case 'get_audit_logs':
                $this->handleGetAuditLogs($from, $user, $data['filters'] ?? []);
                break;
            default:
                $from->send(json_encode(['error' => 'Unknown action']));
        }
    }

    // ==================== GET WITH PAGINATION ====================
    private function handleGet($from, $module, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $total = 0;

        if ($module === 'client') {
            $stmt = $this->pdo->prepare("SELECT * FROM clients ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $total = $this->pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        } elseif ($module === 'appointment') {
            $stmt = $this->pdo->prepare("SELECT a.*, c.name as client_name FROM appointments a JOIN clients c ON a.client_id = c.id ORDER BY a.id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $total = $this->pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
        } elseif ($module === 'service') {
            $stmt = $this->pdo->prepare("SELECT * FROM services ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $total = $this->pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
        } elseif ($module === 'public_booking') {
            if (!isset($from->user)) {
                $from->send(json_encode(['error' => 'Permission denied']));
                return;
            }
            $stmt = $this->pdo->prepare("SELECT pb.*, s.name as service_name FROM public_bookings pb LEFT JOIN services s ON pb.service_id = s.id ORDER BY pb.id DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $records = $stmt->fetchAll();
            $total = $this->pdo->query("SELECT COUNT(*) FROM public_bookings")->fetchColumn();
        } else {
            $from->send(json_encode(['error' => 'Invalid module']));
            return;
        }

        $from->send(json_encode([
            'event' => 'data',
            'module' => $module,
            'data' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]));
    }

    // ==================== GLOBAL SEARCH ====================
    private function handleSearch($from, $query) {
        $query = trim($query);
        if ($query === '') {
            $from->send(json_encode(['event' => 'search_results', 'data' => []]));
            return;
        }
        $like = "%$query%";
        $results = [];

        // Search clients
        $stmt = $this->pdo->prepare("SELECT id, name, email, phone, 'client' as type FROM clients WHERE name LIKE ? OR email LIKE ?");
        $stmt->execute([$like, $like]);
        $results = array_merge($results, $stmt->fetchAll());

        // Search appointments
        $stmt = $this->pdo->prepare("SELECT a.id, c.name as client_name, a.service, a.appointment_date, 'appointment' as type FROM appointments a JOIN clients c ON a.client_id = c.id WHERE c.name LIKE ? OR a.service LIKE ?");
        $stmt->execute([$like, $like]);
        $results = array_merge($results, $stmt->fetchAll());

        // Search services
        $stmt = $this->pdo->prepare("SELECT id, name, duration, price, 'service' as type FROM services WHERE name LIKE ?");
        $stmt->execute([$like]);
        $results = array_merge($results, $stmt->fetchAll());

        // Search public bookings (only if authenticated)
        if (isset($from->user)) {
            $stmt = $this->pdo->prepare("SELECT pb.id, pb.customer_name, pb.customer_email, pb.preferred_date, 'public_booking' as type FROM public_bookings pb WHERE pb.customer_name LIKE ? OR pb.customer_email LIKE ?");
            $stmt->execute([$like, $like]);
            $results = array_merge($results, $stmt->fetchAll());
        }

        $from->send(json_encode(['event' => 'search_results', 'data' => $results]));
    }

    // ==================== AUDIT LOGS (ADMIN ONLY) ====================
    private function handleGetAuditLogs($from, $user, $filters) {
        if (!$user || $user['role'] !== 'admin') {
            $from->send(json_encode(['error' => 'Permission denied']));
            return;
        }
        $sql = "SELECT al.*, u.username FROM audit_logs al JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC";
        $params = [];
        if (!empty($filters['action'])) {
            $sql .= " WHERE al.action = ?";
            $params[] = $filters['action'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        $from->send(json_encode(['event' => 'audit_logs', 'data' => $logs]));
    }

    // ==================== CREATE ====================
    private function handleCreate($from, $module, $data, $user = null) {
        global $pdo;
        try {
            if ($module === 'client') {
                if (!$user) throw new Exception('Authentication required');
                $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone) VALUES (?, ?, ?)");
                $stmt->execute([$data['name'], $data['email'], $data['phone']]);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'created_at' => date('Y-m-d H:i:s')];
                $this->logAudit($user['id'], 'CREATE', $module, $id, "Created {$module} {$data['name']}");
                $this->broadcast(['event' => 'cud', 'action' => 'create', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'appointment') {
                if (!$user) throw new Exception('Authentication required');
                $stmt = $pdo->prepare("INSERT INTO appointments (client_id, service, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$data['client_id'], $data['service'], $data['date'], $data['time'], $data['status'] ?? 'scheduled']);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'client_id' => $data['client_id'], 'service' => $data['service'], 'appointment_date' => $data['date'], 'appointment_time' => $data['time'], 'status' => $data['status'] ?? 'scheduled'];
                $this->logAudit($user['id'], 'CREATE', $module, $id, "Created appointment for client {$data['client_id']}");
                $this->broadcast(['event' => 'cud', 'action' => 'create', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'service') {
                if (!$user) throw new Exception('Authentication required');
                $stmt = $pdo->prepare("INSERT INTO services (name, duration, price) VALUES (?, ?, ?)");
                $stmt->execute([$data['name'], $data['duration'], $data['price']]);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'name' => $data['name'], 'duration' => $data['duration'], 'price' => $data['price']];
                $this->logAudit($user['id'], 'CREATE', $module, $id, "Created service {$data['name']}");
                $this->broadcast(['event' => 'cud', 'action' => 'create', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'public_booking') {
                $stmt = $pdo->prepare("INSERT INTO public_bookings (customer_name, customer_email, customer_phone, service_id, preferred_date, preferred_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$data['name'], $data['email'], $data['phone'] ?? null, $data['service_id'], $data['date'], $data['time']]);
                $id = $pdo->lastInsertId();
                $record = ['id' => $id, 'customer_name' => $data['name'], 'customer_email' => $data['email'], 'customer_phone' => $data['phone'] ?? null, 'service_id' => $data['service_id'], 'preferred_date' => $data['date'], 'preferred_time' => $data['time'], 'status' => 'pending'];
                $this->broadcastToAuthenticated(['event' => 'cud', 'action' => 'create', 'module' => 'public_booking', 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'create', 'module' => 'public_booking', 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ==================== UPDATE ====================
    private function handleUpdate($from, $module, $id, $data, $user) {
        if (!$user) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => 'Authentication required']));
            return;
        }
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
            } elseif ($module === 'service') {
                $stmt = $this->pdo->prepare("UPDATE services SET name=?, duration=?, price=? WHERE id=?");
                $stmt->execute([$data['name'], $data['duration'], $data['price'], $id]);
                $record = ['id' => $id, 'name' => $data['name'], 'duration' => $data['duration'], 'price' => $data['price']];
                $this->logAudit($user['id'], 'UPDATE', $module, $id, "Updated service {$data['name']}");
                $this->broadcast(['event' => 'cud', 'action' => 'update', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'update', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'public_booking') {
                if ($user['role'] !== 'admin') throw new Exception('Permission denied');
                $stmt = $this->pdo->prepare("UPDATE public_bookings SET status=? WHERE id=?");
                $stmt->execute([$data['status'], $id]);
                $record = ['id' => $id, 'status' => $data['status']];
                $this->logAudit($user['id'], 'UPDATE', $module, $id, "Updated booking $id to {$data['status']}");
                $this->broadcast(['event' => 'cud', 'action' => 'update', 'module' => $module, 'data' => $record]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'update', 'module' => $module, 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ==================== DELETE ====================
    private function handleDelete($from, $module, $id, $user) {
        if (!$user || $user['role'] !== 'admin') {
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
            } elseif ($module === 'service') {
                $stmt = $this->pdo->prepare("DELETE FROM services WHERE id=?");
                $stmt->execute([$id]);
                $this->logAudit($user['id'], 'DELETE', $module, $id, "Deleted service $id");
                $this->broadcast(['event' => 'cud', 'action' => 'delete', 'module' => $module, 'id' => $id]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'delete', 'module' => $module, 'id' => $id]));
            } elseif ($module === 'public_booking') {
                $stmt = $this->pdo->prepare("DELETE FROM public_bookings WHERE id=?");
                $stmt->execute([$id]);
                $this->logAudit($user['id'], 'DELETE', $module, $id, "Deleted booking $id");
                $this->broadcast(['event' => 'cud', 'action' => 'delete', 'module' => $module, 'id' => $id]);
                $from->send(json_encode(['event' => 'ack', 'success' => true, 'action' => 'delete', 'module' => $module, 'id' => $id]));
            }
        } catch (Exception $e) {
            $from->send(json_encode(['event' => 'ack', 'success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ==================== HELPER METHODS ====================
    private function logAudit($userId, $action, $module, $recordId, $details) {
        $stmt = $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $module, $recordId, $details]);
    }

    private function broadcast($message) {
        foreach ($this->clients as $client) {
            $client->send(json_encode($message));
        }
    }

    private function broadcastToAuthenticated($message) {
        foreach ($this->clients as $client) {
            if (isset($client->user)) {
                $client->send(json_encode($message));
            }
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

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SalonWebSocket()
        )
    ),
    8080
);

$server->run();