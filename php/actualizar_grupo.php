<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

// 1. Recibir datos
$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = isset($_POST['id_quiniela']) ? intval($_POST['id_quiniela']) : 0;
$nuevoNombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

// 2. Validación básica
if (!$userId || !$idQuiniela || empty($nuevoNombre)) {
    echo json_encode(['success' => false, 'message' => 'Datos vacíos o sesión expirada']);
    exit;
}

try {
    $fotoPath = null;
    $sql = "";
    $params = [];

    // 3. Lógica de Foto (Opcional)
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

    // 4. Construcción de Query Segura
    if ($fotoPath) {
        $sql = "UPDATE QUINIELA SET Nombre_Quiniela = ?, Foto_Grupo = ? WHERE Id_Quiniela = ?";
        $params = [$nuevoNombre, $fotoPath, $idQuiniela];
    } else {
        $sql = "UPDATE QUINIELA SET Nombre_Quiniela = ? WHERE Id_Quiniela = ?";
        $params = [$nuevoNombre, $idQuiniela];
    }

    // 5. Ejecución
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Actualizado correctamente',
            'foto' => $fotoPath,
            'nombre' => $nuevoNombre
        ]);
    } else {
        // Capturar error exacto de SQL
        $errorInfo = $stmt->errorInfo();
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $errorInfo[2]]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Excepción: ' . $e->getMessage()]);
}
?>