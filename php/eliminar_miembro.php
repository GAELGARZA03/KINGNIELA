<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$idQuiniela = isset($input['id_quiniela']) ? intval($input['id_quiniela']) : 0;
$idUsuarioAEliminar = isset($input['id_usuario']) ? intval($input['id_usuario']) : 0;
$miId = $_SESSION['user_id'] ?? 0;

if (!$idQuiniela || !$idUsuarioAEliminar || !$miId) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit;
}

try {
    // 1. Verificar quién es el dueño de la quiniela
    $stmtCheck = $pdo->prepare("SELECT Id_Creador FROM QUINIELA WHERE Id_Quiniela = ?");
    $stmtCheck->execute([$idQuiniela]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'La quiniela no existe']); exit;
    }

    $idCreador = intval($row['Id_Creador']);

    // 2. REGLA DE ORO: Solo el creador puede eliminar a otros
    if ($miId !== $idCreador) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos de administrador para eliminar miembros.']); 
        exit;
    }

    // 3. Seguridad: El creador no puede auto-eliminarse por aquí (debe usar "Salir del grupo")
    if ($idUsuarioAEliminar === $idCreador) {
        echo json_encode(['success' => false, 'message' => 'El administrador no puede ser eliminado.']); 
        exit;
    }

    // 4. Ejecutar eliminación
    $stmt = $pdo->prepare("DELETE FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ? AND Id_Usuario = ?");
    $stmt->execute([$idQuiniela, $idUsuarioAEliminar]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Miembro eliminado']);
    } else {
        echo json_encode(['success' => false, 'message' => 'El usuario no estaba en el grupo']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>