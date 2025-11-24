<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$idQuiniela = $input['id_quiniela'] ?? 0;
$idUsuarioEliminar = $input['id_usuario'] ?? 0;

if (!$idQuiniela || !$idUsuarioEliminar) {
    echo json_encode(['success' => false, 'message' => 'Datos incorrectos']); exit;
}

try {
    // Eliminar
    $stmt = $pdo->prepare("DELETE FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ? AND Id_Usuario = ?");
    $stmt->execute([$idQuiniela, $idUsuarioEliminar]);

    echo json_encode(['success' => true, 'message' => 'Usuario eliminado del grupo']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>