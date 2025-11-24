<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$codigo = $data['codigo'] ?? '';

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Escribe un código']);
    exit;
}

try {
    // 1. Buscar la quiniela por código
    $stmtQ = $pdo->prepare("SELECT Id_Quiniela, Nombre_Quiniela FROM QUINIELA WHERE Codigo_Acceso = ?");
    $stmtQ->execute([$codigo]);
    $quiniela = $stmtQ->fetch(PDO::FETCH_ASSOC);

    if (!$quiniela) {
        echo json_encode(['success' => false, 'message' => 'Código inválido. No existe esa quiniela.']);
        exit;
    }

    $idQuiniela = $quiniela['Id_Quiniela'];

    // 2. Verificar si ya soy miembro
    $stmtCheck = $pdo->prepare("SELECT 1 FROM QUINIELA_INTEGRANTES WHERE Id_Quiniela = ? AND Id_Usuario = ?");
    $stmtCheck->execute([$idQuiniela, $userId]);
    
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya eres miembro de este grupo.']);
        exit;
    }

    // 3. Unirse al grupo
    $stmtJoin = $pdo->prepare("INSERT INTO QUINIELA_INTEGRANTES (Id_Quiniela, Id_Usuario) VALUES (?, ?)");
    $stmtJoin->execute([$idQuiniela, $userId]);

    echo json_encode(['success' => true, 'message' => '¡Te has unido a ' . $quiniela['Nombre_Quiniela'] . '!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>