<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

// --- ZONA DE DEPURACIÓN ---
$logData = "--- Intento de Actualización: " . date('Y-m-d H:i:s') . " ---\n";
$logData .= "POST Data: " . print_r($_POST, true);
$logData .= "FILES Data: " . print_r($_FILES, true);
file_put_contents('debug_log.txt', $logData, FILE_APPEND);
// ---------------------------

$userId = $_SESSION['user_id'] ?? 0;
$idQuiniela = $_POST['id_quiniela'] ?? 0;
$nuevoNombre = $_POST['nombre'] ?? '';

if (!$userId || !$idQuiniela || empty($nuevoNombre)) {
    echo json_encode(['success' => false, 'message' => 'Datos faltantes (ID o Nombre). Revisa el log.']); 
    exit;
}

try {
    // 1. Procesar Imagen
    $fotoPath = null;
    if (isset($_FILES['foto_grupo']) && $_FILES['foto_grupo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto_grupo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $newName = "group_" . $idQuiniela . "_" . time() . "." . $ext;
            $targetDir = "../uploads/groups/";
            
            if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
            
            if (move_uploaded_file($_FILES['foto_grupo']['tmp_name'], $targetDir . $newName)) {
                $fotoPath = "uploads/groups/" . $newName;
            }
        }
    }

    // 2. Preparar Consulta
    $sql = "UPDATE QUINIELA SET Nombre_Quiniela = ?";
    $params = [$nuevoNombre];

    if ($fotoPath) {
        $sql .= ", Foto_Grupo = ?";
        $params[] = $fotoPath;
    }

    $sql .= " WHERE Id_Quiniela = ?";
    $params[] = $idQuiniela;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // 3. VERIFICACIÓN REAL
    // rowCount() nos dice cuántas filas se tocaron.
    // Nota: Si el nombre es igual al anterior, rowCount será 0, pero no es un error.
    $filasAfectadas = $stmt->rowCount();

    echo json_encode([
        'success' => true, 
        'message' => 'Proceso completado',
        'filas_afectadas' => $filasAfectadas, // Para ver si realmente hizo algo
        'foto' => $fotoPath
    ]);

} catch (Exception $e) {
    file_put_contents('debug_log.txt', "Error SQL: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
}
?>