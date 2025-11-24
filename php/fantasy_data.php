<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_GET['id_quiniela'] ?? 0;
$jornada = $_GET['jornada'] ?? 'Jornada 1';

if (!$userId || !$idQuiniela) { echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit; }

try {
    // 1. Obtener ID de la configuración Fantasy
    $stmtF = $pdo->prepare("SELECT Id_Quiniela_F FROM QUINIELA_F WHERE Id_Quiniela = ?");
    $stmtF->execute([$idQuiniela]);
    $idF = $stmtF->fetchColumn();
    
    if (!$idF) { echo json_encode(['success'=>false, 'message'=>'Error configuración fantasy']); exit; }

    $fasesExclusivas = ['Jornada 1', 'Jornada 2', 'Jornada 3'];
    $esMercadoExclusivo = in_array($jornada, $fasesExclusivas);

    // 2. Mercado de Jugadores
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

    // 3. Jugadores Ocupados (Si es exclusivo)
    $ocupadosIds = [];
    if ($esMercadoExclusivo) {
        $stmtOcup = $pdo->prepare("SELECT Id_Jugador FROM SELECCION_JUGADORES WHERE Id_Quiniela_F = ? AND Fase = ? AND Id_Usuario != ?");
        $stmtOcup->execute([$idF, $jornada, $userId]);
        $ocupadosIds = $stmtOcup->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. MI EQUIPO + ESTADÍSTICAS PARA PUNTOS
    // CORRECCIÓN AQUÍ: Usamos 'Tipo_Accion'
    $sqlMyTeam = "
        SELECT j.Id_Jugador, j.Nombre_Jugador, j.Posicion, j.Costo, j.Foto, j.Id_Equipo,
               e.Nombre_Equipo,
               r.Calificacion_Final,
               p.Id_Equipo_Local, p.Id_Equipo_Visitante, p.Goles_Local, p.Goles_Visitante, p.Estado as Estado_Partido,
               (SELECT COUNT(*) FROM ACCION a WHERE a.Id_Jugador = j.Id_Jugador AND a.Id_Partido = p.Id_Partido AND a.Tipo_Accion = 'Gol') as Cant_Goles,
               (SELECT COUNT(*) FROM ACCION a WHERE a.Id_Jugador = j.Id_Jugador AND a.Id_Partido = p.Id_Partido AND a.Tipo_Accion = 'Asistencia') as Cant_Asist,
               (SELECT COUNT(*) FROM ACCION a WHERE a.Id_Jugador = j.Id_Jugador AND a.Id_Partido = p.Id_Partido AND a.Tipo_Accion = 'Tarjeta Amarilla') as Cant_Amarillas,
               (SELECT COUNT(*) FROM ACCION a WHERE a.Id_Jugador = j.Id_Jugador AND a.Id_Partido = p.Id_Partido AND a.Tipo_Accion = 'Tarjeta Roja') as Cant_Rojas
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

    // 5. CÁLCULO DE PUNTOS
    foreach ($miEquipo as &$jug) {
        $gastoActual += $jug['Costo'];
        
        // Solo calculamos si hay datos de rendimiento o el partido terminó
        if (isset($jug['Calificacion_Final']) || (isset($jug['Estado_Partido']) && $jug['Estado_Partido'] == 'finalizado')) {
            $pts = 0;
            $pos = $jug['Posicion']; 

            // A) Rendimiento Base
            $pts += floatval($jug['Calificacion_Final']); 

            // B) Goles
            $multiGol = 0;
            if ($pos == 'DEL') $multiGol = 4;
            elseif ($pos == 'MED') $multiGol = 5;
            else $multiGol = 6; // DEF y POR
            $pts += ($jug['Cant_Goles'] * $multiGol);

            // C) Asistencias
            $multiAsist = 0;
            if ($pos == 'DEL') $multiAsist = 1;
            elseif ($pos == 'MED') $multiAsist = 3;
            else $multiAsist = 2; // DEF y POR
            $pts += ($jug['Cant_Asist'] * $multiAsist);

            // D) Castigos
            $pts += ($jug['Cant_Amarillas'] * -4);
            $pts += ($jug['Cant_Rojas'] * -10);

            // E) Resultado del Equipo y Portería a Cero
            $esLocal = ($jug['Id_Equipo'] == $jug['Id_Equipo_Local']);
            $golesFavor = $esLocal ? $jug['Goles_Local'] : $jug['Goles_Visitante'];
            $golesContra = $esLocal ? $jug['Goles_Visitante'] : $jug['Goles_Local'];
            
            $diferencia = $golesFavor - $golesContra;

            // Victoria / Derrota
            if ($diferencia > 0) {
                if ($diferencia >= 2) $pts += 2; 
                else $pts += 1; 
            } elseif ($diferencia < 0) {
                if ($diferencia <= -2) $pts += -5; 
                else $pts += -2; 
            }

            // Portería a Cero 
            if (($pos == 'DEF' || $pos == 'POR') && $golesContra == 0) {
                if ($pos == 'DEF') $pts += 3;
                if ($pos == 'POR') $pts += 5;
            }

            $jug['Puntos'] = round($pts);
            $puntosTotales += $jug['Puntos'];

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
        'puntos_totales' => $puntosTotales,
        'es_exclusivo' => $esMercadoExclusivo
    ]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
?>