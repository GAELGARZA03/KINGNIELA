<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

// --- HELPERS DE ENCRIPTACIÓN ---
define('ENC_KEY', 'KingnielaSecretKey2026');
define('ENC_IV', '1234567891011121');
function encryptText($text) { return openssl_encrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV); }
function decryptText($text) { return openssl_decrypt($text, 'AES-128-CTR', ENC_KEY, 0, ENC_IV); }

function broadcastGroupSocket($quinielaId, $message) {
    $data = ['quiniela_id' => $quinielaId, 'message' => $message];
    $payload = json_encode($data);
    $ch = curl_init('http://localhost:3000/broadcast-group');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);
    curl_exec($ch); curl_close($ch);
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = $_SESSION['user_id'] ?? 0;

if ($method === 'POST') {
    // ENVIAR MENSAJE GRUPO
    $idQuiniela = $_POST['id_quiniela'] ?? 0;
    $contenido = $_POST['content'] ?? '';
    $tipo = 'texto'; // Por ahora solo texto

    if (!$idQuiniela || !$userId) { echo json_encode(['success'=>false]); exit; }

    try {
        // 1. Verificar Preferencia Emisor
        $stmtP = $pdo->prepare("SELECT Preferencias_Encriptacion FROM USUARIO WHERE Id_Usuario = ?");
        $stmtP->execute([$userId]);
        $pref = $stmtP->fetchColumn();
        
        $encriptar = ($pref == 1) ? 1 : 0;
        $contenidoFinal = ($encriptar == 1) ? encryptText($contenido) : $contenido;

        // 2. Insertar
        $sql = "INSERT INTO MENSAJES_QUINIELA (Id_Quiniela, Id_Emisor, Contenido, Tipo, Esta_Encriptado) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idQuiniela, $userId, $contenidoFinal, $tipo, $encriptar]);

        // 3. Preparar respuesta para Socket (Datos de usuario necesarios)
        $lastId = $pdo->lastInsertId();
        $stmtGet = $pdo->prepare("
            SELECT m.*, u.Nombre_Usuario, u.Avatar, c.Imagen_Url as Corona
            FROM MENSAJES_QUINIELA m
            JOIN USUARIO u ON m.Id_Emisor = u.Id_Usuario
            LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
            LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
            WHERE m.Id_MensajeGrupo = ?
        ");
        $stmtGet->execute([$lastId]);
        $msg = $stmtGet->fetch(PDO::FETCH_ASSOC);

        // Desencriptar para el broadcast
        if ($msg['Esta_Encriptado'] == 1) $msg['Contenido'] = decryptText($msg['Contenido']);

        broadcastGroupSocket($idQuiniela, $msg);
        echo json_encode(['success' => true]);

    } catch (Exception $e) { echo json_encode(['success'=>false, 'm'=>$e->getMessage()]); }

} else {
    // GET: OBTENER HISTORIAL
    $idQuiniela = $_GET['id_quiniela'] ?? 0;
    
    $sql = "
        SELECT m.*, u.Nombre_Usuario, u.Avatar, c.Imagen_Url as Corona
        FROM MENSAJES_QUINIELA m
        JOIN USUARIO u ON m.Id_Emisor = u.Id_Usuario
        LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
        LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
        WHERE m.Id_Quiniela = ?
        ORDER BY m.Fecha_Envio ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idQuiniela]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($msgs as &$m) {
        if ($m['Esta_Encriptado'] == 1) $m['Contenido'] = decryptText($m['Contenido']);
    }
    
    echo json_encode($msgs);
}
?>