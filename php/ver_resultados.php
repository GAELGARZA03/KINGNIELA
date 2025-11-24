<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen del Torneo - Kingniela 2026</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #001f5c;
            color: white;
            margin: 0;
            padding: 20px;
        }
        h1, h2 { text-align: center; color: #ffdd00; text-transform: uppercase; letter-spacing: 2px; }
        
        /* --- ESTILOS DE GRUPOS --- */
        .groups-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 50px;
        }
        .group-card {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            width: 300px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .group-header {
            background: #1a24ad;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            color: #fff;
        }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: rgba(0,0,0,0.3); padding: 8px; text-align: center; color: #ccc; }
        td { padding: 8px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .team-name { text-align: left; display: flex; align-items: center; gap: 5px; }
        .team-logo { width: 20px; height: 20px; object-fit: contain; }
        .qualified { background: rgba(0, 255, 38, 0.1); border-left: 3px solid #00ff26; } /* Clasificados */
        
        /* --- ESTILOS DE BRACKET / ELIMINATORIAS --- */
        .bracket-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }
        .phase-block {
            width: 100%;
            max-width: 900px;
        }
        .phase-title {
            background: #ffdd00;
            color: #001f5c;
            padding: 10px;
            border-radius: 50px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            display: inline-block;
        }
        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .match-card {
            background: white;
            color: #333;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        .match-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            border-radius: 5px;
        }
        .match-row.winner { background-color: #d4edda; font-weight: bold; }
        .penalties { font-size: 10px; color: #666; margin-left: 5px; }
        .vs-tag { text-align: center; font-size: 10px; color: #ccc; margin: -5px 0; }
    </style>
</head>
<body>

    <h1>🏆 Mundial 2026 - Resumen 🏆</h1>

    <h2>Fase de Grupos</h2>
    <div class="groups-container">
        <?php
        require 'conexion.php';

        // Consulta Maestra: Calcula Puntos, DG, GF para todos los equipos
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

        // Agrupar por Letra de Grupo (A, B, C...)
        $grupos = [];
        foreach ($equipos as $eq) {
            $grupos[$eq['Grupo']][] = $eq;
        }

        // Renderizar cada Grupo
        foreach ($grupos as $letra => $listaEquipos) {
            echo "<div class='group-card'>";
            echo "<div class='group-header'>Grupo $letra</div>";
            echo "<table><thead><tr><th>Equipo</th><th>PJ</th><th>DG</th><th>Pts</th></tr></thead><tbody>";
            
            foreach ($listaEquipos as $idx => $eq) {
                // Los 2 primeros pasan seguro (verde), los 3ros dependen (amarillo/logica externa)
                // Por simplicidad visual marcamos los top 2
                $clase = ($idx < 2) ? 'qualified' : ''; 
                
                echo "<tr class='$clase'>";
                echo "<td class='team-name'><img src='../{$eq['Escudo']}' class='team-logo'> {$eq['Nombre_Equipo']}</td>";
                echo "<td>{$eq['PJ']}</td>";
                echo "<td>{$eq['DG']}</td>";
                echo "<td><strong>{$eq['Pts']}</strong></td>";
                echo "</tr>";
            }
            echo "</tbody></table></div>";
        }
        ?>
    </div>

    <h2>Fase Final</h2>
    <div class="bracket-container">
        <?php
        // Obtener partidos de eliminatoria ordenados por ID (para mantener orden de creación)
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

        // Agrupar por Fase
        $fases = [
            'Dieciseisavos de final' => [],
            'Octavos de final' => [],
            'Cuartos de final' => [],
            'Semifinal' => [],
            'Final' => [],
            'Tercer Puesto' => []
        ];

        foreach ($partidosElim as $pe) {
            if (isset($fases[$pe['Fase']])) {
                $fases[$pe['Fase']][] = $pe;
            }
        }

        // Renderizar cada Fase
        foreach ($fases as $nombreFase => $matches) {
            if (empty($matches)) continue; // Si no hay partidos en esa fase (ej. Octavos aun no se juegan), saltar

            echo "<div class='phase-block'>";
            echo "<div style='text-align:center'><span class='phase-title'>$nombreFase</span></div>";
            echo "<div class='matches-grid'>";

            foreach ($matches as $m) {
                $gl = $m['Goles_Local'] ?? '-';
                $gv = $m['Goles_Visitante'] ?? '-';
                
                // Determinar ganador para resaltar (negritas y fondo verde suave)
                $winL = false; $winV = false;
                if (is_numeric($gl)) {
                    if ($gl > $gv) $winL = true;
                    elseif ($gv > $gl) $winV = true;
                    elseif ($m['Penales_Local'] !== null) {
                        if ($m['Penales_Local'] > $m['Penales_Visitante']) $winL = true;
                        else $winV = true;
                    }
                }

                // Texto de penales
                $penTxtL = ($m['Penales_Local'] !== null) ? "({$m['Penales_Local']})" : "";
                $penTxtV = ($m['Penales_Visitante'] !== null) ? "({$m['Penales_Visitante']})" : "";

                echo "<div class='match-card'>";
                echo "<div class='match-row " . ($winL ? 'winner' : '') . "'><span>{$m['Local']}</span> <span>$gl <span class='penalties'>$penTxtL</span></span></div>";
                echo "<div class='vs-tag'>vs</div>";
                echo "<div class='match-row " . ($winV ? 'winner' : '') . "'><span>{$m['Visitante']}</span> <span>$gv <span class='penalties'>$penTxtV</span></span></div>";
                echo "</div>";
            }
            echo "</div></div>";
        }
        
        if (empty($partidosElim)) {
            echo "<p style='text-align:center; color:#ccc;'>Aún no se ha jugado la fase de grupos o generado el cuadro.</p>";
        }
        ?>
    </div>

</body>
</html>