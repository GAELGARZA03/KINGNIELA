<?php
header('Content-Type: application/json');
require 'conexion.php';

$idQuiniela = $_GET['id_quiniela'] ?? 0;

try {
    $stmtF = $pdo->prepare("SELECT Id_Quiniela_F FROM QUINIELA_F WHERE Id_Quiniela = ?");
    $stmtF->execute([$idQuiniela]);
    $idF = $stmtF->fetchColumn();

    if (!$idF) { echo json_encode(['success'=>false, 'message'=>'Error config']); exit; }

    $sqlUsers = "
        SELECT u.Id_Usuario, u.Nombre_Usuario, u.Avatar, c.Imagen_Url as Corona
        FROM QUINIELA_INTEGRANTES qi
        JOIN USUARIO u ON qi.Id_Usuario = u.Id_Usuario
        LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
        LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
        WHERE qi.Id_Quiniela = ?
    ";
    $stmtU = $pdo->prepare($sqlUsers);
    $stmtU->execute([$idQuiniela]);
    $usuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    // Lista de todas las fases posibles
    $todasLasFases = [
        'Jornada 1', 'Jornada 2', 'Jornada 3', 
        'Dieciseisavos de final', 'Octavos de final', 
        'Cuartos de final', 'Semifinal', 'Final', 'Tercer Puesto'
    ];
    
    $sqlTeamStats = "
        SELECT j.Posicion, j.Id_Equipo,
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
    $stmtStats = $pdo->prepare($sqlTeamStats);

    foreach ($usuarios as &$user) {
        // Inicializar contadores
        $user['PuntosTotales'] = 0;
        $user['Pts_J1'] = 0;
        $user['Pts_J2'] = 0;
        $user['Pts_J3'] = 0;
        $user['Pts_16'] = 0;
        $user['Pts_8'] = 0;
        $user['Pts_4'] = 0;
        $user['Pts_Semi'] = 0;
        $user['Pts_Final'] = 0;

        foreach ($todasLasFases as $fase) {
            // Truco: Si la fase es "Tercer Puesto", usamos el equipo guardado en "Final"
            $faseBusqueda = ($fase === 'Tercer Puesto') ? 'Final' : $fase;

            $stmtStats->execute([$idF, $faseBusqueda, $user['Id_Usuario']]);
            $equipo = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
            
            $puntosFase = 0;
            foreach ($equipo as $jug) {
                // Calcular puntos (Lógica estándar)
                if (isset($jug['Calificacion_Final']) || (isset($jug['Estado_Partido']) && $jug['Estado_Partido'] == 'finalizado')) {
                    $pts = 0;
                    $pos = $jug['Posicion'];
                    $pts += floatval($jug['Calificacion_Final']); 
                    
                    // Goles
                    $multiGol = ($pos == 'DEL') ? 4 : (($pos == 'MED') ? 5 : 6);
                    $pts += ($jug['Cant_Goles'] * $multiGol);
                    
                    // Asistencias
                    $multiAsist = ($pos == 'DEL') ? 1 : (($pos == 'MED') ? 3 : 2);
                    $pts += ($jug['Cant_Asist'] * $multiAsist);
                    
                    // Tarjetas
                    $pts += ($jug['Cant_Amarillas'] * -4);
                    $pts += ($jug['Cant_Rojas'] * -10);
                    
                    // Resultado
                    $esLocal = ($jug['Id_Equipo'] == $jug['Id_Equipo_Local']);
                    $golesFavor = $esLocal ? $jug['Goles_Local'] : $jug['Goles_Visitante'];
                    $golesContra = $esLocal ? $jug['Goles_Visitante'] : $jug['Goles_Local'];
                    $diferencia = $golesFavor - $golesContra;

                    if ($diferencia > 0) $pts += ($diferencia >= 2) ? 2 : 1;
                    elseif ($diferencia < 0) $pts += ($diferencia <= -2) ? -5 : -2;

                    if (($pos == 'DEF' || $pos == 'POR') && $golesContra == 0) {
                        $pts += ($pos == 'DEF') ? 3 : 5;
                    }
                    $puntosFase += round($pts);
                }
            }

            // Asignar a la columna correcta
            if ($fase == 'Jornada 1') $user['Pts_J1'] = $puntosFase;
            elseif ($fase == 'Jornada 2') $user['Pts_J2'] = $puntosFase;
            elseif ($fase == 'Jornada 3') $user['Pts_J3'] = $puntosFase;
            elseif ($fase == 'Dieciseisavos de final') $user['Pts_16'] = $puntosFase;
            elseif ($fase == 'Octavos de final') $user['Pts_8'] = $puntosFase;
            elseif ($fase == 'Cuartos de final') $user['Pts_4'] = $puntosFase;
            elseif ($fase == 'Semifinal') $user['Pts_Semi'] = $puntosFase;
            elseif ($fase == 'Final' || $fase == 'Tercer Puesto') {
                // Sumar tanto Final como Tercer Puesto a la columna 'Pts_Final'
                $user['Pts_Final'] += $puntosFase;
            }

            $user['PuntosTotales'] += $puntosFase;
        }
    }

    usort($usuarios, function($a, $b) {
        return $b['PuntosTotales'] - $a['PuntosTotales'];
    });

    echo json_encode(['success' => true, 'ranking' => $usuarios]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>