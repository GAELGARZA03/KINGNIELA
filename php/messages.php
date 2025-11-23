<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- FUNCIÓN SOCKET (IGUAL QUE ANTES) ---
function broadcastToSocket($message) {
    $data = [
        'sender_id'   => intval($message['Id_Remitente']),
        'receiver_id' => intval($message['Id_Destinatario']),
        'message'     => $message 
    ];
    $payload = json_encode($data);
    $ch = curl_init('http://localhost:3000/broadcast');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_exec($ch);
    curl_close($ch);
}

function broadcastReadStatus($reader_id, $original_sender_id) {
    $data = ['reader_id' => $reader_id, 'original_sender_id' => $original_sender_id];
    $payload = json_encode($data);
    $ch = curl_init('http://localhost:3000/broadcast-read');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    curl_exec($ch);
    curl_close($ch);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $remitente = $_POST['sender_id'] ?? 0;
    $destinatario = $_POST['receiver_id'] ?? 0;
    
    $contenido = '';
    $tipo = 'texto';
    $urlArchivo = null; // Se mantiene por compatibilidad, pero Contenido tendrá lo importante
    
    // --- LOGICA DE ARCHIVOS MODIFICADA ---
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        $uploadDir = '../uploads/'; 
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (move_uploaded_file($fileTmp, $uploadDir . $fileName)) {
            $webPath = 'uploads/' . $fileName;
            
            // AQUÍ ESTÁ EL CAMBIO QUE PEDISTE:
            // Guardamos la RUTA en el contenido, no el nombre.
            $contenido = $webPath; 
            $urlArchivo = $webPath; // También lo dejamos aquí por si acaso

            // Detectar tipo
            $tipo = 'archivo'; // Default
            $mime = mime_content_type($uploadDir . $fileName);
            if (strpos($mime, 'image') !== false) $tipo = 'imagen';
            else if (strpos($mime, 'audio') !== false) $tipo = 'audio';
            else if (strpos($mime, 'video') !== false) $tipo = 'video';
        }
    } else {
        $contenido = $_POST['content'] ?? '';
        $tipo = $_POST['tipo'] ?? 'texto';
    }

    try {
        $estaEncriptado = 1; 
        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Url_Archivo, Esta_Encriptado) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remitente, $destinatario, $contenido, $tipo, $urlArchivo, $estaEncriptado]);
        
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $newMessage = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($newMessage) broadcastToSocket($newMessage);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // GET (Obtener mensajes) - Sin cambios
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    try {
        $updateSql = "UPDATE MENSAJE SET Leido = 1 WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
        $stmtUpdate = $pdo->prepare($updateSql);
        $stmtUpdate->execute([$receiver, $sender]);

        if ($stmtUpdate->rowCount() > 0) broadcastReadStatus($sender, $receiver);

        $sql = "SELECT * FROM MENSAJE 
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