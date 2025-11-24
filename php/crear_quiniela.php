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

$nombre = $data['nombre'] ?? '';
$tipo = $data['tipo'] ?? '';
$dificultad = $data['dificultad'] ?? null; // Solo para Kingniela
$amigos = $data['amigos'] ?? []; // Array de IDs de amigos

if (empty($nombre) || empty($tipo)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Generar Código Único (6 caracteres)
    $codigo = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // 2. Insertar Quiniela Base
    $sql = "INSERT INTO QUINIELA (Nombre_Quiniela, Tipo_Quiniela, Codigo_Acceso, Id_Creador, Foto_Grupo) 
            VALUES (?, ?, ?, ?, 'Imagenes/mundial_2026.png')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $tipo, $codigo, $userId]);
    $idQuiniela = $pdo->lastInsertId();

    // 3. Insertar Detalles según Tipo
    if ($tipo === 'kingniela') {
        $stmtK = $pdo->prepare("INSERT INTO QUINIELA_K (Id_Quiniela, Dificultad) VALUES (?, ?)");
        // Mapear dificultad a los valores ENUM de la BD si es necesario, o pasar directo
        // Asumimos que el frontend manda: 'Aficionado', 'Profesional', 'Leyenda'
        // O ajustamos si manda 'facil', 'medio', 'dificil'
        $difMap = ['facil'=>'Aficionado', 'medio'=>'Profesional', 'dificil'=>'Leyenda'];
        $difVal = $difMap[$dificultad] ?? 'Aficionado';
        $stmtK->execute([$idQuiniela, $difVal]);
    } else {
        // Fantasy
        $stmtF = $pdo->prepare("INSERT INTO QUINIELA_F (Id_Quiniela, Presupuesto_Inicial) VALUES (?, 100.00)");
        $stmtF->execute([$idQuiniela]);
    }

    // 4. Agregar Integrantes (Creador + Amigos)
    $integrantes = array_merge([$userId], $amigos);
    $integrantes = array_unique($integrantes); // Evitar duplicados

    $stmtInt = $pdo->prepare("INSERT INTO QUINIELA_INTEGRANTES (Id_Quiniela, Id_Usuario) VALUES (?, ?)");
    foreach ($integrantes as $idUser) {
        $stmtInt->execute([$idQuiniela, $idUser]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Quiniela creada', 'codigo' => $codigo]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>