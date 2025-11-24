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
    $esEliminatoria = (strpos($fase, 'Jornada') === false);

    foreach ($partidos as $p) {
        $probLocal = calcularProbabilidadVictoria($p['Rareza_L'], $p['Rareza_V']);
        $rand = rand(1, 100);
        $golesL = 0; $golesV = 0; $penalesL = null; $penalesV = null; $huboPenales = false;

        // 90 Minutos
        if ($rand <= $probLocal) { $golesL = rand(1, 3); $golesV = rand(0, $golesL - 1); }
        elseif ($rand <= ($probLocal + 25)) { $golesL = rand(0, 2); $golesV = $golesL; } 
        else { $golesV = rand(1, 3); $golesL = rand(0, $golesV - 1); }

        // Tiempos Extras
        if ($esEliminatoria && $golesL == $golesV) {
            if (rand(1,100) <= 30) {
                if (rand(1,100) <= 50) $golesL++; else $golesV++;
            }
        }

        // Penales
        if ($esEliminatoria && $golesL == $golesV) {
            $huboPenales = true;
            $penalesL = 0; $penalesV = 0;
            for($i=0; $i<5; $i++) { if(rand(1,100) <= 75) $penalesL++; if(rand(1,100) <= 75) $penalesV++; }
            while($penalesL == $penalesV) { if(rand(1,100) <= 70) $penalesL++; if(rand(1,100) <= 70) $penalesV++; }
        }

        // Guardar Resultado
        $stmtUpd = $pdo->prepare("UPDATE PARTIDO SET Goles_Local = ?, Goles_Visitante = ?, Penales_Local = ?, Penales_Visitante = ?, Estado = 'finalizado' WHERE Id_Partido = ?");
        $stmtUpd->execute([$golesL, $golesV, $penalesL, $penalesV, $p['Id_Partido']]);

        $ganadorLocal = ($golesL > $golesV) || ($huboPenales && $penalesL > $penalesV);
        $ganadorVisit = ($golesV > $golesL) || ($huboPenales && $penalesV > $penalesL);

        // Simular eventos
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
    $stmtJug = $pdo->prepare("SELECT * FROM JUGADOR WHERE Id_Equipo = ?");
    $stmtJug->execute([$idEquipo]);
    $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jugadores)) { // Fantasmas si faltan datos
        $jugadores = [];
        for($i=1; $i<=11; $i++) {
            $pos = ($i==1)?'POR':(($i<=5)?'DEF':(($i<=9)?'MED':'DEL'));
            $jugadores[] = ['Id_Jugador' => null, 'Nombre_Jugador' => 'Genérico', 'Posicion' => $pos, 'Rareza_Jugador' => 'Regular'];
        }
    }

    $golesPorJugador = [];
    $asistPorJugador = [];
    $tarjetasPorJugador = [];
    foreach ($jugadores as $j) if($j['Id_Jugador']) { 
        $golesPorJugador[$j['Id_Jugador']] = 0; 
        $asistPorJugador[$j['Id_Jugador']] = 0;
        $tarjetasPorJugador[$j['Id_Jugador']] = 0;
    }

    // 1. ASIGNAR GOLES (Ajuste: Defensas casi nulo, Delanteros muy alto)
    for ($i = 0; $i < $golesFavor; $i++) {
        $candidatos = [];
        foreach ($jugadores as $k => $j) {
            // PESOS AJUSTADOS: DEL(80), MED(15), DEF(1)
            $peso = ($j['Posicion']=='DEL')?80:(($j['Posicion']=='MED')?15:(($j['Posicion']=='DEF')?1:0));
            if ($j['Rareza_Jugador'] === 'Leyenda') $peso += 20;
            if ($peso < 1) $peso = 1; 
            for ($x=0; $x<$peso; $x++) $candidatos[] = $k; 
        }
        $idxGol = $candidatos[array_rand($candidatos)];
        $goleadorId = $jugadores[$idxGol]['Id_Jugador'];
        
        if ($goleadorId) {
            $golesPorJugador[$goleadorId]++;
            $minuto = ($jugadores[$idxGol]['Posicion'] == 'POR') ? rand(88, 95) : rand(1, 90);
            
            $stmtGol = $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES ('gol', ?, ?, ?)");
            $stmtGol->execute([$minuto, $goleadorId, $idPartido]);

            // --- ASISTENCIAS (Ajuste: Probabilidad Ponderada) ---
            if (rand(1,100) <= 70) {
                $candidatosAsist = [];
                foreach ($jugadores as $k => $j) {
                    if ($j['Id_Jugador'] && $j['Id_Jugador'] != $goleadorId) {
                        // PESOS ASISTENCIA: MED(50), DEL(20), DEF(5), POR(1)
                        $pesoA = ($j['Posicion']=='MED')?50:(($j['Posicion']=='DEL')?20:(($j['Posicion']=='DEF')?5:1));
                        if ($j['Rareza_Jugador'] === 'Leyenda') $pesoA += 10;
                        for ($y=0; $y<$pesoA; $y++) $candidatosAsist[] = $j['Id_Jugador'];
                    }
                }
                if (!empty($candidatosAsist)) {
                    $asistidorId = $candidatosAsist[array_rand($candidatosAsist)];
                    $asistPorJugador[$asistidorId]++;
                    
                    $stmtAsist = $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES ('asistencia', ?, ?, ?)");
                    $stmtAsist->execute([$minuto, $asistidorId, $idPartido]);
                }
            }
        }
    }

    // 2. TARJETAS (Ajuste: Promedio 2.5 amarillas / 0.3 rojas)
    // Probabilidades ajustadas para sumar aprox ~1.25 tarjetas por equipo (2.5 total)
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        // DEF: 9%, MED: 6%, DEL: 3%, POR: 0.5%
        $chance = ($j['Posicion']=='DEF')?9:(($j['Posicion']=='MED')?6:(($j['Posicion']=='DEL')?3:0.5));
        
        if (rand(1, 1000) <= ($chance * 10)) { // Multiplicamos por 10 para usar rand 1000 y tener decimales
            // Roja: 12% de probabilidad si ya hubo falta (aprox 0.3 por partido total)
            $tipo = (rand(1, 100) <= 88) ? 'tarjeta_amarilla' : 'tarjeta_roja';
            $tarjetasPorJugador[$j['Id_Jugador']] = ($tipo == 'tarjeta_amarilla' ? 1 : 2);
            
            $stmtCard = $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES (?, ?, ?, ?)");
            $stmtCard->execute([$tipo, rand(1, 90), $j['Id_Jugador'], $idPartido]);
        }
    }
    
    // 3. CALIFICACIONES (Sin cambios mayores, solo el +0.5 asistencia ya estaba)
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $id = $j['Id_Jugador'];

        $base = 6.0;
        if ($j['Rareza_Jugador'] === 'Leyenda') $base = rand(70, 80) / 10; 
        else if ($j['Rareza_Jugador'] === 'Regular') $base = rand(50, 75) / 10; 
        else $base = rand(60, 75) / 10;

        $base += ($golesPorJugador[$id] * 1.0);
        $base += ($asistPorJugador[$id] * 0.5); // Bono Asistencia
        
        if ($gano) $base += 0.5;
        if (!$gano && ($golesContra - $golesFavor) >= 3) $base -= 1.0;

        if ($tarjetasPorJugador[$id] == 1) $base -= 0.5;
        if ($tarjetasPorJugador[$id] == 2) $base -= 2.0;

        if ($golesContra == 0 && ($j['Posicion'] == 'DEF' || $j['Posicion'] == 'POR')) $base += 1.0;
        if ($golesContra >= 3 && ($j['Posicion'] == 'DEF' || $j['Posicion'] == 'POR')) $base -= 1.5;

        if ($base > 10) $base = 10;
        if ($base < 0) $base = 0;
        
        $stmtRend = $pdo->prepare("INSERT INTO RENDIMIENTO (Calificacion_Final, Id_Partido, Id_Jugador) VALUES (?, ?, ?)");
        $stmtRend->execute([number_format($base, 1), $idPartido, $id]);
    }
}
?>