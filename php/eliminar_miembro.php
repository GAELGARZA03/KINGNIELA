<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);

$idQuiniela = isset($input['id_quiniela']) ? intval($input['id_quiniela']) : 0;
$idUsuarioEliminar = isset($input['id_usuario']) ? intval($input['id_usuario']) : 0;

if (!$idQuiniela || !$idUsuarioEliminar) {
    echo json_encode(['success' => false, 'message' => 'Datos incorrectos']); exit;
}

try {
    // TABLA EN MAYÚSCULAS
    $stmt = $pdo->prepare("DELETE FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ? AND Id_Usuario = ?");
    $stmt->execute([$idQuiniela, $idUsuarioEliminar]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró al usuario o ya fue eliminado']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>