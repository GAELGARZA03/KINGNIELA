<?php
header('Content-Type: application/json');
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$faseAnterior = $data['fase_anterior'] ?? '';

if (empty($faseAnterior)) {
    echo json_encode(['success' => false, 'message' => 'Fase anterior no especificada']);
    exit;
}

$progreso = [
    'Dieciseisavos de final' => 'Octavos de final',
    'Octavos de final' => 'Cuartos de final',
    'Cuartos de final' => 'Semifinal',
    'Semifinal' => 'Final'
];

// MAPA DE FECHAS REALISTAS 2026
$calendarioFases = [
    'Octavos de final' => '2026-07-04',
    'Cuartos de final' => '2026-07-09',
    'Semifinal' => '2026-07-14',
    'Tercer Puesto' => '2026-07-18',
    'Final' => '2026-07-19'
];

if (!isset($progreso[$faseAnterior])) {
    echo json_encode(['success' => false, 'message' => 'Fase no válida o es final.']);
    exit;
}

$faseSiguiente = $progreso[$faseAnterior];

try {
    $pdo->beginTransaction();

    // Obtener partidos previos
    $stmt = $pdo->prepare("SELECT * FROM PARTIDO WHERE Fase = ? ORDER BY Id_Partido ASC");
    $stmt->execute([$faseAnterior]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) throw new Exception("No hay partidos en $faseAnterior.");

    foreach ($partidos as $p) {
        if ($p['Estado'] !== 'finalizado') throw new Exception("Faltan partidos por jugar en $faseAnterior.");
    }

    // Borrar existentes de la siguiente fase
    $stmtDel = $pdo->prepare("DELETE FROM PARTIDO WHERE Fase = ? OR Fase = 'Tercer Puesto'");
    $stmtDel->execute([$faseSiguiente]);

    $crucesGenerados = [];
    
    // Fecha a usar
    $fechaJuego = $calendarioFases[$faseSiguiente] ?? '2026-07-01';

    for ($i = 0; $i < count($partidos); $i += 2) {
        if (!isset($partidos[$i+1])) break;

        $p1 = $partidos[$i];
        $p2 = $partidos[$i+1];

        $ganador1 = obtenerGanador($p1);
        $perdedor1 = obtenerPerdedor($p1);
        $ganador2 = obtenerGanador($p2);
        $perdedor2 = obtenerPerdedor($p2);

        // Insertar Siguiente Ronda con FECHA
        insertarPartido($pdo, $faseSiguiente, $ganador1, $ganador2, $fechaJuego);
        $crucesGenerados[] = "$faseSiguiente: Eq $ganador1 vs Eq $ganador2";

        // Caso Semifinal -> Tercer Puesto
        if ($faseAnterior === 'Semifinal') {
            $fecha3ro = $calendarioFases['Tercer Puesto'];
            insertarPartido($pdo, 'Tercer Puesto', $perdedor1, $perdedor2, $fecha3ro);
            $crucesGenerados[] = "Tercer Puesto: Eq $perdedor1 vs Eq $perdedor2";
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Cruces generados para $faseSiguiente", 'cruces' => $crucesGenerados]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// --- FUNCIONES AUXILIARES ---

function obtenerGanador($p) {
    if ($p['Penales_Local'] !== null) {
        return ($p['Penales_Local'] > $p['Penales_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
    }
    return ($p['Goles_Local'] > $p['Goles_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
}

function obtenerPerdedor($p) {
    if ($p['Penales_Local'] !== null) {
        return ($p['Penales_Local'] < $p['Penales_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
    }
    return ($p['Goles_Local'] < $p['Goles_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
}

// CAMBIO: Función acepta fecha
function insertarPartido($pdo, $fase, $local, $visitante, $fecha) {
    $stmt = $pdo->prepare("INSERT INTO PARTIDO (Fase, Estado, Id_Equipo_Local, Id_Equipo_Visitante, Fecha_Partido, Hora_Partido) VALUES (?, 'programado', ?, ?, ?, '20:00:00')");
    $stmt->execute([$fase, $local, $visitante, $fecha]);
}
?>