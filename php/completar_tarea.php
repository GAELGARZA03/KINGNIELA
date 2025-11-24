<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$idTarea = $data['id_tarea'] ?? 0;

try {
    $stmt = $pdo->prepare("UPDATE USUARIO_TAREA SET Realizado = 1 WHERE Id_Usuario = ? AND Id_Tarea = ?");
    $stmt->execute([$userId, $idTarea]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>