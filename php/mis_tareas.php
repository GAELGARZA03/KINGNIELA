<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $sql = "
        SELECT t.Id_Tarea, t.Nombre_Tarea, t.Fecha_Vencimiento, 
               q.Nombre_Quiniela as Grupo, 
               ut.Realizado
        FROM USUARIO_TAREA ut
        JOIN TAREA t ON ut.Id_Tarea = t.Id_Tarea
        JOIN QUINIELA q ON t.Id_Quiniela = q.Id_Quiniela
        WHERE ut.Id_Usuario = ?
        ORDER BY t.Fecha_Vencimiento ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($tareas);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>