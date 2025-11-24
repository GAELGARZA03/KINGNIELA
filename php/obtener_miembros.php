<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$idQuiniela = $_GET['id_quiniela'] ?? 0;
$currentUserId = $_SESSION['user_id'] ?? 0;

if (!$idQuiniela) { echo json_encode(['success' => false, 'message' => 'Falta ID']); exit; }

try {
    // Usamos nombres de tablas en minúsculas para evitar errores de case-sensitivity
    $sql = "
        SELECT u.Id_Usuario, u.Nombre_Usuario, u.Avatar
        FROM quiniela_integrantes qi
        JOIN usuario u ON qi.Id_Usuario = u.Id_Usuario
        WHERE qi.Id_Quiniela = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idQuiniela]);
    $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marcar al usuario actual (Tú)
    foreach ($miembros as &$m) {
        $m['es_propio'] = ($m['Id_Usuario'] == $currentUserId);
    }

    echo json_encode(['success' => true, 'miembros' => $miembros]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
}
?>