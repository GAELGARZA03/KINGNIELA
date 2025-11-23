<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Listar coronas ---
if ($method === 'GET') {
    try {
        // 1. Ver cuál está activa
        $stmtActive = $pdo->prepare("SELECT Id_Corona FROM CORONA_ACTIVA WHERE Id_Usuario = ?");
        $stmtActive->execute([$userId]);
        $activeId = $stmtActive->fetchColumn();

        // 2. Traer todas las coronas
        $stmtAll = $pdo->query("SELECT * FROM CORONA");
        $allCrowns = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        // 3. Ver cuáles tiene desbloqueadas el usuario
        $stmtOwned = $pdo->prepare("SELECT Id_Corona FROM USUARIO_CORONAS WHERE Id_Usuario = ?");
        $stmtOwned->execute([$userId]);
        $ownedIds = $stmtOwned->fetchAll(PDO::FETCH_COLUMN);

        // 4. Armar respuesta
        $response = [];
        foreach ($allCrowns as $c) {
            $status = 'locked';
            
            if (in_array($c['Id_Corona'], $ownedIds)) {
                $status = 'available';
            }
            
            if ($c['Id_Corona'] == $activeId) {
                $status = 'active';
            }

            $response[] = [
                'id' => $c['Id_Corona'],
                'nombre' => $c['Nombre_Corona'],
                'descripcion' => $c['Descripcion_Corona'],
                'imagen' => $c['Imagen_Url'],
                'estado' => $status
            ];
        }

        echo json_encode(['success' => true, 'data' => $response]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// --- POST: Activar o Desactivar ---
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'activate'; // 'activate' o 'deactivate'
    $crownId = $data['crown_id'] ?? null;

    try {
        $pdo->beginTransaction();

        // CASO 1: DESACTIVAR (Quitar corona)
        if ($action === 'deactivate') {
            $stmtDel = $pdo->prepare("DELETE FROM CORONA_ACTIVA WHERE Id_Usuario = ?");
            $stmtDel->execute([$userId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Corona quitada']);
            exit;
        }

        // CASO 2: ACTIVAR
        if (!$crownId) {
            echo json_encode(['success' => false, 'message' => 'Falta ID corona']);
            exit;
        }

        // Verificar propiedad
        $stmtCheck = $pdo->prepare("SELECT 1 FROM USUARIO_CORONAS WHERE Id_Usuario = ? AND Id_Corona = ?");
        $stmtCheck->execute([$userId, $crownId]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'No tienes esta corona desbloqueada']);
            exit;
        }

        // Borrar anterior e insertar nueva
        $stmtDel = $pdo->prepare("DELETE FROM CORONA_ACTIVA WHERE Id_Usuario = ?");
        $stmtDel->execute([$userId]);

        $stmtIns = $pdo->prepare("INSERT INTO CORONA_ACTIVA (Id_Corona, Id_Usuario) VALUES (?, ?)");
        $stmtIns->execute([$crownId, $userId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Corona activada']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>