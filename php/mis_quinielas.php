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
    $sql = "SELECT q.Id_Quiniela, q.Nombre_Quiniela, q.Tipo_Quiniela, q.Codigo_Acceso, q.Foto_Grupo, q.Descripcion 
            FROM QUINIELA q
            JOIN QUINIELA_INTEGRANTES qi ON q.Id_Quiniela = qi.Id_Quiniela
            WHERE qi.Id_Usuario = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $quinielas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($quinielas);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>