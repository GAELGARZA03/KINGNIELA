<?php
header('Content-Type: application/json');
require 'conexion.php';

$fases = [
    'Jornada 1', 'Jornada 2', 'Jornada 3', 
    'Dieciseisavos de final', 'Octavos de final', 'Cuartos de final', 
    'Semifinal', 'Tercer Puesto', 'Final'
];

$estadoFases = [];

try {
    foreach ($fases as $fase) {
        // Contar partidos totales y finalizados en esa fase
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN Estado='finalizado' THEN 1 ELSE 0 END) as finalizados FROM PARTIDO WHERE Fase = ?");
        $stmt->execute([$fase]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        // Está "Simulada" si hay partidos (>0) y todos están terminados
        $esSimulada = ($res['total'] > 0 && $res['total'] == $res['finalizados']);
        $estadoFases[$fase] = $esSimulada;
    }

    echo json_encode(['success' => true, 'estados' => $estadoFases]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>