<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$idQuiniela = $data['id_quiniela'] ?? 0;
$nombre = $data['nombre'] ?? '';
$fecha = $data['fecha'] ?? '';

if (empty($idQuiniela) || empty($nombre) || empty($fecha)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

try {
    // 1. Verificar que soy miembro del grupo (Seguridad)
    $stmtCheck = $pdo->prepare("SELECT 1 FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ? AND Id_Usuario = ?");
    $stmtCheck->execute([$idQuiniela, $userId]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No perteneces a este grupo']);
        exit;
    }

    $pdo->beginTransaction();

    // 2. Crear la Tarea Maestra
    $sqlTarea = "INSERT INTO TAREA (Nombre_Tarea, Fecha_Vencimiento, Id_Quiniela) VALUES (?, ?, ?)";
    $stmtTarea = $pdo->prepare($sqlTarea);
    $stmtTarea->execute([$nombre, $fecha, $idQuiniela]);
    $idTarea = $pdo->lastInsertId();

    // 3. Asignar a TODOS los miembros del grupo
    // Obtenemos los IDs de los miembros
    $stmtMiembros = $pdo->prepare("SELECT Id_Usuario FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ?");
    $stmtMiembros->execute([$idQuiniela]);
    $miembros = $stmtMiembros->fetchAll(PDO::FETCH_COLUMN);

    // Insertamos en USUARIO_TAREA para cada uno
    $sqlAsignar = "INSERT INTO USUARIO_TAREA (Id_Usuario, Id_Tarea, Realizado) VALUES (?, ?, 0)";
    $stmtAsignar = $pdo->prepare($sqlAsignar);

    foreach ($miembros as $miembroId) {
        $stmtAsignar->execute([$miembroId, $idTarea]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Tarea creada y asignada a todos']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>