<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

// --- DEBUG: Ver qué llega ---
$log = "UPDATE Request: " . print_r($_POST, true);
file_put_contents('debug_update.txt', $log, FILE_APPEND);

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_POST['id_quiniela'] ?? 0;
$nuevoNombre = $_POST['nombre'] ?? '';

if (!$userId || !$idQuiniela || empty($nuevoNombre)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']); exit;
}

try {
    // 1. Procesar Foto (Si se subió)
    $fotoPath = null;
    if (isset($_FILES['foto_grupo']) && $_FILES['foto_grupo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_grupo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $newName = "group_" . $idQuiniela . "_" . time() . "." . $ext;
            $target = "../uploads/groups/" . $newName;
            if (!is_dir("../uploads/groups/")) mkdir("../uploads/groups/", 0777, true);
            if (move_uploaded_file($_FILES['foto_grupo']['tmp_name'], $target)) {
                $fotoPath = "uploads/groups/" . $newName;
            }
        }
    }

    // 2. Actualizar Base de Datos (Tabla 'quiniela' en minúsculas)
    $sql = "UPDATE quiniela SET Nombre_Quiniela = ?";
    $params = [$nuevoNombre];

    if ($fotoPath) {
        $sql .= ", Foto_Grupo = ?";
        $params[] = $fotoPath;
    }

    $sql .= " WHERE Id_Quiniela = ?";
    $params[] = $idQuiniela;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Grupo actualizado', 
            'foto' => $fotoPath,
            'debug_info' => 'Filas afectadas: ' . $stmt->rowCount()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar UPDATE en BD']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
}
?>