<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$idQuiniela = $_GET['id_quiniela'] ?? 0;
$currentUserId = $_SESSION['user_id'] ?? 0;

if (!$idQuiniela) { echo json_encode(['success' => false, 'message' => 'Falta ID']); exit; }

try {
    $sql = "
        SELECT u.Id_Usuario, u.Nombre_Usuario, u.Avatar
        FROM QUINIELA_INTEGRANTES qi
        JOIN USUARIO u ON qi.Id_Usuario = u.Id_Usuario
        WHERE qi.Id_Quiniela = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idQuiniela]);
    $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($miembros as &$m) {
        $m['es_propio'] = ($m['Id_Usuario'] == $currentUserId);
    }

    // Si el array está vacío, avisar
    if (empty($miembros)) {
        // Opcional: Devolver mensaje de depuración
        echo json_encode(['success' => true, 'miembros' => [], 'debug' => 'No se encontraron miembros en la tabla QUINIELA_INTEGRANTES para este ID.']);
    } else {
        echo json_encode(['success' => true, 'miembros' => $miembros]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>