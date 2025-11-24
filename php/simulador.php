<?php
header('Content-Type: application/json');
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$fase = $data['fase'] ?? '';

if (empty($fase)) { echo json_encode(['success' => false, 'message' => 'Fase requerida']); exit; }

try {
    // Buscar partidos
    $stmt = $pdo->prepare("SELECT p.Id_Partido, el.Id_Equipo as Id_Local, el.Nombre_Equipo as Local, el.Rareza_Equipo as Rareza_L, ev.Id_Equipo as Id_Visit, ev.Nombre_Equipo as Visitante, ev.Rareza_Equipo as Rareza_V FROM PARTIDO p JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo WHERE p.Fase = ? AND p.Estado = 'programado'");
    $stmt->execute([$fase]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) { echo json_encode(['success' => false, 'message' => 'No hay partidos pendientes para esta fase']); exit; }

    $resultados = [];
    $esEliminatoria = (strpos($fase, 'Jornada') === false); // Si no dice "Jornada", es eliminatoria

    foreach ($partidos as $p) {
        $probLocal = calcularProbabilidadVictoria($p['Rareza_L'], $p['Rareza_V']);
        $rand = rand(1, 100);
        $golesL = 0; $golesV = 0; $penalesL = null; $penalesV = null; $huboPenales = false;

        // 90 Minutos
        if ($rand <= $probLocal) { $golesL = rand(1, 3); $golesV = rand(0, $golesL - 1); }
        elseif ($rand <= ($probLocal + 25)) { $golesL = rand(0, 2); $golesV = $golesL; } // Empate
        else { $golesV = rand(1, 3); $golesL = rand(0, $golesV - 1); }

        // TIEMPOS EXTRAS (Solo eliminatorias y si hay empate)
        if ($esEliminatoria && $golesL == $golesV) {
            // Simular 30 mins extra (probabilidad baja de gol)
            if (rand(1,100) <= 30) { // 30% chance de que se rompa el empate
                if (rand(1,100) <= 50) $golesL++; else $golesV++;
            }
        }

        // PENALES (Si sigue empate en eliminatoria)
        if ($esEliminatoria && $golesL == $golesV) {
            $huboPenales = true;
            $penalesL = 0; $penalesV = 0;
            // Tanda de 5
            for($i=0; $i<5; $i++) {
                if(rand(1,100) <= 75) $penalesL++; // 75% acierto base
                if(rand(1,100) <= 75) $penalesV++;
            }
            // Muerte súbita si siguen empatados
            while($penalesL == $penalesV) {
                if(rand(1,100) <= 70) $penalesL++;
                if(rand(1,100) <= 70) $penalesV++;
            }
        }

        // Guardar en BD
        $stmtUpd = $pdo->prepare("UPDATE PARTIDO SET Goles_Local = ?, Goles_Visitante = ?, Penales_Local = ?, Penales_Visitante = ?, Estado = 'finalizado' WHERE Id_Partido = ?");
        $stmtUpd->execute([$golesL, $golesV, $penalesL, $penalesV, $p['Id_Partido']]);

        // Simular eventos (Goles, Tarjetas, Rendimiento)
        // Usamos ganador de partido o de penales para las bonificaciones
        $ganadorLocal = ($golesL > $golesV) || ($huboPenales && $penalesL > $penalesV);
        $ganadorVisit = ($golesV > $golesL) || ($huboPenales && $penalesV > $penalesL);

        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Local'], $golesL, $golesV, $ganadorLocal);
        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Visit'], $golesV, $golesL, $ganadorVisit);

        $resTxt = "{$golesL} - {$golesV}";
        if($huboPenales) $resTxt .= " ({$penalesL}-{$penalesV} Pen.)";
        
        $resultados[] = ['partido' => "{$p['Local']} vs {$p['Visitante']}", 'resultado' => $resTxt];
    }

    echo json_encode(['success' => true, 'data' => $resultados]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }

// --- FUNCIONES AUXILIARES ---
function getRarezaValor($rareza) {
    switch(strtolower($rareza)) { case 'leyenda': return 4; case 'heroe': return 3; case 'crack': return 2; default: return 1; }
}
function calcularProbabilidadVictoria($rarezaL, $rarezaV) {
    $diff = getRarezaValor($rarezaL) - getRarezaValor($rarezaV);
    $prob = 40 + ($diff * 15) + 5; 
    return max(15, min(85, $prob));
}
function simularDesempenoEquipo($pdo, $idPartido, $idEquipo, $golesFavor, $golesContra, $gano) {
    // Intentar obtener jugadores reales
    $stmtJug = $pdo->prepare("SELECT * FROM JUGADOR WHERE Id_Equipo = ?");
    $stmtJug->execute([$idEquipo]);
    $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

    // SI NO HAY JUGADORES, GENERAMOS "FANTASMAS" PARA QUE NO FALLE EL SIMULADOR
    if (empty($jugadores)) {
        $jugadores = [];
        for($i=1; $i<=11; $i++) {
            $pos = ($i==1)?'POR':(($i<=5)?'DEF':(($i<=9)?'MED':'DEL'));
            $jugadores[] = ['Id_Jugador' => null, 'Nombre_Jugador' => 'Genérico', 'Posicion' => $pos, 'Rareza_Jugador' => 'Regular'];
        }
    }

    // Asignar Goles (Solo si el jugador existe en BD guardamos en ACCION, si es genérico solo cuenta para la lógica interna)
    for ($i = 0; $i < $golesFavor; $i++) {
        $candidatos = [];
        foreach ($jugadores as $k => $j) {
            $peso = ($j['Posicion']=='DEL')?50:(($j['Posicion']=='MED')?15:(($j['Posicion']=='DEF')?2:1));
            if ($j['Rareza_Jugador'] === 'Leyenda') $peso += 10;
            for ($x=0; $x<$peso; $x++) $candidatos[] = $k; // Guardamos índice del array
        }
        $idx = $candidatos[array_rand($candidatos)];
        
        if ($jugadores[$idx]['Id_Jugador']) {
            $stmtGol = $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES ('gol', ?, ?, ?)");
            $stmtGol->execute([rand(1, 90), $jugadores[$idx]['Id_Jugador'], $idPartido]);
        }
    }
    
    // Calificaciones (Solo para jugadores reales)
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $base = rand(60, 80)/10;
        if ($gano) $base += 0.5;
        if (!$gano && ($golesContra - $golesFavor) >= 3) $base -= 1.0;
        
        $stmtRend = $pdo->prepare("INSERT INTO RENDIMIENTO (Calificacion_Final, Id_Partido, Id_Jugador) VALUES (?, ?, ?)");
        $stmtRend->execute([$base, $idPartido, $j['Id_Jugador']]);
    }
}
?>