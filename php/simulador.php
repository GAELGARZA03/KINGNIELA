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

        // 90 Minutos
        if ($rand <= $probLocal) { $golesL = rand(1, 3); $golesV = rand(0, $golesL - 1); }
        elseif ($rand <= ($probLocal + 25)) { $golesL = rand(0, 2); $golesV = $golesL; } 
        else { $golesV = rand(1, 3); $golesL = rand(0, $golesV - 1); }

        // Tiempos Extras y Penales (Solo eliminatorias)
        if ($esEliminatoria && $golesL == $golesV) {
            if (rand(1,100) <= 30) { // Prórroga
                if (rand(1,100) <= 50) $golesL++; else $golesV++;
            }
        }
        if ($esEliminatoria && $golesL == $golesV) { // Penales
            $huboPenales = true;
            $penalesL = 0; $penalesV = 0;
            for($i=0; $i<5; $i++) { if(rand(1,100) <= 75) $penalesL++; if(rand(1,100) <= 75) $penalesV++; }
            while($penalesL == $penalesV) { if(rand(1,100) <= 70) $penalesL++; if(rand(1,100) <= 70) $penalesV++; }
        }

        // Guardar Resultado en BD
        $stmtUpd = $pdo->prepare("UPDATE PARTIDO SET Goles_Local = ?, Goles_Visitante = ?, Penales_Local = ?, Penales_Visitante = ?, Estado = 'finalizado' WHERE Id_Partido = ?");
        $stmtUpd->execute([$golesL, $golesV, $penalesL, $penalesV, $p['Id_Partido']]);

        $ganadorLocal = ($golesL > $golesV) || ($huboPenales && $penalesL > $penalesV);
        $ganadorVisit = ($golesV > $golesL) || ($huboPenales && $penalesV > $penalesL);

        // Simular Eventos (Jugadores)
        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Local'], $golesL, $golesV, $ganadorLocal);
        simularDesempenoEquipo($pdo, $p['Id_Partido'], $p['Id_Visit'], $golesV, $golesL, $ganadorVisit);

        // --- 2. CALIFICAR PREDICCIONES DE USUARIOS ---
        procesarPronosticos($pdo, $p['Id_Partido'], $golesL, $golesV);

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

