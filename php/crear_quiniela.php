<?php
header('Content-Type: application/json');
require 'conexion.php';
require 'crown_helper.php';
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

    // 1. Insertar Quiniela (Tabla 'quiniela')
    $stmt = $pdo->prepare("INSERT INTO quiniela (Nombre_Quiniela, Tipo_Quiniela, Codigo_Acceso, Id_Creador) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $tipo, $codigo, $userId]);
    $idQuiniela = $pdo->lastInsertId();

    // 2. Insertar Creador como Integrante (Tabla 'quiniela_integrantes')
    $stmtInt = $pdo->prepare("INSERT INTO quiniela_integrantes (Id_Quiniela, Id_Usuario) VALUES (?, ?)");
    $stmtInt->execute([$idQuiniela, $userId]);

    // 3. Insertar Amigos
    foreach ($amigos as $idAmigo) {
        $stmtInt->execute([$idQuiniela, $idAmigo]);
    }

    // 4. Configuración Específica (Tablas 'quiniela_k' o 'quiniela_f')
    if ($tipo === 'kingniela') {
        // CORRECCIÓN: Tabla 'quiniela_k' en minúsculas
        $stmtK = $pdo->prepare("INSERT INTO quiniela_k (Id_Quiniela, Dificultad) VALUES (?, ?)");
        $stmtK->execute([$idQuiniela, $dificultad]);
    } else {
        // CORRECCIÓN: Tabla 'quiniela_f' en minúsculas
        $stmtF = $pdo->prepare("INSERT INTO quiniela_f (Id_Quiniela, Presupuesto_Inicial) VALUES (?, 100.00)");
        $stmtF->execute([$idQuiniela]);
    }

    // --- LOGRO: EL COMIENZO ---
    desbloquearCorona($pdo, $userId, 'El comienzo');
    // ---------------------------

    $pdo->commit();
    echo json_encode(['success' => true, 'codigo' => $codigo]);

} catch (Exception $e) {
    $pdo->rollBack();
    // Guardar error en log para depurar si sigue fallando
    file_put_contents('error_log_crear.txt', $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}
?>