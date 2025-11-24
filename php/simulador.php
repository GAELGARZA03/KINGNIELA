<?php
header('Content-Type: application/json');
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$fase = $data['fase'] ?? '';

if (empty($fase)) { echo json_encode(['success' => false, 'message' => 'Fase requerida']); exit; }

try {
    // Buscar partidos programados
    $stmt = $pdo->prepare("SELECT p.Id_Partido, el.Id_Equipo as Id_Local, el.Nombre_Equipo as Local, el.Rareza_Equipo as Rareza_L, ev.Id_Equipo as Id_Visit, ev.Nombre_Equipo as Visitante, ev.Rareza_Equipo as Rareza_V FROM PARTIDO p JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo WHERE p.Fase = ? AND p.Estado = 'programado'");
    $stmt->execute([$fase]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) { echo json_encode(['success' => false, 'message' => 'No hay partidos pendientes para esta fase']); exit; }

    $resultados = [];
    $esEliminatoria = (strpos($fase, 'Jornada') === false);

    foreach ($partidos as $p) {
        // --- 1. SIMULACIÓN DEL PARTIDO ---
        $probLocal = calcularProbabilidadVictoria($p['Rareza_L'], $p['Rareza_V']);
        $rand = rand(1, 100);
        $golesL = 0; $golesV = 0; $penalesL = null; $penalesV = null; $huboPenales = false;
        $ganadorPenales = null; // 'L' o 'V'

        // 90 Minutos
        if ($rand <= $probLocal) { $golesL = rand(1, 3); $golesV = rand(0, $golesL - 1); }
        elseif ($rand <= ($probLocal + 25)) { $golesL = rand(0, 2); $golesV = $golesL; } 
        else { $golesV = rand(1, 3); $golesL = rand(0, $golesV - 1); }

        // Tiempos Extras (Solo eliminatorias)
        if ($esEliminatoria && $golesL == $golesV) {
            if (rand(1,100) <= 30) { // 30% chance de gol en prórroga
                if (rand(1,100) <= 50) $golesL++; else $golesV++;
            }
        }

        // Penales (Si sigue empate)
        if ($esEliminatoria && $golesL == $golesV) {
            $huboPenales = true;
            $penalesL = 0; $penalesV = 0;
            for($i=0; $i<5; $i++) { if(rand(1,100) <= 75) $penalesL++; if(rand(1,100) <= 75) $penalesV++; }
            while($penalesL == $penalesV) { if(rand(1,100) <= 70) $penalesL++; if(rand(1,100) <= 70) $penalesV++; }
            
            $ganadorPenales = ($penalesL > $penalesV) ? 'L' : 'V';
        }

        // Guardar Resultado en BD
        $stmtUpd = $pdo->prepare("UPDATE PARTIDO SET Goles_Local = ?, Goles_Visitante = ?, Penales_Local = ?, Penales_Visitante = ?, Estado = 'finalizado' WHERE Id_Partido = ?");
        $stmtUpd->execute([$golesL, $golesV, $penalesL, $penalesV, $p['Id_Partido']]);

        $ganadorLocal = ($golesL > $golesV) || ($huboPenales && $penalesL > $penalesV);
        $ganadorVisit = ($golesV > $golesL) || ($huboPenales && $penalesV > $penalesL);

        // Simular Eventos (Jugadores)
        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Local'], $golesL, $golesV, $ganadorLocal);
        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Visit'], $golesV, $golesL, $ganadorVisit);

        // --- 2. CALIFICAR PREDICCIONES (Pasamos quién ganó en penales) ---
        procesarPronosticos($pdo, $p['Id_Partido'], $golesL, $golesV, $ganadorPenales);

        $resTxt = "{$golesL} - {$golesV}";
        if($huboPenales) $resTxt .= " ({$penalesL}-{$penalesV} Pen.)";
        $resultados[] = ['partido' => "{$p['Local']} vs {$p['Visitante']}", 'resultado' => $resTxt];
    }

    echo json_encode(['success' => true, 'data' => $resultados]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }

// ==========================================================
// FUNCIONES AUXILIARES
// ==========================================================

function getRarezaValor($rareza) {
    switch(strtolower($rareza)) { case 'leyenda': return 4; case 'heroe': return 3; case 'crack': return 2; default: return 1; }
}
function calcularProbabilidadVictoria($rarezaL, $rarezaV) {
    $diff = getRarezaValor($rarezaL) - getRarezaValor($rarezaV);
    $prob = 40 + ($diff * 15) + 5; 
    return max(15, min(85, $prob));
}

// --- LÓGICA DE PUNTUACIÓN EXACTA ---
function procesarPronosticos($pdo, $idPartido, $golesRealL, $golesRealV, $ganadorPenales = null) {
    // Obtener todos los pronósticos
    $sql = "SELECT pr.Id_Pronostico, pr.Prediccion_Local, pr.Prediccion_Visitante, k.Dificultad FROM PRONOSTICOS pr JOIN QUINIELA_K k ON pr.Id_Quiniela_K = k.Id_Quiniela_K WHERE pr.Id_Partido = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idPartido]);
    $pronosticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resultados Reales
    $signoReal = ($golesRealL > $golesRealV) ? 'L' : (($golesRealV > $golesRealL) ? 'V' : 'E');
    $diferenciaReal = $golesRealL - $golesRealV;

    // Mapeo de Goles por Jugador (Para Modo Leyenda)
    $stmtGoles = $pdo->prepare("SELECT Id_Jugador, COUNT(*) as Goles FROM ACCION WHERE Id_Partido = ? AND Tipo_Accion = 'gol' GROUP BY Id_Jugador");
    $stmtGoles->execute([$idPartido]);
    $golesPorJugador = $stmtGoles->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($pronosticos as $pron) {
        $puntos = 0;
        $acierto = 0;
        $pL = $pron['Prediccion_Local'];
        $pV = $pron['Prediccion_Visitante'];
        
        // Signo Predicción
        $signoPred = ($pL > $pV) ? 'L' : (($pV > $pL) ? 'V' : 'E');
        if ($pL == -1) $signoPred = 'E'; // Aficionado

        // 1. CALCULO DE PUNTOS POR RESULTADO
        if ($pron['Dificultad'] === 'Aficionado') {
            if ($signoPred === $signoReal) { $puntos = 3; $acierto = 1; }
        } 
        else {
            // Profesional / Leyenda
            if ($pL == $golesRealL && $pV == $golesRealV) {
                $puntos = 10; $acierto = 1; // Exacto
            } elseif ($signoPred === $signoReal && ($pL - $pV) == $diferenciaReal) {
                $puntos = 5; $acierto = 1; // Diferencia
            } elseif ($signoPred === $signoReal) {
                $puntos = 3; $acierto = 1; // Ganador
            }
        }

        // 2. PUNTO DE CONSUELO (SI HUBO PENALES)
        // Si falló el pronóstico (puntos=0) PERO acertó al equipo que avanzó en penales
        if ($puntos == 0 && $ganadorPenales !== null) {
            if ($ganadorPenales === 'L' && $signoPred === 'L') {
                $puntos = 2; // Acertó que pasaba el Local
            } elseif ($ganadorPenales === 'V' && $signoPred === 'V') {
                $puntos = 2; // Acertó que pasaba el Visitante
            }
        }

        // 3. BONUS LEYENDA (GOLEADORES)
        if ($pron['Dificultad'] === 'Leyenda') {
            $stmtPG = $pdo->prepare("SELECT Id_Jugador FROM GOLEADORES_PRONOSTICO WHERE Id_Pronostico = ?");
            $stmtPG->execute([$pron['Id_Pronostico']]);
            $misGoleadores = $stmtPG->fetchAll(PDO::FETCH_COLUMN);

            foreach ($misGoleadores as $idJ) {
                if (isset($golesPorJugador[$idJ])) {
                    // CAMBIO: Ahora es +1 punto por cada gol (antes +5)
                    $puntos += ($golesPorJugador[$idJ] * 1);
                }
            }
        }

        $pdo->prepare("UPDATE PRONOSTICOS SET Puntos_Obtenidos = ?, Acierto = ? WHERE Id_Pronostico = ?")->execute([$puntos, $acierto, $pron['Id_Pronostico']]);
    }
}

// --- SIMULADOR DE EVENTOS (Sin cambios lógicos) ---
function simularDesempenoEquipo($pdo, $idPartido, $idEquipo, $golesFavor, $golesContra, $gano) {
    $stmtJug = $pdo->prepare("SELECT * FROM JUGADOR WHERE Id_Equipo = ?");
    $stmtJug->execute([$idEquipo]);
    $jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jugadores)) { 
        $jugadores = [];
        for($i=1; $i<=11; $i++) {
            $pos = ($i==1)?'POR':(($i<=5)?'DEF':(($i<=9)?'MED':'DEL'));
            $jugadores[] = ['Id_Jugador' => null, 'Nombre_Jugador' => 'Genérico', 'Posicion' => $pos, 'Rareza_Jugador' => 'Regular'];
        }
    }

    $golesPorJugador = []; $asistPorJugador = []; $tarjetasPorJugador = [];
    foreach ($jugadores as $j) if($j['Id_Jugador']) { $golesPorJugador[$j['Id_Jugador']] = 0; $asistPorJugador[$j['Id_Jugador']] = 0; $tarjetasPorJugador[$j['Id_Jugador']] = 0; }

    // ASIGNAR GOLES
    for ($i = 0; $i < $golesFavor; $i++) {
        $candidatos = [];
        foreach ($jugadores as $k => $j) {
            $peso = ($j['Posicion']=='DEL')?50:(($j['Posicion']=='MED')?15:(($j['Posicion']=='DEF')?2:0));
            if ($j['Rareza_Jugador'] === 'Leyenda') $peso += 60; elseif ($j['Rareza_Jugador'] === 'Heroe') $peso += 30; elseif ($j['Rareza_Jugador'] === 'Crack') $peso += 10;
            if ($peso < 1) $peso = 1; 
            for ($x=0; $x<$peso; $x++) $candidatos[] = $k; 
        }
        $idxGol = $candidatos[array_rand($candidatos)];
        $goleadorId = $jugadores[$idxGol]['Id_Jugador'];
        
        if ($goleadorId) {
            $golesPorJugador[$goleadorId]++;
            $minuto = ($jugadores[$idxGol]['Posicion'] == 'POR') ? rand(88, 95) : rand(1, 90);
            $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES ('gol', ?, ?, ?)")->execute([$minuto, $goleadorId, $idPartido]);

            if (rand(1,100) <= 70) {
                $candidatosAsist = [];
                foreach ($jugadores as $k => $j) {
                    if ($j['Id_Jugador'] && $j['Id_Jugador'] != $goleadorId) {
                        $pesoA = ($j['Posicion']=='MED')?40:(($j['Posicion']=='DEL')?35:(($j['Posicion']=='DEF')?5:1));
                        if ($j['Rareza_Jugador'] === 'Leyenda') $pesoA += 40; elseif ($j['Rareza_Jugador'] === 'Heroe') $pesoA += 20;
                        for ($y=0; $y<$pesoA; $y++) $candidatosAsist[] = $j['Id_Jugador'];
                    }
                }
                if (!empty($candidatosAsist)) {
                    $asistidorId = $candidatosAsist[array_rand($candidatosAsist)];
                    $asistPorJugador[$asistidorId]++;
                    $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES ('asistencia', ?, ?, ?)")->execute([$minuto, $asistidorId, $idPartido]);
                }
            }
        }
    }

    // TARJETAS
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $chance = ($j['Posicion']=='DEF')?9:(($j['Posicion']=='MED')?6:(($j['Posicion']=='DEL')?3:0.5));
        if (rand(1, 1000) <= ($chance * 10)) { 
            $tipo = (rand(1, 100) <= 88) ? 'tarjeta_amarilla' : 'tarjeta_roja';
            $tarjetasPorJugador[$j['Id_Jugador']] = ($tipo == 'tarjeta_amarilla' ? 1 : 2);
            $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES (?, ?, ?, ?)")->execute([$tipo, rand(1, 90), $j['Id_Jugador'], $idPartido]);
        }
    }
    
    // CALIFICACIONES
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $id = $j['Id_Jugador'];
        $base = 6.0;
        if ($j['Rareza_Jugador'] === 'Leyenda') $base = rand(70, 80) / 10; else if ($j['Rareza_Jugador'] === 'Regular') $base = rand(50, 75) / 10; else $base = rand(60, 75) / 10;

        $base += ($golesPorJugador[$id] * 1.0);
        $base += ($asistPorJugador[$id] * 0.5); 
        if ($gano) $base += 0.5;
        if (!$gano && ($golesContra - $golesFavor) >= 3) $base -= 1.0;
        if ($tarjetasPorJugador[$id] == 1) $base -= 0.5;
        if ($tarjetasPorJugador[$id] == 2) $base -= 2.0;
        if ($golesContra == 0 && ($j['Posicion'] == 'DEF' || $j['Posicion'] == 'POR')) $base += 1.0;
        if ($golesContra >= 3 && ($j['Posicion'] == 'DEF' || $j['Posicion'] == 'POR')) $base -= 1.5;

        if ($base > 10) $base = 10; if ($base < 0) $base = 0;
        $pdo->prepare("INSERT INTO RENDIMIENTO (Calificacion_Final, Id_Partido, Id_Jugador) VALUES (?, ?, ?)")->execute([number_format($base, 1), $idPartido, $id]);
    }
}
?>