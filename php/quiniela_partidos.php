<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_GET['id_quiniela'] ?? 0;
$jornada = $_GET['jornada'] ?? 'Jornada 1';

if (!$userId || !$idQuiniela) {
    echo json_encode(['success' => false, 'message' => 'Datos faltantes']);
    exit;
}

try {
    // 1. Obtener Info de la Quiniela (Dificultad)
    $stmtQ = $pdo->prepare("
        SELECT q.Tipo_Quiniela, k.Dificultad, k.Id_Quiniela_K
        FROM QUINIELA q
        LEFT JOIN QUINIELA_K k ON q.Id_Quiniela = k.Id_Quiniela
        WHERE q.Id_Quiniela = ?
    ");
    $stmtQ->execute([$idQuiniela]);
    $infoQ = $stmtQ->fetch(PDO::FETCH_ASSOC);

    if (!$infoQ) {
        echo json_encode(['success' => false, 'message' => 'Quiniela no encontrada']);
        exit;
    }

    // 2. Obtener Partidos de la Jornada
    // CAMBIO: Agregamos p.Estado al SELECT
    $stmtP = $pdo->prepare("
        SELECT p.Id_Partido, p.Fecha_Partido, p.Hora_Partido, p.Estado,
               el.Nombre_Equipo as Local, el.Escudo as EscudoL,
               ev.Nombre_Equipo as Visitante, ev.Escudo as EscudoV
        FROM PARTIDO p
        JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo
        JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo
        WHERE p.Fase = ?
        ORDER BY p.Fecha_Partido, p.Hora_Partido
    ");
    $stmtP->execute([$jornada]);
    $partidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener Predicciones del Usuario
    $predicciones = [];
    if ($infoQ['Tipo_Quiniela'] === 'kingniela' && $infoQ['Id_Quiniela_K']) {
        $stmtPred = $pdo->prepare("
            SELECT Id_Partido, Prediccion_Local, Prediccion_Visitante 
            FROM PRONOSTICOS 
            WHERE Id_Quiniela_K = ? AND Id_Usuario = ?
        ");
        $stmtPred->execute([$infoQ['Id_Quiniela_K'], $userId]);
        $predsRaw = $stmtPred->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($predsRaw as $pr) {
            $predicciones[$pr['Id_Partido']] = $pr;
        }
    }

    // 4. Fusionar Datos
    $listaFinal = [];
    foreach ($partidos as $p) {
        $pred = $predicciones[$p['Id_Partido']] ?? null;
        
        $listaFinal[] = [
            'id' => $p['Id_Partido'],
            'fecha' => $p['Fecha_Partido'] . ' ' . substr($p['Hora_Partido'], 0, 5),
            'estado' => $p['Estado'], // Enviamos el estado al JS
            'local' => $p['Local'],
            'escudoL' => $p['EscudoL'],
            'visitante' => $p['Visitante'],
            'escudoV' => $p['EscudoV'],
            'pred_L' => $pred ? $pred['Prediccion_Local'] : '',
            'pred_V' => $pred ? $pred['Prediccion_Visitante'] : ''
        ];
    }

    echo json_encode([
        'success' => true,
        'config' => $infoQ,
        'partidos' => $listaFinal
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>