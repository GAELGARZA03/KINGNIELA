<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['social' => 0, 'tareas' => 0]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // 1. Social: (Mensajes privados no leídos) + (Solicitudes de amistad pendientes)
    // Nota: No incluimos mensajes de grupo aquí para simplificar, pero podrías agregarlo.
    $sqlSocial = "
        SELECT 
        (SELECT COUNT(*) FROM MENSAJE WHERE Id_Destinatario = ? AND Leido = 0) +
        (SELECT COUNT(*) FROM AMISTAD WHERE Id_Usuario_2 = ? AND Estado = 'pendiente') 
        as total";
    $stmtS = $pdo->prepare($sqlSocial);
    $stmtS->execute([$userId, $userId]);
    $socialCount = $stmtS->fetchColumn();

    // 2. Tareas: Tareas asignadas NO realizadas
    $sqlTareas = "SELECT COUNT(*) FROM USUARIO_TAREA WHERE Id_Usuario = ? AND Realizado = 0";
    $stmtT = $pdo->prepare($sqlTareas);
    $stmtT->execute([$userId]);
    $tareasCount = $stmtT->fetchColumn();

    echo json_encode([
        'social' => (int)$socialCount,
        'tareas' => (int)$tareasCount
    ]);

} catch (Exception $e) {
    echo json_encode(['social' => 0, 'tareas' => 0, 'error' => $e->getMessage()]);
}
?>