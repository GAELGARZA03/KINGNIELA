<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

$idQuiniela = $input['id_quiniela'] ?? 0;
$jornada = $input['jornada'] ?? '';
$jugadoresIds = $input['jugadores'] ?? []; // Array de IDs

if (!$userId || !$idQuiniela || empty($jornada)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit;
}

// Validación estricta de 11 jugadores
if (count($jugadoresIds) !== 11) {
    echo json_encode(['success' => false, 'message' => 'Debes seleccionar exactamente 11 jugadores.']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener ID Quiniela F
    $stmtF = $pdo->prepare("SELECT Id_Quiniela_F FROM QUINIELA_F WHERE Id_Quiniela = ?");
    $stmtF->execute([$idQuiniela]);
    $idF = $stmtF->fetchColumn();

    // 2. Validar Mercado Exclusivo
    $fasesExclusivas = ['Jornada 1', 'Jornada 2', 'Jornada 3'];
    if (in_array($jornada, $fasesExclusivas)) {
        // Verificar si alguno ya está ocupado por OTRO usuario (Race Condition)
        $placeholders = implode(',', array_fill(0, count($jugadoresIds), '?'));
        $sqlCheck = "SELECT COUNT(*) FROM SELECCION_JUGADORES 
                     WHERE Id_Quiniela_F = ? AND Fase = ? AND Id_Usuario != ? 
                     AND Id_Jugador IN ($placeholders)";
        
        $params = array_merge([$idF, $jornada, $userId], $jugadoresIds);
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($params);
        
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Uno o más jugadores seleccionados ya fueron fichados por otro usuario. Recarga la página.']);
            $pdo->rollBack(); exit;
        }
    }

    // 3. Validar Presupuesto
    $placeholders = implode(',', array_fill(0, count($jugadoresIds), '?'));
    $stmtCost = $pdo->prepare("SELECT SUM(Costo) FROM JUGADOR WHERE Id_Jugador IN ($placeholders)");
    $stmtCost->execute($jugadoresIds);
    $totalCosto = $stmtCost->fetchColumn();

    if ($totalCosto > 100) {
        echo json_encode(['success' => false, 'message' => 'Te has excedido del presupuesto ($100M).']);
        $pdo->rollBack(); exit;
    }

    // 4. Borrar selección anterior de esta jornada
    $stmtDel = $pdo->prepare("DELETE FROM SELECCION_JUGADORES WHERE Id_Quiniela_F = ? AND Fase = ? AND Id_Usuario = ?");
    $stmtDel->execute([$idF, $jornada, $userId]);

    // 5. Insertar nueva selección
    $stmtIns = $pdo->prepare("INSERT INTO SELECCION_JUGADORES (Fase, Id_Quiniela_F, Id_Jugador, Id_Usuario) VALUES (?, ?, ?, ?)");
    foreach ($jugadoresIds as $idJ) {
        $stmtIns->execute([$jornada, $idF, $idJ, $userId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '¡Equipo guardado exitosamente!']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>