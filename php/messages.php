<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- FUNCIÓN SOCKET (Igual que antes) ---
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
    
    // 1. ARCHIVOS
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        $uploadDir = '../uploads/'; 
        
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        if (move_uploaded_file($fileTmp, $uploadDir . $fileName)) {
            $contenido = 'uploads/' . $fileName;
            
            $mime = mime_content_type($uploadDir . $fileName);
            if (strpos($mime, 'image') !== false) $tipo = 'imagen';
            else if (strpos($mime, 'audio') !== false) $tipo = 'audio';
            else $tipo = 'archivo';
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al mover archivo']);
            exit;
        }
    } 
    // 2. TEXTO
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

        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Esta_Encriptado) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$remitente, $destinatario, $contenido, $tipo, $estaEncriptado]);
        
        // Recuperar mensaje para devolverlo al JS
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $newMessage = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($newMessage) {
            broadcastToSocket($newMessage);
        }

        // CAMBIO CLAVE: Devolvemos el mensaje completo en 'data'
        echo json_encode(['success' => true, 'message_data' => $newMessage]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
} else {
    // GET (Sin cambios mayores)
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    try {
        $updateSql = "UPDATE MENSAJE SET Leido = 1 WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
        $stmtUpdate = $pdo->prepare($updateSql);
        $stmtUpdate->execute([$receiver, $sender]);

        if ($stmtUpdate->rowCount() > 0) broadcastReadStatus($sender, $receiver);

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