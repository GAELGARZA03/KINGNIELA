<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- FUNCIÓN PARA ENVIAR AL SOCKET (Node.js) ---
function broadcastToSocket($message) {
    // Datos que enviaremos a Node.js
    // Node espera recibir sender_id y receiver_id para saber a quién emitir
    $data = [
        'sender_id'   => intval($message['Id_Remitente']),
        'receiver_id' => intval($message['Id_Destinatario']),
        'message'     => $message // Enviamos la fila completa de la BD
    ];

    $payload = json_encode($data);

    // Petición interna al servidor Node (Puerto 3000)
    $ch = curl_init('http://localhost:3000/broadcast');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    
    // Timeout muy corto para no bloquear PHP
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
    
    curl_exec($ch);
    curl_close($ch);
}

// --- FUNCIÓN PARA NOTIFICAR LECTURA ---
function broadcastReadStatus($reader_id, $original_sender_id) {
    $data = [
        'reader_id'          => $reader_id,
        'original_sender_id' => $original_sender_id
    ];
    $payload = json_encode($data);

    $ch = curl_init('http://localhost:3000/broadcast-read');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
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
    
    // Determinar contenido y tipo
    $contenido = '';
    $tipo = 'texto';
    $urlArchivo = null;
    
    // Verificar si es archivo
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tipo = 'archivo'; // Por defecto
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        $uploadDir = '../uploads/'; // Asegúrate de crear esta carpeta
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (move_uploaded_file($fileTmp, $uploadDir . $fileName)) {
            $urlArchivo = 'uploads/' . $fileName; // Ruta relativa para BD
            $contenido = $_FILES['file']['name']; // Nombre original
            
            // Detectar MIME real
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
        $estaEncriptado = 1; 

        // 1. Insertar en MENSAJE
        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Url_Archivo, Esta_Encriptado) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remitente, $destinatario, $contenido, $tipo, $urlArchivo, $estaEncriptado]);
        
        // 2. Recuperar el mensaje completo para enviarlo al Socket
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $newMessage = $stmtGet->fetch(PDO::FETCH_ASSOC);

        // 3. Enviar notificación en tiempo real
        if ($newMessage) {
            broadcastToSocket($newMessage);
        }

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- OBTENER MENSAJES (GET) ---
else {
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    
    try {
        // 1. Marcar como leídos los mensajes que recibí de la otra persona
        $updateSql = "UPDATE MENSAJE SET Leido = 1 
                      WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
        $stmtUpdate = $pdo->prepare($updateSql);
        $stmtUpdate->execute([$receiver, $sender]); // receiver es el "amigo", sender soy "yo" (el que lee)

        if ($stmtUpdate->rowCount() > 0) {
            broadcastReadStatus($sender, $receiver);
        }

        // 2. Obtener historial
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