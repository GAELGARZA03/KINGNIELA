<?php
header('Content-Type: application/json');
require 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// --- ENVIAR MENSAJE (POST) ---
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $remitente = $_POST['sender_id'] ?? 0;
    $destinatario = $_POST['receiver_id'] ?? 0;
    
    // Determinar contenido y tipo
    $contenido = '';
    $tipo = 'texto';
    $urlArchivo = null;
    
    // Verificar si es archivo
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tipo = 'archivo'; // Por defecto
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        $uploadDir = '../uploads/'; // Asegúrate de crear esta carpeta en tu proyecto
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (move_uploaded_file($fileTmp, $uploadDir . $fileName)) {
            $urlArchivo = 'uploads/' . $fileName;
            $contenido = $_FILES['file']['name']; // Nombre original para mostrar
            
            // Detectar si es imagen o audio
            $mime = mime_content_type($uploadDir . $fileName);
            if (strpos($mime, 'image') !== false) $tipo = 'imagen';
            if (strpos($mime, 'audio') !== false) $tipo = 'audio';
        }
    } else {
        // Es texto o ubicación
        $contenido = $_POST['content'] ?? '';
        $tipo = $_POST['tipo'] ?? 'texto';
    }

    try {
        // Aquí activamos la bandera de encriptado por defecto como pediste
        $estaEncriptado = 1; 

        // Insertar en MENSAJE
        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Url_Archivo, Esta_Encriptado) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remitente, $destinatario, $contenido, $tipo, $urlArchivo, $estaEncriptado]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- OBTENER MENSAJES (GET) ---
else {
    $sender = $_GET['sender_id'];
    $receiver = $_GET['receiver_id'];
    
    try {
        $sql = "SELECT Id_Mensaje, Id_Remitente, Id_Destinatario, Contenido, Tipo, Url_Archivo, Fecha_Envio, Leido 
                FROM MENSAJE 
                WHERE (Id_Remitente = ? AND Id_Destinatario = ?) 
                   OR (Id_Remitente = ? AND Id_Destinatario = ?)
                ORDER BY Fecha_Envio ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sender, $receiver, $receiver, $sender]);
        $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($mensajes);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>