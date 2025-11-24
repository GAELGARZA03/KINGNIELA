<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_GET['id_quiniela'] ?? 0;
$jornada = $_GET['jornada'] ?? 'Jornada 1';

if (!$userId || !$idQuiniela) {
    echo json_encode(['success' => false, 'message' => 'Datos faltantes']);
    exit;
}

try {
    // 1. Info Quiniela
    $stmtQ = $pdo->prepare("SELECT q.Tipo_Quiniela, k.Dificultad, k.Id_Quiniela_K FROM QUINIELA q LEFT JOIN QUINIELA_K k ON q.Id_Quiniela = k.Id_Quiniela WHERE q.Id_Quiniela = ?");
    $stmtQ->execute([$idQuiniela]);
    $infoQ = $stmtQ->fetch(PDO::FETCH_ASSOC);

    if (!$infoQ) { echo json_encode(['success' => false, 'message' => 'Quiniela no encontrada']); exit; }

    // 2. Partidos
    $stmtP = $pdo->prepare("
        SELECT p.Id_Partido, p.Fecha_Partido, p.Hora_Partido, p.Estado, p.Goles_Local, p.Goles_Visitante,
               el.Id_Equipo as IdL, el.Nombre_Equipo as Local, el.Escudo as EscudoL,
               ev.Id_Equipo as IdV, ev.Nombre_Equipo as Visitante, ev.Escudo as EscudoV
        FROM PARTIDO p
        JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo
        JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo
        WHERE p.Fase = ?
        ORDER BY p.Fecha_Partido, p.Hora_Partido
    ");
    $stmtP->execute([$jornada]);
    $partidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // 3. Pronósticos y Jugadores
    $predicciones = [];
    $goleadoresPred = [];
    $jugadoresPorEquipo = []; // Cache para no repetir consultas

    if ($infoQ['Tipo_Quiniela'] === 'kingniela' && $infoQ['Id_Quiniela_K']) {
        // Pronósticos Base
        $stmtPred = $pdo->prepare("SELECT Id_Partido, Id_Pronostico, Prediccion_Local, Prediccion_Visitante, Puntos_Obtenidos, Acierto FROM PRONOSTICOS WHERE Id_Quiniela_K = ? AND Id_Usuario = ?");
        $stmtPred->execute([$infoQ['Id_Quiniela_K'], $userId]);
        $predsRaw = $stmtPred->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($predsRaw as $pr) {
            $predicciones[$pr['Id_Partido']] = $pr;
            
            // Si es Leyenda, cargar goleadores pronosticados
            if ($infoQ['Dificultad'] === 'Leyenda') {
                $stmtGol = $pdo->prepare("SELECT Id_Jugador FROM GOLEADORES_PRONOSTICO WHERE Id_Pronostico = ?");
                $stmtGol->execute([$pr['Id_Pronostico']]);
                $goleadoresPred[$pr['Id_Pronostico']] = $stmtGol->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }

    // Cargar Jugadores (Solo si es Leyenda)
    if ($infoQ['Dificultad'] === 'Leyenda') {
        // Recopilar IDs de equipos en esta jornada
        $equiposIds = [];
        foreach ($partidos as $p) { $equiposIds[] = $p['IdL']; $equiposIds[] = $p['IdV']; }
        $equiposIds = array_unique($equiposIds);
        
        if (!empty($equiposIds)) {
            $inQuery = implode(',', array_fill(0, count($equiposIds), '?'));
            $stmtJ = $pdo->prepare("SELECT Id_Jugador, Nombre_Jugador, Posicion, Id_Equipo FROM JUGADOR WHERE Id_Equipo IN ($inQuery) ORDER BY FIELD(Posicion, 'DEL', 'MED', 'DEF', 'POR'), Nombre_Jugador");
            $stmtJ->execute($equiposIds);
            $allPlayers = $stmtJ->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allPlayers as $pl) {
                $jugadoresPorEquipo[$pl['Id_Equipo']][] = $pl;
            }
        }
    }

    // 4. Armar Respuesta
    $listaFinal = [];
    foreach ($partidos as $p) {
        $pred = $predicciones[$p['Id_Partido']] ?? null;
        $pronosticoId = $pred ? $pred['Id_Pronostico'] : 0;
        
        $listaFinal[] = [
            'id' => $p['Id_Partido'],
            'fecha' => $p['Fecha_Partido'] . ' ' . substr($p['Hora_Partido'], 0, 5),
            'estado' => $p['Estado'],
            'local' => $p['Local'], 'idL' => $p['IdL'], 'escudoL' => $p['EscudoL'],
            'visitante' => $p['Visitante'], 'idV' => $p['IdV'], 'escudoV' => $p['EscudoV'],
            'goles_real_L' => $p['Goles_Local'], 'goles_real_V' => $p['Goles_Visitante'],
            'pred_L' => $pred ? $pred['Prediccion_Local'] : '',
            'pred_V' => $pred ? $pred['Prediccion_Visitante'] : '',
            'puntos' => $pred ? $pred['Puntos_Obtenidos'] : 0,
            // Datos Leyenda
            'jugadores_L' => $jugadoresPorEquipo[$p['IdL']] ?? [],
            'jugadores_V' => $jugadoresPorEquipo[$p['IdV']] ?? [],
            'mis_goleadores' => $goleadoresPred[$pronosticoId] ?? [] // Lista de IDs guardados
        ];
    }

    echo json_encode(['success' => true, 'config' => $infoQ, 'partidos' => $listaFinal]);

} catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
?>