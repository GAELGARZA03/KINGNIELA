<?php
header('Content-Type: application/json');
require 'conexion.php';

$idQuiniela = $_GET['id_quiniela'] ?? 0;

try {
    // 1. Obtener ID Quiniela Fantasy
    $stmtF = $pdo->prepare("SELECT Id_Quiniela_F FROM QUINIELA_F WHERE Id_Quiniela = ?");
    $stmtF->execute([$idQuiniela]);
    $idF = $stmtF->fetchColumn();

    if (!$idF) { echo json_encode(['success'=>false, 'message'=>'Error config']); exit; }

    // 2. Obtener todos los miembros
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

    // 3. Definir fases a calcular
    $fases = ['Jornada 1', 'Jornada 2', 'Jornada 3']; 
    
    // Preparar Statement Reutilizable para obtener equipo y rendimiento
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
        $user['PuntosTotales'] = 0;
        $user['Pts_J1'] = 0;
        $user['Pts_J2'] = 0;
        $user['Pts_J3'] = 0;
        $user['Pts_Elim'] = 0; // Fantasy suele ser solo grupos o reiniciar, lo dejo en 0

        foreach ($fases as $fase) {
            $stmtStats->execute([$idF, $fase, $user['Id_Usuario']]);
            $equipo = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
            
            $puntosFase = 0;
            foreach ($equipo as $jug) {
                if (isset($jug['Calificacion_Final']) || (isset($jug['Estado_Partido']) && $jug['Estado_Partido'] == 'finalizado')) {
                    $pts = 0;
                    $pos = $jug['Posicion'];
                    
                    // Logica Idéntica a fantasy_data.php
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

                    $puntosFase += round($pts);
                }
            }

            if ($fase == 'Jornada 1') $user['Pts_J1'] = $puntosFase;
            if ($fase == 'Jornada 2') $user['Pts_J2'] = $puntosFase;
            if ($fase == 'Jornada 3') $user['Pts_J3'] = $puntosFase;
            $user['PuntosTotales'] += $puntosFase;
        }
    }

    // Ordenar ranking
    usort($usuarios, function($a, $b) {
        return $b['PuntosTotales'] - $a['PuntosTotales'];
    });

    echo json_encode(['success' => true, 'ranking' => $usuarios]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>