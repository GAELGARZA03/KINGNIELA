<?php
require 'conexion.php';

// --- 1. OBTENER DATOS GENERALES DE GRUPOS ---
// Calculamos puntos, DG y GF para TODOS los equipos
$sql = "
    SELECT e.Id_Equipo, e.Nombre_Equipo, e.Escudo, e.Grupo,
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
        ELSE p.Goles_Visitante END) as GF,
    COUNT(p.Id_Partido) as PJ
    FROM EQUIPO e
    LEFT JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo) 
        AND p.Estado = 'finalizado' AND p.Fase LIKE 'Jornada%'
    GROUP BY e.Id_Equipo
    ORDER BY e.Grupo, Pts DESC, DG DESC, GF DESC
";

$stmt = $pdo->query($sql);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar equipos por su Grupo (A, B, C...)
$grupos = [];
foreach ($equipos as $eq) {
    $grupos[$eq['Grupo']][] = $eq;
}

// Calcular terceros lugares para la tabla de "Mejores 3ros"
$terceros = [];
foreach ($grupos as $g => $lista) {
    if (isset($lista[2])) { // El índice 2 es el 3er lugar (0, 1, 2)
        $terceros[] = $lista[2];
    }
}
// Ordenar terceros
usort($terceros, function($a, $b) {
    if ($b['Pts'] != $a['Pts']) return $b['Pts'] - $a['Pts'];
    if ($b['DG'] != $a['DG']) return $b['DG'] - $a['DG'];
    return $b['GF'] - $a['GF'];
});

// --- 2. OBTENER PARTIDOS ELIMINATORIOS ---
$sqlElim = "
    SELECT p.Fase, p.Goles_Local, p.Goles_Visitante, p.Penales_Local, p.Penales_Visitante,
           el.Nombre_Equipo as Local, ev.Nombre_Equipo as Visitante
    FROM PARTIDO p
    JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo
    JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo
    WHERE p.Fase NOT LIKE 'Jornada%'
    ORDER BY p.Id_Partido ASC
";
$stmtElim = $pdo->query($sqlElim);
$partidosElim = $stmtElim->fetchAll(PDO::FETCH_ASSOC);

$fases = [
    'Dieciseisavos de final' => [], 'Octavos de final' => [],
    'Cuartos de final' => [], 'Semifinal' => [], 'Final' => []
];
foreach ($partidosElim as $pe) {
    if (isset($fases[$pe['Fase']])) $fases[$pe['Fase']][] = $pe;
}
?>

<style>
    /* Estilos compactos específicos para este componente */
    .resumen-wrapper { color: white; text-align: center; font-family: 'Montserrat', sans-serif; }
    
    /* Grid de Grupos */
    .group-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); 
        gap: 10px; 
        margin-bottom: 20px; 
    }
    .group-box { 
        background: rgba(255,255,255,0.05); 
        border-radius: 8px; 
        padding: 5px; 
        border: 1px solid rgba(255,255,255,0.1); 
    }
    .group-title { 
        background: #1a24ad; 
        color: white; 
        font-weight: bold; 
        padding: 3px; 
        border-radius: 4px; 
        font-size: 12px; 
        margin-bottom: 5px;
    }
    .mini-table { width: 100%; font-size: 11px; border-collapse: collapse; }
    .mini-table td, .mini-table th { padding: 2px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .mini-table .t-left { text-align: left; }
    .q-zone { background: rgba(0, 255, 0, 0.1); } /* Zona de clasificación directa */
    
    /* Bracket */
    .bracket-row { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; }
    .phase-col { min-width: 200px; }
    .match-mini { background: white; color: #333; border-radius: 4px; padding: 5px; margin-bottom: 5px; font-size: 11px; }
</style>

<div class="resumen-wrapper">

    <h3 style="color:#ffdd00; margin: 10px 0; border-bottom: 1px solid #ffdd00; display:inline-block;">Fase de Grupos</h3>
    <div class="group-grid">
        <?php foreach ($grupos as $letra => $lista): ?>
            <div class="group-box">
                <div class="group-title">Grupo <?php echo $letra; ?></div>
                <table class="mini-table">
                    <tr><th class="t-left">Eq</th><th>Pts</th></tr>
                    <?php foreach ($lista as $i => $e): ?>
                        <tr class="<?php echo ($i<2)?'q-zone':''; ?>">
                            <td class="t-left">
                                <img src="../<?php echo $e['Escudo']; ?>" style="width:12px; vertical-align:middle;">
                                <?php echo substr($e['Nombre_Equipo'], 0, 12); ?>
                            </td>
                            <td><b><?php echo $e['Pts']; ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <h3 style="color:#ffdd00; margin: 20px 0 10px 0; border-bottom: 1px solid #ffdd00; display:inline-block;">Mejores 3ros (Top 8 pasan)</h3>
    <div style="overflow-x:auto; margin-bottom:20px;">
        <table class="mini-table" style="margin: 0 auto; max-width: 600px;">
            <tr style="background: #1a24ad;"><th>Pos</th><th class="t-left">Equipo</th><th>Gpo</th><th>Pts</th><th>DG</th><th>GF</th></tr>
            <?php foreach ($terceros as $idx => $eq): ?>
                <tr style="background: <?php echo ($idx<8)?'rgba(0,255,0,0.2)':'rgba(255,0,0,0.2)'; ?>">
                    <td><?php echo $idx+1; ?></td>
                    <td class="t-left"><?php echo $eq['Nombre_Equipo']; ?></td>
                    <td><?php echo $eq['Grupo']; ?></td>
                    <td><?php echo $eq['Pts']; ?></td>
                    <td><?php echo $eq['DG']; ?></td>
                    <td><?php echo $eq['GF']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <h3 style="color:#ffdd00; margin: 20px 0 10px 0; border-bottom: 1px solid #ffdd00; display:inline-block;">Fase Final</h3>
    <div class="bracket-row">
        <?php foreach ($fases as $nombre => $partidos): ?>
            <?php if(empty($partidos)) continue; ?>
            <div class="phase-col">
                <div style="background:#ffdd00; color:#001f5c; font-weight:bold; font-size:12px; padding:3px; border-radius:4px; margin-bottom:5px;"><?php echo $nombre; ?></div>
                <?php foreach ($partidos as $m): ?>
                    <div class="match-mini">
                        <div style="display:flex; justify-content:space-between;">
                            <span><?php echo $m['Local']; ?></span>
                            <b><?php echo $m['Goles_Local'] ?? '-'; ?></b>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span><?php echo $m['Visitante']; ?></span>
                            <b><?php echo $m['Goles_Visitante'] ?? '-'; ?></b>
                        </div>
                        <?php if($m['Penales_Local'] !== null): ?>
                            <div style="font-size:9px; color:#666; text-align:right; border-top:1px solid #eee; margin-top:2px;">
                                Penales: <?php echo $m['Penales_Local'].'-'.$m['Penales_Visitante']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if(empty($partidosElim)): ?>
        <p style="color:#ccc; font-size:12px;">Simula hasta la Jornada 3 para ver el cuadro.</p>
    <?php endif; ?>

</div>