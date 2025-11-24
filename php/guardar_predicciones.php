<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);
$idQuiniela = $input['id_quiniela'] ?? 0;
$predicciones = $input['predicciones'] ?? [];

if (!$userId || !$idQuiniela || empty($predicciones)) { echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit; }

try {
    $stmtK = $pdo->prepare("SELECT Id_Quiniela_K FROM QUINIELA_K WHERE Id_Quiniela = ?");
    $stmtK->execute([$idQuiniela]);
    $idK = $stmtK->fetchColumn();

    if (!$idK) { echo json_encode(['success' => false, 'message' => 'Error de quiniela']); exit; }

    $pdo->beginTransaction();
    $guardados = 0;

    foreach ($predicciones as $p) {
        $idPartido = $p['id_partido'];
        $gL = $p['local'];
        $gV = $p['visitante'];
        $goleadores = $p['goleadores'] ?? []; // Array de IDs de jugadores

        // Verificar estado
        $stmtCheck = $pdo->prepare("SELECT Estado FROM PARTIDO WHERE Id_Partido = ?");
        $stmtCheck->execute([$idPartido]);
        if ($stmtCheck->fetchColumn() !== 'programado') continue;

        // Upsert Pronóstico
        $stmtExist = $pdo->prepare("SELECT Id_Pronostico FROM PRONOSTICOS WHERE Id_Quiniela_K=? AND Id_Partido=? AND Id_Usuario=?");
        $stmtExist->execute([$idK, $idPartido, $userId]);
        $idPron = $stmtExist->fetchColumn();

        if ($idPron) {
            $stmtUpd = $pdo->prepare("UPDATE PRONOSTICOS SET Prediccion_Local=?, Prediccion_Visitante=? WHERE Id_Pronostico=?");
            $stmtUpd->execute([$gL, $gV, $idPron]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO PRONOSTICOS (Prediccion_Local, Prediccion_Visitante, Id_Quiniela_K, Id_Partido, Id_Usuario) VALUES (?, ?, ?, ?, ?)");
            $stmtIns->execute([$gL, $gV, $idK, $idPartido, $userId]);
            $idPron = $pdo->lastInsertId();
        }

        // Guardar Goleadores (Borrar anteriores e insertar nuevos)
        // Solo si mandaron lista (o si mandaron vacía porque borraron los goles, hay que limpiar)
        $pdo->prepare("DELETE FROM GOLEADORES_PRONOSTICO WHERE Id_Pronostico = ?")->execute([$idPron]);
        
        if (!empty($goleadores)) {
            $stmtG = $pdo->prepare("INSERT INTO GOLEADORES_PRONOSTICO (Id_Pronostico, Id_Jugador, Id_Usuario) VALUES (?, ?, ?)");
            // Eliminar duplicados para no insertar 2 veces al mismo jugador (la regla de "únicos")
            $goleadoresUnicos = array_unique($goleadores);
            foreach ($goleadoresUnicos as $idJug) {
                $stmtG->execute([$idPron, $idJug, $userId]);
            }
        }

        $guardados++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Se guardaron $guardados predicciones."]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>