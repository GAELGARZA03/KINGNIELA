<?php
header('Content-Type: application/json');
require 'conexion.php';

$idQuiniela = $_GET['id_quiniela'] ?? 0;

try {
    // 1. Obtener ID Kingniela
    $stmtK = $pdo->prepare("SELECT Id_Quiniela_K FROM QUINIELA_K WHERE Id_Quiniela = ?");
    $stmtK->execute([$idQuiniela]);
    $idK = $stmtK->fetchColumn();

    // 2. Consulta Desglosada
    // Usamos CASE WHEN para sumar puntos solo si pertenecen a cierta fase
    $sql = "
        SELECT u.Id_Usuario, u.Nombre_Usuario, u.Avatar, 
               c.Imagen_Url as Corona,
               COALESCE(SUM(p.Puntos_Obtenidos), 0) as PuntosTotales,
               COALESCE(SUM(CASE WHEN part.Fase = 'Jornada 1' THEN p.Puntos_Obtenidos ELSE 0 END), 0) as Pts_J1,
               COALESCE(SUM(CASE WHEN part.Fase = 'Jornada 2' THEN p.Puntos_Obtenidos ELSE 0 END), 0) as Pts_J2,
               COALESCE(SUM(CASE WHEN part.Fase = 'Jornada 3' THEN p.Puntos_Obtenidos ELSE 0 END), 0) as Pts_J3,
               COALESCE(SUM(CASE WHEN part.Fase NOT LIKE 'Jornada%' THEN p.Puntos_Obtenidos ELSE 0 END), 0) as Pts_Elim
        FROM QUINIELA_INTEGRANTES qi
        JOIN USUARIO u ON qi.Id_Usuario = u.Id_Usuario
        LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
        LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
        LEFT JOIN PRONOSTICOS p ON p.Id_Usuario = u.Id_Usuario AND p.Id_Quiniela_K = ?
        LEFT JOIN PARTIDO part ON p.Id_Partido = part.Id_Partido
        WHERE qi.Id_Quiniela = ?
        GROUP BY u.Id_Usuario
        ORDER BY PuntosTotales DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idK, $idQuiniela]);
    $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'ranking' => $ranking]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>