<?php
header('Content-Type: application/json');
require 'conexion.php';
require 'crown_helper.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode(['success' => false]); exit; }

try {
    // 1. Contar Aciertos Totales (Puntos > 0 en PRONOSTICOS)
    // Asumimos que si Puntos_Obtenidos > 0, acertó al menos el resultado
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM PRONOSTICOS WHERE Id_Usuario = ? AND Puntos_Obtenidos > 0");
    $stmtCount->execute([$userId]);
    $aciertos = $stmtCount->fetchColumn();

    // Logros por cantidad
    if ($aciertos >= 1) desbloquearCorona($pdo, $userId, 'Primero de muchos');
    if ($aciertos >= 20) desbloquearCorona($pdo, $userId, 'Profesional');
    if ($aciertos >= 50) desbloquearCorona($pdo, $userId, 'La leyenda');

    // 2. Verificar 'Novato' (Completar primera jornada)
    // Verificamos si el usuario tiene predicciones para TODOS los partidos de la Jornada 1
    // Y si esos partidos ya finalizaron (o simplemente si ya hizo las predicciones, depende de tu regla. "Completa" suele ser "Juega").
    // Usaremos la regla: Si tiene predicciones registradas para al menos 1 partido de J1 que ya finalizó.
    $stmtJ1 = $pdo->prepare("
        SELECT COUNT(*) FROM PRONOSTICOS p 
        JOIN PARTIDO part ON p.Id_Partido = part.Id_Partido 
        WHERE p.Id_Usuario = ? AND part.Fase = 'Jornada 1' AND part.Estado = 'finalizado'
    ");
    $stmtJ1->execute([$userId]);
    if ($stmtJ1->fetchColumn() > 0) {
        desbloquearCorona($pdo, $userId, 'Novato');
    }

    // 3. Verificar 'Jornada Perfecta' (Todos los partidos de una jornada acertados)
    // Esto es complejo, simplificamos: Checamos Jornada 1, 2 o 3.
    $jornadas = ['Jornada 1', 'Jornada 2', 'Jornada 3'];
    foreach ($jornadas as $fase) {
        // Total partidos en esa fase
        $stmtTotalP = $pdo->prepare("SELECT COUNT(*) FROM PARTIDO WHERE Fase = ?");
        $stmtTotalP->execute([$fase]);
        $totalPartidos = $stmtTotalP->fetchColumn();

        if ($totalPartidos > 0) {
            // Aciertos del usuario en esa fase
            $stmtAciertosF = $pdo->prepare("
                SELECT COUNT(*) FROM PRONOSTICOS p 
                JOIN PARTIDO part ON p.Id_Partido = part.Id_Partido 
                WHERE p.Id_Usuario = ? AND part.Fase = ? AND p.Puntos_Obtenidos > 0
            ");
            $stmtAciertosF->execute([$userId, $fase]);
            $misAciertos = $stmtAciertosF->fetchColumn();

            if ($misAciertos == $totalPartidos) {
                desbloquearCorona($pdo, $userId, 'Jornada Perfecta');
                break; // Ya la consiguió
            }
        }
    }

    echo json_encode(['success' => true, 'aciertos' => $aciertos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>