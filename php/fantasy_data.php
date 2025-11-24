<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_GET['id_quiniela'] ?? 0;
$jornada = $_GET['jornada'] ?? 'Jornada 1';

if (!$userId || !$idQuiniela) { echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit; }

try {
    $stmtF = $pdo->prepare("SELECT Id_Quiniela_F FROM QUINIELA_F WHERE Id_Quiniela = ?");
    $stmtF->execute([$idQuiniela]);
    $idF = $stmtF->fetchColumn();
    
    if (!$idF) { echo json_encode(['success'=>false, 'message'=>'Error configuración fantasy']); exit; }

    $fasesExclusivas = ['Jornada 1', 'Jornada 2', 'Jornada 3'];
    $esMercadoExclusivo = in_array($jornada, $fasesExclusivas);

    // Mercado (Igual que antes)
    $sqlMarket = "
        SELECT j.Id_Jugador, j.Nombre_Jugador, j.Posicion, j.Costo, j.Foto, e.Nombre_Equipo, e.Escudo
        FROM JUGADOR j
        JOIN EQUIPO e ON j.Id_Equipo = e.Id_Equipo
        JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo)
        WHERE p.Fase = ?
        ORDER BY j.Costo DESC
    ";
    $stmtM = $pdo->prepare($sqlMarket);
    $stmtM->execute([$jornada]);
    $todosJugadores = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Ocupados
    $ocupadosIds = [];
    if ($esMercadoExclusivo) {
        $stmtOcup = $pdo->prepare("SELECT Id_Jugador FROM SELECCION_JUGADORES WHERE Id_Quiniela_F = ? AND Fase = ? AND Id_Usuario != ?");
        $stmtOcup->execute([$idF, $jornada, $userId]);
        $ocupadosIds = $stmtOcup->fetchAll(PDO::FETCH_COLUMN);
    }

    // MI EQUIPO + PUNTOS (JOIN CON RENDIMIENTO)
    $sqlMyTeam = "
        SELECT j.Id_Jugador, j.Nombre_Jugador, j.Posicion, j.Costo, j.Foto, e.Nombre_Equipo,
               r.Calificacion_Final
        FROM SELECCION_JUGADORES s
        JOIN JUGADOR j ON s.Id_Jugador = j.Id_Jugador
        JOIN EQUIPO e ON j.Id_Equipo = e.Id_Equipo
        LEFT JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo) AND p.Fase = s.Fase
        LEFT JOIN RENDIMIENTO r ON r.Id_Jugador = j.Id_Jugador AND r.Id_Partido = p.Id_Partido
        WHERE s.Id_Quiniela_F = ? AND s.Fase = ? AND s.Id_Usuario = ?
    ";
    $stmtMyTeam = $pdo->prepare($sqlMyTeam);
    $stmtMyTeam->execute([$idF, $jornada, $userId]);
    $miEquipo = $stmtMyTeam->fetchAll(PDO::FETCH_ASSOC);

    $gastoActual = 0;
    $puntosTotales = 0;

    // Procesar Puntos
    foreach ($miEquipo as &$jug) {
        $gastoActual += $jug['Costo'];
        
        // CALCULO DE PUNTOS: (Calificacion - 6)
        // Si no ha jugado (null), es 0.
        if (isset($jug['Calificacion_Final'])) {
            $pts = round($jug['Calificacion_Final'] - 6);
            $jug['Puntos'] = $pts; // Puede ser negativo
            $puntosTotales += $pts;
        } else {
            $jug['Puntos'] = null; // Aún no juega
        }
    }

    $presupuestoRestante = 100.00 - $gastoActual;

    $mercadoFinal = [];
    foreach ($todosJugadores as $jug) {
        $jug['ocupado'] = in_array($jug['Id_Jugador'], $ocupadosIds);
        $jug['lo_tengo'] = false;
        foreach($miEquipo as $m) { if($m['Id_Jugador'] == $jug['Id_Jugador']) $jug['lo_tengo'] = true; }
        $mercadoFinal[] = $jug;
    }

    echo json_encode([
        'success' => true,
        'mercado' => $mercadoFinal,
        'mi_equipo' => $miEquipo,
        'presupuesto' => $presupuestoRestante,
        'puntos_totales' => $puntosTotales, // Nuevo dato
        'es_exclusivo' => $esMercadoExclusivo
    ]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
?>