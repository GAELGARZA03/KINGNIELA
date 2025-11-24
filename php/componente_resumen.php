<?php
require 'conexion.php';

// 1. DATOS GRUPOS Y ELIMINATORIAS (Igual que antes)
$sql = "SELECT e.Id_Equipo, e.Nombre_Equipo, e.Escudo, e.Grupo, SUM(CASE WHEN p.Goles_Local > p.Goles_Visitante AND p.Id_Equipo_Local = e.Id_Equipo THEN 3 WHEN p.Goles_Visitante > p.Goles_Local AND p.Id_Equipo_Visitante = e.Id_Equipo THEN 3 WHEN p.Goles_Local = p.Goles_Visitante THEN 1 ELSE 0 END) as Pts, SUM(CASE WHEN p.Id_Equipo_Local = e.Id_Equipo THEN p.Goles_Local - p.Goles_Visitante ELSE p.Goles_Visitante - p.Goles_Local END) as DG, SUM(CASE WHEN p.Id_Equipo_Local = e.Id_Equipo THEN p.Goles_Local ELSE p.Goles_Visitante END) as GF FROM EQUIPO e LEFT JOIN PARTIDO p ON (p.Id_Equipo_Local = e.Id_Equipo OR p.Id_Equipo_Visitante = e.Id_Equipo) AND p.Estado = 'finalizado' AND p.Fase LIKE 'Jornada%' GROUP BY e.Id_Equipo ORDER BY e.Grupo, Pts DESC, DG DESC, GF DESC";
$stmt = $pdo->query($sql);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grupos = []; foreach ($equipos as $eq) { $grupos[$eq['Grupo']][] = $eq; }

$terceros = [];
foreach ($grupos as $g => $lista) { if (isset($lista[2])) $terceros[] = $lista[2]; }
usort($terceros, function($a, $b) { if ($b['Pts'] != $a['Pts']) return $b['Pts'] - $a['Pts']; if ($b['DG'] != $a['DG']) return $b['DG'] - $a['DG']; return $b['GF'] - $a['GF']; });

$sqlElim = "SELECT p.Fase, p.Goles_Local, p.Goles_Visitante, p.Penales_Local, p.Penales_Visitante, el.Nombre_Equipo as Local, ev.Nombre_Equipo as Visitante FROM PARTIDO p JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo WHERE p.Fase NOT LIKE 'Jornada%' ORDER BY p.Id_Partido ASC";
$stmtElim = $pdo->query($sqlElim);
$partidosElim = $stmtElim->fetchAll(PDO::FETCH_ASSOC);
$fases = ['Dieciseisavos de final'=>[], 'Octavos de final'=>[], 'Cuartos de final'=>[], 'Semifinal'=>[], 'Final'=>[], 'Tercer Puesto'=>[]];
foreach ($partidosElim as $pe) { if (isset($fases[$pe['Fase']])) $fases[$pe['Fase']][] = $pe; }

