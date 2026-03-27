<?php
require_once 'config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    echo json_encode($appointment);
}
?>