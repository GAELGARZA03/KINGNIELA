<?php
header('Content-Type: application/json');
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);
$faseAnterior = $data['fase_anterior'] ?? '';

if (empty($faseAnterior)) {
    echo json_encode(['success' => false, 'message' => 'Fase anterior no especificada']);
    exit;
}

// Mapa de progresión
$progreso = [
    'Dieciseisavos de final' => 'Octavos de final',
    'Octavos de final' => 'Cuartos de final',
    'Cuartos de final' => 'Semifinal',
    'Semifinal' => 'Final' // Caso especial: genera Final y 3er Puesto
];

if (!isset($progreso[$faseAnterior])) {
    echo json_encode(['success' => false, 'message' => 'Fase no válida para avance automático o es la final.']);
    exit;
}

$faseSiguiente = $progreso[$faseAnterior];

try {
    $pdo->beginTransaction();

    // 1. Obtener partidos finalizados de la fase anterior (ordenados por ID para emparejar 1vs2, 3vs4...)
    $stmt = $pdo->prepare("SELECT * FROM PARTIDO WHERE Fase = ? ORDER BY Id_Partido ASC");
    $stmt->execute([$faseAnterior]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($partidos)) {
        throw new Exception("No hay partidos en la fase anterior.");
    }

    // Verificar que todos estén terminados
    foreach ($partidos as $p) {
        if ($p['Estado'] !== 'finalizado') {
            throw new Exception("Aún hay partidos pendientes en $faseAnterior.");
        }
    }

    // 2. Borrar partidos previos de la fase siguiente (para evitar duplicados si se re-simula)
    $stmtDel = $pdo->prepare("DELETE FROM PARTIDO WHERE Fase = ? OR Fase = 'Tercer Puesto'");
    $stmtDel->execute([$faseSiguiente]);

    // 3. Generar cruces
    $crucesGenerados = [];
    
    // Iteramos de 2 en 2 (El ganador del partido 0 vs el ganador del partido 1)
    for ($i = 0; $i < count($partidos); $i += 2) {
        if (!isset($partidos[$i+1])) break; // Seguridad por si es impar

        $p1 = $partidos[$i];
        $p2 = $partidos[$i+1];

        // Determinar ganadores P1
        $ganador1 = obtenerGanador($p1);
        $perdedor1 = obtenerPerdedor($p1); // Solo importa en semis

        // Determinar ganadores P2
        $ganador2 = obtenerGanador($p2);
        $perdedor2 = obtenerPerdedor($p2); // Solo importa en semis

        // Insertar partido Fase Siguiente
        insertarPartido($pdo, $faseSiguiente, $ganador1, $ganador2);
        $crucesGenerados[] = "$faseSiguiente: Eq $ganador1 vs Eq $ganador2";

        // CASO ESPECIAL: SEMIFINAL -> Generar Tercer Puesto
        if ($faseAnterior === 'Semifinal') {
            insertarPartido($pdo, 'Tercer Puesto', $perdedor1, $perdedor2);
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
    // Si hubo penales, deciden los penales
    if ($p['Penales_Local'] !== null) {
        return ($p['Penales_Local'] > $p['Penales_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
    }
    // Si no, deciden los goles
    return ($p['Goles_Local'] > $p['Goles_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
}

function obtenerPerdedor($p) {
    // Lógica inversa al ganador
    if ($p['Penales_Local'] !== null) {
        return ($p['Penales_Local'] < $p['Penales_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
    }
    return ($p['Goles_Local'] < $p['Goles_Visitante']) ? $p['Id_Equipo_Local'] : $p['Id_Equipo_Visitante'];
}

function insertarPartido($pdo, $fase, $local, $visitante) {
    $stmt = $pdo->prepare("INSERT INTO PARTIDO (Fase, Estado, Id_Equipo_Local, Id_Equipo_Visitante) VALUES (?, 'programado', ?, ?)");
    $stmt->execute([$fase, $local, $visitante]);
}
?>