<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- FUNCIÓN PARA ENVIAR AL SOCKET (Node.js) ---
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

// --- FUNCIÓN PARA NOTIFICAR LECTURA ---
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

// --- ENVIAR MENSAJE (POST) ---
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $remitente = $_POST['sender_id'] ?? 0;
    $destinatario = $_POST['receiver_id'] ?? 0;
    
    // Variables principales
    $contenido = '';
    $tipo = 'texto';
    
    // 1. SI ES UN ARCHIVO (IMAGEN/AUDIO)
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        $uploadDir = '../uploads/'; 
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (move_uploaded_file($fileTmp, $uploadDir . $fileName)) {
            // AQUÍ ESTÁ LA CLAVE: Guardamos la ruta DIRECTAMENTE en 'Contenido'
            $contenido = 'uploads/' . $fileName;
            
            // Detectar tipo MIME
            $mime = mime_content_type($uploadDir . $fileName);
            if (strpos($mime, 'image') !== false) $tipo = 'imagen';
            else if (strpos($mime, 'audio') !== false) $tipo = 'audio';
            else $tipo = 'archivo'; // PDF u otros
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al mover archivo']);
            exit;
        }
    } 
    // 2. SI ES TEXTO O UBICACIÓN
    else {
        $contenido = $_POST['content'] ?? '';
        $tipo = $_POST['tipo'] ?? 'texto';
    }

    if ($contenido === '') {
        echo json_encode(['success' => false, 'message' => 'Contenido vacío']);
        exit;
    }

    try {
        $estaEncriptado = 1; 

        // INSERTAR EN LA BASE DE DATOS (Solo usamos 'Contenido' y 'Tipo')
        // NO usamos Url_Archivo
        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Esta_Encriptado) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remitente, $destinatario, $contenido, $tipo, $estaEncriptado]);
        
        // Recuperar el mensaje para enviarlo al socket
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $newMessage = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($newMessage) {
            broadcastToSocket($newMessage);
        }

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
}

// --- OBTENER MENSAJES (GET) ---
else {
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    
    try {
        // Marcar como leídos
        $updateSql = "UPDATE MENSAJE SET Leido = 1 WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
        $stmtUpdate = $pdo->prepare($updateSql);
        $stmtUpdate->execute([$receiver, $sender]);

        if ($stmtUpdate->rowCount() > 0) {
            broadcastReadStatus($sender, $receiver);
        }

        // Obtener historial
        // NO seleccionamos Url_Archivo porque ya no la usamos/queremos
        $sql = "SELECT Id_Mensaje, Id_Remitente, Id_Destinatario, Contenido, Tipo, Fecha_Envio, Leido 
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