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

    // --- LÓGICA ESPECIAL PARA LA FINAL ---
    // Si la jornada es 'Final', incluimos también 'Tercer Puesto'
    if ($jornada === 'Final') {
        $fasesQuery = "('Final', 'Tercer Puesto')";
    } else {
        $fasesQuery = "('$jornada')";
    }

    // 1. Verificar Bloqueo (Si hay partidos finalizados en estas fases)
    $stmtLock = $pdo->query("SELECT COUNT(*) FROM PARTIDO WHERE Fase IN $fasesQuery AND Estado = 'finalizado'");
    $isLocked = ($stmtLock->fetchColumn() > 0);

    // 2. Mercado de Jugadores
    // Usamos IN $fasesQuery para traer jugadores de ambos partidos en la final
    $sqlMarket = "
        SELECT j.Id_Jugador, j.Nombre_Jugador, j.Posicion, j.Costo, j.Foto, e.Nombre_Equipo, e.Escudo
        FROM JUGADOR j
        JOIN EQUIPO e ON j.Id_Equipo = e.Id_Equipo
        JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo)
        WHERE p.Fase IN $fasesQuery
        ORDER BY j.Costo DESC
    ";
    $stmtM = $pdo->query($sqlMarket);
    $todosJugadores = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // 3. Jugadores Ocupados
    $ocupadosIds = [];
    if ($esMercadoExclusivo) {
        $stmtOcup = $pdo->prepare("SELECT Id_Jugador FROM SELECCION_JUGADORES WHERE Id_Quiniela_F = ? AND Fase = ? AND Id_Usuario != ?");
        $stmtOcup->execute([$idF, $jornada, $userId]);
        $ocupadosIds = $stmtOcup->fetchAll(PDO::FETCH_COLUMN);
    }

    // 4. MI EQUIPO + ESTADÍSTICAS
    // También ajustamos el JOIN de Partido para que coincida con las fases
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
        LEFT JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo) AND p.Fase IN $fasesQuery
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
        
        // Si jugó (tiene calificacion o partido finalizado)
        if (isset($jug['Calificacion_Final']) || (isset($jug['Estado_Partido']) && $jug['Estado_Partido'] == 'finalizado')) {
            $pts = 0;
            $pos = $jug['Posicion']; 

            $pts += floatval($jug['Calificacion_Final']); 

            $multiGol = ($pos == 'DEL') ? 4 : (($pos == 'MED') ? 5 : 6);
            $pts += ($jug['Cant_Goles'] * $multiGol);

            $multiAsist = ($pos == 'DEL') ? 1 : (($pos == 'MED') ? 3 : 2);
            $pts += ($jug['Cant_Asist'] * $multiAsist);

            $pts += ($jug['Cant_Amarillas'] * -4);
            $pts += ($jug['Cant_Rojas'] * -10);

            $esLocal = ($jug['Id_Equipo'] == $jug['Id_Equipo_Local']);
            $golesFavor = $esLocal ? $jug['Goles_Local'] : $jug['Goles_Visitante'];
            $golesContra = $esLocal ? $jug['Goles_Visitante'] : $jug['Goles_Local'];
            $diferencia = $golesFavor - $golesContra;

            if ($diferencia > 0) $pts += ($diferencia >= 2) ? 2 : 1;
            elseif ($diferencia < 0) $pts += ($diferencia <= -2) ? -5 : -2;

            if (($pos == 'DEF' || $pos == 'POR') && $golesContra == 0) {
                $pts += ($pos == 'DEF') ? 3 : 5;
            }

            $jug['Puntos'] = round($pts);
            $puntosTotales += $jug['Puntos'];

        } else {
            $jug['Puntos'] = null; 
        }
    }
    unset($jug); // Romper referencia

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
        'es_exclusivo' => $esMercadoExclusivo,
        'locked' => $isLocked
    ]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
?>