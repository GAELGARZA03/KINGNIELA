<?php
header('Content-Type: application/json');
require 'conexion.php';
require 'crown_helper.php'; // <--- AGREGAR ESTO
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$nombre = $input['nombre'] ?? '';
$tipo = $input['tipo'] ?? 'clasico';
$dificultad = $input['dificultad'] ?? 'Aficionado';
$amigos = $input['amigos'] ?? [];
$userId = $_SESSION['user_id'] ?? 0;

if (!$userId || empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit;
}

try {
    $pdo->beginTransaction();

    // Generar Código Único
    $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    // Insertar Quiniela
    $stmt = $pdo->prepare("INSERT INTO QUINIELA (Nombre_Quiniela, Tipo_Quiniela, Codigo_Acceso, Id_Creador) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $tipo, $codigo, $userId]);
    $idQuiniela = $pdo->lastInsertId();

    // Insertar Creador como Integrante
    $stmtInt = $pdo->prepare("INSERT INTO QUINIELA_INTEGRANTES (Id_Quiniela, Id_Usuario) VALUES (?, ?)");
    $stmtInt->execute([$idQuiniela, $userId]);

    // Insertar Amigos
    foreach ($amigos as $idAmigo) {
        $stmtInt->execute([$idQuiniela, $idAmigo]);
    }

    // Configuración Específica
    if ($tipo === 'kingniela') {
        $stmtK = $pdo->prepare("INSERT INTO QUINIELA_K (Id_Quiniela, Dificultad) VALUES (?, ?)");
        $stmtK->execute([$idQuiniela, $dificultad]);
    } else {
        $stmtF = $pdo->prepare("INSERT INTO QUINIELA_F (Id_Quiniela, Presupuesto_Inicial) VALUES (?, 100.00)");
        $stmtF->execute([$idQuiniela]);
    }

    // --- LOGRO: EL COMIENZO ---
    desbloquearCorona($pdo, $userId, 'El comienzo');
    // ---------------------------

    $pdo->commit();
    echo json_encode(['success' => true, 'codigo' => $codigo]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>