// 2. LÍDERES
function getTop10($pdo, $tipoAccion) {
    $sql = "SELECT j.Nombre_Jugador, e.Nombre_Equipo, e.Escudo, COUNT(*) as Cantidad FROM ACCION a JOIN JUGADOR j ON a.Id_Jugador = j.Id_Jugador JOIN EQUIPO e ON j.Id_Equipo = e.Id_Equipo WHERE a.Tipo_Accion = ? GROUP BY j.Id_Jugador ORDER BY Cantidad DESC LIMIT 10";
    $stmt = $pdo->prepare($sql); $stmt->execute([$tipoAccion]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$topGoles = getTop10($pdo, 'gol');
$topAsist = getTop10($pdo, 'asistencia');
$topAmarillas = getTop10($pdo, 'tarjeta_amarilla');
$topRojas = getTop10($pdo, 'tarjeta_roja');
$sqlClean = "SELECT j.Nombre_Jugador, e.Nombre_Equipo, e.Escudo, COUNT(*) as Cantidad FROM RENDIMIENTO r JOIN JUGADOR j ON r.Id_Jugador = j.Id_Jugador JOIN EQUIPO e ON j.Id_Equipo = e.Id_Equipo JOIN PARTIDO p ON r.Id_Partido = p.Id_Partido WHERE j.Posicion = 'POR' AND ((p.Id_Equipo_Local = j.Id_Equipo AND p.Goles_Visitante = 0) OR (p.Id_Equipo_Visitante = j.Id_Equipo AND p.Goles_Local = 0)) GROUP BY j.Id_Jugador ORDER BY Cantidad DESC LIMIT 10";
$stmtClean = $pdo->query($sqlClean);
$topClean = $stmtClean->fetchAll(PDO::FETCH_ASSOC);

// 3. HISTORIAL DETALLADO
$sqlPartidosFull = "SELECT p.Id_Partido, p.Fase, el.Nombre_Equipo as Local, el.Escudo as EscudoL, p.Goles_Local, ev.Nombre_Equipo as Visitante, ev.Escudo as EscudoV, p.Goles_Visitante, p.Penales_Local, p.Penales_Visitante FROM PARTIDO p JOIN EQUIPO el ON p.Id_Equipo_Local = el.Id_Equipo JOIN EQUIPO ev ON p.Id_Equipo_Visitante = ev.Id_Equipo WHERE p.Estado = 'finalizado' ORDER BY p.Id_Partido DESC";
$stmtPF = $pdo->query($sqlPartidosFull);
$historialPartidos = $stmtPF->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista única de fases para el filtro
$listaFases = [];
foreach ($historialPartidos as $hp) { $listaFases[$hp['Fase']] = true; }
?>

<style>
    .resumen-wrapper { color: white; text-align: center; font-family: 'Montserrat', sans-serif; }
    h3 { color:#ffdd00; margin: 25px 0 15px 0; border-bottom: 1px solid #ffdd00; display:inline-block; padding-bottom: 5px; }
    .mini-table { width: 100%; font-size: 11px; border-collapse: collapse; }
    .mini-table td, .mini-table th { padding: 4px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .t-left { text-align: left; }
    .team-ico { width: 14px; vertical-align: middle; margin-right: 4px; }
    .group-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .group-box { background: rgba(255,255,255,0.05); border-radius: 8px; padding: 5px; border: 1px solid rgba(255,255,255,0.1); }
    .q-zone { background: rgba(0, 255, 0, 0.1); }
    .bracket-row { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; }
    .phase-col { min-width: 220px; }
    .match-mini { background: white; color: #333; border-radius: 4px; padding: 5px; margin-bottom: 5px; font-size: 12px; }
    .leaders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; text-align: left; }
    .leader-box { background: #001540; border-radius: 10px; padding: 10px; border: 1px solid #1a2cbd; }
    .leader-title { color: #ffdd00; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 5px; padding-bottom: 5px; }
    .feed-container { display: flex; flex-direction: column; gap: 10px; max-height: 500px; overflow-y: auto; text-align: left; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 10px; }
    .feed-card { background: white; color: #333; border-radius: 8px; padding: 10px; font-size: 13px; display: flex; flex-direction: column; }
    .feed-header { display: flex; justify-content: space-between; font-weight: bold; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 5px; }
    .feed-events { font-size: 11px; color: #555; line-height: 1.4; }
    .ev-gol { color: green; }
    .ev-card { color: #d4a000; }
    /* Estilo del Filtro */
    .filter-select { padding: 8px; border-radius: 5px; border: none; margin-left: 10px; font-family: 'Montserrat'; }
</style>

<div class="resumen-wrapper">

    <h3>Fase de Grupos</h3>
    <div class="group-grid">
        <?php foreach ($grupos as $letra => $lista): ?>
            <div class="group-box">
                <div style="background:#1a24ad; font-weight:bold; font-size:12px; padding:2px; margin-bottom:5px;">Grupo <?php echo $letra; ?></div>
                <table class="mini-table">
                    <tr><th class="t-left">Eq</th><th>Pts</th></tr>
                    <?php foreach ($lista as $i => $e): ?>
                        <tr class="<?php echo ($i<2)?'q-zone':''; ?>">
                            <td class="t-left"><img src="<?php echo $e['Escudo']; ?>" class="team-ico"><?php echo substr($e['Nombre_Equipo'], 0, 14); ?></td>
                            <td><b><?php echo $e['Pts']; ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Mejores 3ros</h3>
    <div style="overflow-x:auto;">
        <table class="mini-table" style="margin:0 auto; max-width:600px; background:#001540;">
            <tr style="background:#1a24ad;"><th>Pos</th><th class="t-left">Equipo</th><th>Gpo</th><th>Pts</th><th>DG</th><th>GF</th></tr>
            <?php foreach ($terceros as $idx => $eq): ?>
                <tr style="background:<?php echo ($idx<8)?'rgba(0,255,0,0.2)':'rgba(255,0,0,0.2)'; ?>">
                    <td><?php echo $idx+1; ?></td>
                    <td class="t-left"><img src="<?php echo $eq['Escudo']; ?>" class="team-ico"><?php echo $eq['Nombre_Equipo']; ?></td>
                    <td><?php echo $eq['Grupo']; ?></td>
                    <td><?php echo $eq['Pts']; ?></td>
                    <td><?php echo $eq['DG']; ?></td>
                    <td><?php echo $eq['GF']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <h3>Fase Final</h3>
    <div class="bracket-row">
        <?php foreach ($fases as $nombre => $partidos): if(empty($partidos)) continue; ?>
            <div class="phase-col">
                <div style="background:#ffdd00; color:#001f5c; font-weight:bold; font-size:12px; padding:3px; border-radius:4px; margin-bottom:5px;"><?php echo $nombre; ?></div>
                <?php foreach ($partidos as $m): ?>
                    <div class="match-mini">
                        <div style="display:flex; justify-content:space-between;"><span><?php echo $m['Local']; ?></span><b><?php echo $m['Goles_Local']??'-'; ?></b></div>
                        <div style="display:flex; justify-content:space-between;"><span><?php echo $m['Visitante']; ?></span><b><?php echo $m['Goles_Visitante']??'-'; ?></b></div>
                        <?php if($m['Penales_Local']!==null): ?><div style="font-size:9px; color:#666; text-align:right;">(Pen: <?php echo $m['Penales_Local'].'-'.$m['Penales_Visitante']; ?>)</div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Líderes del Torneo</h3>
    <div class="leaders-grid">
        <div class="leader-box"><div class="leader-title">⚽ Goleadores</div><table class="mini-table"><?php foreach($topGoles as $i=>$j): ?><tr><td><?php echo $i+1; ?></td><td class="t-left"><?php echo $j['Nombre_Jugador']; ?></td><td><img src="<?php echo $j['Escudo']; ?>" class="team-ico"></td><td><b><?php echo $j['Cantidad']; ?></b></td></tr><?php endforeach; ?></table></div>
        <div class="leader-box"><div class="leader-title">👟 Asistencias</div><table class="mini-table"><?php foreach($topAsist as $i=>$j): ?><tr><td><?php echo $i+1; ?></td><td class="t-left"><?php echo $j['Nombre_Jugador']; ?></td><td><img src="<?php echo $j['Escudo']; ?>" class="team-ico"></td><td><b><?php echo $j['Cantidad']; ?></b></td></tr><?php endforeach; ?></table></div>
        <div class="leader-box"><div class="leader-title">🧤 Porterías a Cero</div><table class="mini-table"><?php foreach($topClean as $i=>$j): ?><tr><td><?php echo $i+1; ?></td><td class="t-left"><?php echo $j['Nombre_Jugador']; ?></td><td><img src="<?php echo $j['Escudo']; ?>" class="team-ico"></td><td><b><?php echo $j['Cantidad']; ?></b></td></tr><?php endforeach; ?></table></div>
        <div class="leader-box"><div class="leader-title">🟨 Amarillas / 🟥 Rojas</div><table class="mini-table"><tr><th colspan="2">Amarillas</th><th colspan="2">Rojas</th></tr><?php for($k=0; $k<5; $k++): ?><tr><td class="t-left"><?php echo $topAmarillas[$k]['Nombre_Jugador']??'-'; ?></td><td><b><?php echo $topAmarillas[$k]['Cantidad']??''; ?></b></td><td class="t-left"><?php echo $topRojas[$k]['Nombre_Jugador']??'-'; ?></td><td><b><?php echo $topRojas[$k]['Cantidad']??''; ?></b></td></tr><?php endfor; ?></table></div>
    </div>

    <h3>
        Resultados Detallados 
        <select class="filter-select" onchange="filtrarPartidos(this.value)">
            <option value="all">Todas las Fases</option>
            <?php foreach(array_keys($listaFases) as $f): ?>
                <option value="<?php echo $f; ?>"><?php echo $f; ?></option>
            <?php endforeach; ?>
        </select>
    </h3>
    
    <div class="feed-container" id="feed-lista">
        <?php foreach($historialPartidos as $hp): 
            $stmtEv = $pdo->prepare("SELECT a.Tipo_Accion, a.Minuto, j.Nombre_Jugador FROM ACCION a JOIN JUGADOR j ON a.Id_Jugador = j.Id_Jugador WHERE a.Id_Partido = ? ORDER BY a.Minuto ASC");
            $stmtEv->execute([$hp['Id_Partido']]);
            $eventos = $stmtEv->fetchAll(PDO::FETCH_ASSOC);
        ?>
            <div class="feed-card" data-fase="<?php echo $hp['Fase']; ?>">
                <div class="feed-header">
                    <span style="display:flex; align-items:center; gap:5px;"><img src="<?php echo $hp['EscudoL']; ?>" class="team-ico"> <?php echo $hp['Local']; ?></span>
                    <span style="background:#eee; padding:2px 8px; border-radius:10px;"><?php echo $hp['Goles_Local'].'-'.$hp['Goles_Visitante']; ?></span>
                    <span style="display:flex; align-items:center; gap:5px;"><?php echo $hp['Visitante']; ?> <img src="<?php echo $hp['EscudoV']; ?>" class="team-ico"></span>
                </div>
                <div style="font-size:10px; color:#999; margin-bottom:5px;"><?php echo $hp['Fase']; ?> <?php if($hp['Penales_Local']!==null) echo " (Penales: {$hp['Penales_Local']}-{$hp['Penales_Visitante']})"; ?></div>
                <div class="feed-events">
                    <?php foreach($eventos as $ev): 
                        $icon = ''; $class = '';
                        if($ev['Tipo_Accion']=='gol') { $icon='⚽'; $class='ev-gol'; }
                        if($ev['Tipo_Accion']=='asistencia') { $icon='👟'; $class=''; }
                        if($ev['Tipo_Accion']=='tarjeta_amarilla') { $icon='🟨'; $class='ev-card'; }
                        if($ev['Tipo_Accion']=='tarjeta_roja') { $icon='🟥'; $class='ev-card'; }
                    ?>
                        <span class="<?php echo $class; ?>"><?php echo "{$ev['Minuto']}' $icon {$ev['Nombre_Jugador']}"; ?></span> &bull; 
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>