// --- LÓGICA DE PUNTUACIÓN DE QUINIELA ---
function procesarPronosticos($pdo, $idPartido, $golesRealL, $golesRealV) {
    // Obtener todos los pronósticos para este partido
    $sql = "
        SELECT pr.Id_Pronostico, pr.Prediccion_Local, pr.Prediccion_Visitante, k.Dificultad 
        FROM PRONOSTICOS pr
        JOIN QUINIELA_K k ON pr.Id_Quiniela_K = k.Id_Quiniela_K
        WHERE pr.Id_Partido = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idPartido]);
    $pronosticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determinar signo real del partido (1, X, 2)
    $signoReal = 'E';
    if ($golesRealL > $golesRealV) $signoReal = 'L';
    elseif ($golesRealV > $golesRealL) $signoReal = 'V';
    
    $diferenciaReal = $golesRealL - $golesRealV;

    foreach ($pronosticos as $pron) {
        $puntos = 0;
        $acierto = 0;
        $pL = $pron['Prediccion_Local'];
        $pV = $pron['Prediccion_Visitante'];
        
        // Determinar signo de la predicción
        $signoPred = 'E';
        // Caso especial Aficionado: Guarda -1 para empate
        if ($pL == -1 && $pV == -1) $signoPred = 'E';
        elseif ($pL > $pV) $signoPred = 'L';
        elseif ($pV > $pL) $signoPred = 'V';

        // CASO 1: MODO AFICIONADO (Solo Resultado)
        if ($pron['Dificultad'] === 'Aficionado') {
            if ($signoPred === $signoReal) {
                $puntos = 3;
                $acierto = 1;
            }
        }
        // CASO 2: PROFESIONAL / LEYENDA (Marcador Exacto)
        else {
            // 1. Marcador Exacto (10 pts)
            if ($pL == $golesRealL && $pV == $golesRealV) {
                $puntos = 10;
                $acierto = 1;
            } 
            // 2. Ganador + Diferencia de Goles (5 pts)
            elseif ($signoPred === $signoReal && ($pL - $pV) == $diferenciaReal) {
                $puntos = 5;
                $acierto = 1; // Parcialmente correcto
            }
            // 3. Solo Ganador (3 pts)
            elseif ($signoPred === $signoReal) {
                $puntos = 3;
                $acierto = 1;
            }
            
            // TODO: Sumar puntos extra por Goleadores en modo Leyenda aquí
        }

        // Actualizar Pronóstico
        $stmtUpd = $pdo->prepare("UPDATE PRONOSTICOS SET Puntos_Obtenidos = ?, Acierto = ? WHERE Id_Pronostico = ?");
        $stmtUpd->execute([$puntos, $acierto, $pron['Id_Pronostico']]);
    }
}

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

    $golesPorJugador = [];
    $asistPorJugador = [];
    $tarjetasPorJugador = [];
    foreach ($jugadores as $j) if($j['Id_Jugador']) { 
        $golesPorJugador[$j['Id_Jugador']] = 0; 
        $asistPorJugador[$j['Id_Jugador']] = 0;
        $tarjetasPorJugador[$j['Id_Jugador']] = 0;
    }

    // Asignar Goles
    for ($i = 0; $i < $golesFavor; $i++) {
        $candidatos = [];
        foreach ($jugadores as $k => $j) {
            $peso = ($j['Posicion']=='DEL')?50:(($j['Posicion']=='MED')?15:(($j['Posicion']=='DEF')?2:0));
            if ($j['Rareza_Jugador'] === 'Leyenda') $peso += 60; 
            elseif ($j['Rareza_Jugador'] === 'Heroe') $peso += 30;
            elseif ($j['Rareza_Jugador'] === 'Crack') $peso += 10;
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

            // Asistencias
            if (rand(1,100) <= 70) {
                $candidatosAsist = [];
                foreach ($jugadores as $k => $j) {
                    if ($j['Id_Jugador'] && $j['Id_Jugador'] != $goleadorId) {
                        $pesoA = ($j['Posicion']=='MED')?40:(($j['Posicion']=='DEL')?35:(($j['Posicion']=='DEF')?5:1));
                        if ($j['Rareza_Jugador'] === 'Leyenda') $pesoA += 40;
                        elseif ($j['Rareza_Jugador'] === 'Heroe') $pesoA += 20;
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

    // Tarjetas
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $chance = ($j['Posicion']=='DEF')?9:(($j['Posicion']=='MED')?6:(($j['Posicion']=='DEL')?3:0.5));
        if (rand(1, 1000) <= ($chance * 10)) { 
            $tipo = (rand(1, 100) <= 88) ? 'tarjeta_amarilla' : 'tarjeta_roja';
            $tarjetasPorJugador[$j['Id_Jugador']] = ($tipo == 'tarjeta_amarilla' ? 1 : 2);
            $stmtCard = $pdo->prepare("INSERT INTO ACCION (Tipo_Accion, Minuto, Id_Jugador, Id_Partido) VALUES (?, ?, ?, ?)");
            $stmtCard->execute([$tipo, rand(1, 90), $j['Id_Jugador'], $idPartido]);
        }
    }
    
    // Calificaciones
    foreach ($jugadores as $j) {
        if (!$j['Id_Jugador']) continue;
        $id = $j['Id_Jugador'];
        $base = 6.0;
        if ($j['Rareza_Jugador'] === 'Leyenda') $base = rand(70, 80) / 10; 
        else if ($j['Rareza_Jugador'] === 'Regular') $base = rand(50, 75) / 10; 
        else $base = rand(60, 75) / 10;

        $base += ($golesPorJugador[$id] * 1.0);
        $base += ($asistPorJugador[$id] * 0.5); 
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