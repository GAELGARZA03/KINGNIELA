<?php
header('Content-Type: application/json');
require 'conexion.php';

try {
    // 1. OBTENER CLASIFICACIÓN DE GRUPOS
    $sql = "
        SELECT e.Id_Equipo, e.Nombre_Equipo, e.Grupo,
        SUM(CASE 
            WHEN p.Goles_Local > p.Goles_Visitante AND p.Id_Equipo_Local = e.Id_Equipo THEN 3
            WHEN p.Goles_Visitante > p.Goles_Local AND p.Id_Equipo_Visitante = e.Id_Equipo THEN 3
            WHEN p.Goles_Local = p.Goles_Visitante THEN 1
            ELSE 0 END) as Pts,
        SUM(CASE 
            WHEN p.Id_Equipo_Local = e.Id_Equipo THEN p.Goles_Local - p.Goles_Visitante
            ELSE p.Goles_Visitante - p.Goles_Local END) as DG,
        SUM(CASE 
            WHEN p.Id_Equipo_Local = e.Id_Equipo THEN p.Goles_Local
            ELSE p.Goles_Visitante END) as GF
        FROM EQUIPO e
        JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo)
        WHERE p.Estado = 'finalizado' AND p.Fase LIKE 'Jornada%'
        GROUP BY e.Id_Equipo
        ORDER BY e.Grupo, Pts DESC, DG DESC, GF DESC
    ";
    
    $stmt = $pdo->query($sql);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($equipos) < 32) {
        echo json_encode(['success' => false, 'message' => 'Faltan partidos por jugar.']);
        exit;
    }

    // 2. SEPARAR CLASIFICADOS
    $primeros = [];
    $segundos = [];
    $terceros = [];

    $grupos = [];
    foreach ($equipos as $eq) {
        $grupos[$eq['Grupo']][] = $eq;
    }

    foreach ($grupos as $g => $lista) {
        if (isset($lista[0])) $primeros[] = $lista[0];
        if (isset($lista[1])) $segundos[] = $lista[1];
        if (isset($lista[2])) $terceros[] = $lista[2];
    }

    // Mejores terceros
    usort($terceros, function($a, $b) {
        if ($b['Pts'] != $a['Pts']) return $b['Pts'] - $a['Pts'];
        if ($b['DG'] != $a['DG']) return $b['DG'] - $a['DG'];
        return $b['GF'] - $a['GF'];
    });
    $mejoresTerceros = array_slice($terceros, 0, 8);
    
    // Total 32
    $clasificados = array_merge($primeros, $segundos, $mejoresTerceros);

    // 3. GENERAR CRUCES (1 vs 32, 2 vs 31...)
    usort($clasificados, function($a, $b) {
        if ($b['Pts'] != $a['Pts']) return $b['Pts'] - $a['Pts'];
        return $b['DG'] - $a['DG'];
    });

    $top16 = array_slice($clasificados, 0, 16); 
    $low16 = array_slice($clasificados, 16, 16); 
    $low16 = array_reverse($low16); 

    $pdo->beginTransaction();
    
    $pdo->query("DELETE FROM PARTIDO WHERE Fase = 'Dieciseisavos de final'");

    $crucesGenerados = [];
    // FECHA OFICIAL INICIO ELIMINATORIAS: 29 DE JUNIO
    $fechaDieci = '2026-06-29'; 

    for ($i = 0; $i < 16; $i++) {
        $local = $top16[$i];
        $visita = $low16[$i];
        
        // CAMBIO: Agregamos Fecha y Hora
        $stmtIns = $pdo->prepare("INSERT INTO PARTIDO (Fase, Estado, Id_Equipo_Local, Id_Equipo_Visitante, Fecha_Partido, Hora_Partido) VALUES ('Dieciseisavos de final', 'programado', ?, ?, ?, '20:00:00')");
        $stmtIns->execute([$local['Id_Equipo'], $visita['Id_Equipo'], $fechaDieci]);
        
        $crucesGenerados[] = "{$local['Nombre_Equipo']} vs {$visita['Nombre_Equipo']}";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Cuadro generado', 'cruces' => $crucesGenerados]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>