<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- HELPERS ENCRIPTACIÓN ---
define('ENC_KEY', 'KingnielaSecretKey2026');
define('ENC_IV', '1234567891011121');
function encryptText($text) { return openssl_encrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV); }
function decryptText($text) { return openssl_decrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV); }

function broadcastToSocket($message) {
    $data = ['sender_id' => intval($message['Id_Remitente']), 'receiver_id' => intval($message['Id_Destinatario']), 'message' => $message];
    $payload = json_encode($data);
    $ch = curl_init('http://localhost:3000/broadcast');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_exec($ch); curl_close($ch);
}

function broadcastReadStatus($reader_id, $original_sender_id) {
    $data = ['reader_id' => intval($reader_id), 'original_sender_id' => intval($original_sender_id)];
    $payload = json_encode($data);
    $ch = curl_init('http://localhost:3000/broadcast-read');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_exec($ch); curl_close($ch);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- CASO 1: MARCAR COMO LEÍDO (NUEVO) ---
    if ($action === 'mark_read') {
        $reader = $_POST['reader_id'] ?? 0;
        $sender = $_POST['sender_id'] ?? 0; // El que envió los mensajes que ahora estoy leyendo

        if (!$reader || !$sender) { echo json_encode(['success'=>false]); exit; }

        try {
            // Marcar como leídos todos los mensajes recibidos de ese sender
            $sql = "UPDATE MENSAJE SET Leido = 1 WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sender, $reader]); // Sender es el remitente original, Reader soy yo

            if ($stmt->rowCount() > 0) {
                broadcastReadStatus($reader, $sender); // Avisar al sender que ya leí
                echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
            } else {
                echo json_encode(['success' => true, 'updated' => 0]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // --- CASO 2: ENVIAR MENSAJE ---
    $sender = $_POST['sender_id'] ?? 0;
    $receiver = $_POST['receiver_id'] ?? 0;
    $contenido = $_POST['content'] ?? '';
    $tipo = $_POST['tipo'] ?? 'texto';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
        move_uploaded_file($fileTmp, '../uploads/' . $fileName);
        $contenido = 'uploads/' . $fileName;
        $mime = mime_content_type('../uploads/' . $fileName);
        if (strpos($mime, 'image') !== false) $tipo = 'imagen';
        else if (strpos($mime, 'audio') !== false || strpos($mime, 'video') !== false) $tipo = 'audio';
        else $tipo = 'archivo';
    }

    try {
        $stmtPref = $pdo->prepare("SELECT Preferencias_Encriptacion FROM USUARIO WHERE Id_Usuario IN (?, ?)");
        $stmtPref->execute([$sender, $receiver]);
        $prefs = $stmtPref->fetchAll(PDO::FETCH_COLUMN);
        $encriptar = (in_array(1, $prefs)) ? 1 : 0;

        $contenidoFinal = ($encriptar == 1 && $tipo == 'texto') ? encryptText($contenido) : $contenido;

        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Esta_Encriptado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sender, $receiver, $contenidoFinal, $tipo, $encriptar]);
        
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $msg = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($msg['Esta_Encriptado'] == 1 && $msg['Tipo'] == 'texto') {
            $msg['Contenido'] = decryptText($msg['Contenido']);
        }

        broadcastToSocket($msg);
        echo json_encode(['success' => true, 'message_data' => $msg]);

    } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }

} else {
    // --- GET: CARGAR HISTORIAL ---
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    
    // Al cargar historial, aprovechamos para marcar como leídos
    $updateSql = "UPDATE MENSAJE SET Leido = 1 WHERE Id_Remitente = ? AND Id_Destinatario = ? AND Leido = 0";
    $stmtUpdate = $pdo->prepare($updateSql);
    $stmtUpdate->execute([$receiver, $sender]); // Ojo con el orden: Receiver es el OTRO (Remitente)
    
    if ($stmtUpdate->rowCount() > 0) broadcastReadStatus($sender, $receiver);

    $sql = "SELECT * FROM MENSAJE WHERE (Id_Remitente = ? AND Id_Destinatario = ?) OR (Id_Remitente = ? AND Id_Destinatario = ?) ORDER BY Fecha_Envio ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sender, $receiver, $receiver, $sender]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($msgs as &$m) {
        if ($m['Esta_Encriptado'] == 1 && $m['Tipo'] == 'texto') {
            $m['Contenido'] = decryptText($m['Contenido']);
        }
    }
    echo json_encode($msgs);
}
?>