<?php
header('Content-Type: application/json');
require 'conexion.php';

// --- HELPERS DE ENCRIPTACIÓN (Simple AES-128) ---
define('ENC_KEY', 'KingnielaSecretKey2026');
define('ENC_IV', '1234567891011121'); // 16 bytes fijos para simplicidad

function encryptText($text) {
    return openssl_encrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV);
}
function decryptText($text) {
    return openssl_decrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV);
}

// Función Socket
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender = $_POST['sender_id'] ?? 0;
    $receiver = $_POST['receiver_id'] ?? 0;
    $contenido = $_POST['content'] ?? '';
    $tipo = $_POST['tipo'] ?? 'texto';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // ... (Tu lógica de archivos existente, la omito por brevedad pero mantenla igual) ...
        // Para este ejemplo asumimos texto, si tienes archivos, solo cambia $contenido
        $fileTmp = $_FILES['file']['tmp_name'];
        $fileName = uniqid() . '_' . $_FILES['file']['name'];
        if (!is_dir('../uploads/')) mkdir('../uploads/', 0777, true);
        move_uploaded_file($fileTmp, '../uploads/' . $fileName);
        $contenido = 'uploads/' . $fileName;
        // Detectar tipo...
        $mime = mime_content_type('../uploads/' . $fileName);
        if (strpos($mime, 'image') !== false) $tipo = 'imagen';
        else if (strpos($mime, 'audio') !== false || strpos($mime, 'video') !== false) $tipo = 'audio';
        else $tipo = 'archivo';
    }

    try {
        // 1. VERIFICAR PREFERENCIAS DE ENCRIPTACIÓN
        // "Si usuario A tiene activado... o usuario B... se encripta"
        $stmtPref = $pdo->prepare("SELECT Id_Usuario, Preferencias_Encriptacion FROM USUARIO WHERE Id_Usuario IN (?, ?)");
        $stmtPref->execute([$sender, $receiver]);
        $prefs = $stmtPref->fetchAll(PDO::FETCH_ASSOC);
        
        $encriptar = 0;
        foreach ($prefs as $p) {
            if ($p['Preferencias_Encriptacion'] == 1) {
                $encriptar = 1;
                break; // Con que uno quiera, se encripta
            }
        }

        // 2. ENCRIPTAR SI ES NECESARIO
        $contenidoFinal = $contenido;
        if ($encriptar == 1 && $tipo == 'texto') {
            $contenidoFinal = encryptText($contenido);
        }

        // 3. GUARDAR
        $sql = "INSERT INTO MENSAJE (Id_Remitente, Id_Destinatario, Contenido, Tipo, Esta_Encriptado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sender, $receiver, $contenidoFinal, $tipo, $encriptar]);
        
        // 4. RECUPERAR Y ENVIAR (Desencriptado para el socket instantáneo)
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("SELECT * FROM MENSAJE WHERE Id_Mensaje = ?");
        $stmtGet->execute([$lastId]);
        $msg = $stmtGet->fetch(PDO::FETCH_ASSOC);

        // Para el frontend, enviamos el texto legible
        if ($msg['Esta_Encriptado'] == 1 && $msg['Tipo'] == 'texto') {
            $msg['Contenido'] = decryptText($msg['Contenido']);
        }

        broadcastToSocket($msg);
        echo json_encode(['success' => true, 'message_data' => $msg]);

    } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }

} else {
    // GET
    $sender = $_GET['sender_id'] ?? 0;
    $receiver = $_GET['receiver_id'] ?? 0;
    
    $sql = "SELECT * FROM MENSAJE WHERE (Id_Remitente = ? AND Id_Destinatario = ?) OR (Id_Remitente = ? AND Id_Destinatario = ?) ORDER BY Fecha_Envio ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sender, $receiver, $receiver, $sender]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DESENCRIPTAR AL LEER
    foreach ($msgs as &$m) {
        if ($m['Esta_Encriptado'] == 1 && $m['Tipo'] == 'texto') {
            $m['Contenido'] = decryptText($m['Contenido']);
        }
    }
    echo json_encode($msgs);
}
?>