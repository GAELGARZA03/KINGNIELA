<?php
header('Content-Type: application/json');
require 'conexion.php';
require 'crown_helper.php'; // Asegúrate de que este helper exista, si no, coméntalo
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$nombre = $input['nombre'] ?? '';
$tipo = $input['tipo'] ?? 'clasico';
$rawDificultad = $input['dificultad'] ?? 'Aficionado';
$amigos = $input['amigos'] ?? [];

if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

// --- LÓGICA DE DIFICULTAD CORREGIDA ---
// 1. Mapa de alias (si el frontend manda "facil", lo convertimos)
$mapa = [
    'facil' => 'Aficionado',
    'medio' => 'Profesional',
    'dificil' => 'Leyenda'
];

// 2. Normalizamos a minúsculas para buscar en el mapa
$key = strtolower($rawDificultad);

// 3. Decisión final
if (isset($mapa[$key])) {
    // Si viene "facil", "medio", etc.
    $dificultadFinal = $mapa[$key];
} elseif (in_array($rawDificultad, ['Aficionado', 'Profesional', 'Leyenda'])) {
    // Si ya viene correcto ("Leyenda", "Aficionado") lo usamos directo
    $dificultadFinal = $rawDificultad;
} else {
    // Default de seguridad
    $dificultadFinal = 'Aficionado';
}

try {
    $pdo->beginTransaction();

    // 1. Generar Código
    $codigo = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    // 2. Insertar Quiniela (TABLAS EN MAYÚSCULAS como en tu versión previa)
    $stmt = $pdo->prepare("INSERT INTO QUINIELA (Nombre_Quiniela, Tipo_Quiniela, Codigo_Acceso, Id_Creador, Foto_Grupo) VALUES (?, ?, ?, ?, 'Imagenes/mundial_2026.png')");
    $stmt->execute([$nombre, $tipo, $codigo, $userId]);
    $idQuiniela = $pdo->lastInsertId();

    // 3. Insertar Creador en Integrantes
    $stmtInt = $pdo->prepare("INSERT INTO QUINIELA_INTEGRANTES (Id_Quiniela, Id_Usuario) VALUES (?, ?)");
    $stmtInt->execute([$idQuiniela, $userId]);

    // 4. Insertar Amigos
    // Filtramos duplicados por si acaso
    $amigos = array_unique($amigos);
    foreach ($amigos as $idAmigo) {
        // Evitar insertarse a sí mismo de nuevo
        if(intval($idAmigo) !== intval($userId)){
            $stmtInt->execute([$idQuiniela, $idAmigo]);
        }
    }

    // 5. Configuración Específica
    if ($tipo === 'kingniela') {
        $stmtK = $pdo->prepare("INSERT INTO QUINIELA_K (Id_Quiniela, Dificultad) VALUES (?, ?)");
        $stmtK->execute([$idQuiniela, $dificultadFinal]);
    } else {
        $stmtF = $pdo->prepare("INSERT INTO QUINIELA_F (Id_Quiniela, Presupuesto_Inicial) VALUES (?, 100.00)");
        $stmtF->execute([$idQuiniela]);
    }

    // Logro (Si tienes la función)
    if(function_exists('desbloquearCorona')) {
        desbloquearCorona($pdo, $userId, 'El comienzo');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'codigo' => $codigo]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>