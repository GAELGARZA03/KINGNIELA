<?php
header('Content-Type: application/json');
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    // --- ENVIAR SOLICITUD (MODIFICADO PARA BUSCAR POR EMAIL/USUARIO) ---
    if ($action === 'add_friend') {
        $id1 = $data['user_id1'];
        $target = $data['user_id2']; // Esto puede ser ID, Correo o Usuario
        
        try {
            $id2 = null;

            // 1. Buscar el ID del usuario destino
            $stmtFind = $pdo->prepare("SELECT Id_Usuario FROM USUARIO WHERE Correo = ? OR Nombre_Usuario = ?");
            $stmtFind->execute([$target, $target]);
            $foundUser = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($foundUser) {
                $id2 = $foundUser['Id_Usuario'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado. Verifica el correo o nombre.']);
                exit;
            }

            // 2. Validaciones
            if ($id1 == $id2) {
                echo json_encode(['success' => false, 'message' => 'No puedes agregarte a ti mismo.']);
                exit;
            }

            // 3. Verificar si ya son amigos o hay solicitud
            $stmt = $pdo->prepare("SELECT Id_Amistad FROM AMISTAD WHERE (Id_Usuario_1 = ? AND Id_Usuario_2 = ?) OR (Id_Usuario_1 = ? AND Id_Usuario_2 = ?)");
            $stmt->execute([$id1, $id2, $id2, $id1]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una amistad o solicitud pendiente.']);
                exit;
            }
            
            // 4. Insertar solicitud
            $stmt = $pdo->prepare("INSERT INTO AMISTAD (Id_Usuario_1, Id_Usuario_2, Estado) VALUES (?, ?, 'pendiente')");
            $stmt->execute([$id1, $id2]);
            echo json_encode(['success' => true, 'message' => 'Solicitud enviada exitosamente.']);
            
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
        }
    }
    
    // --- ACEPTAR SOLICITUD ---
    if ($action === 'accept_friend') {
        $amistadId = $data['friendship_id'];
        try {
            $stmt = $pdo->prepare("UPDATE AMISTAD SET Estado = 'aceptada' WHERE Id_Amistad = ?");
            $stmt->execute([$amistadId]);
            echo json_encode(['success' => true, 'message' => 'Solicitud aceptada']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // --- RECHAZAR / ELIMINAR ---
    if ($action === 'reject_friend' || $action === 'delete_friend') {
        $amistadId = $data['friendship_id'] ?? null;
        $idUserDelete = $data['friend_id'] ?? null; // Para eliminar por ID de usuario
        $myId = $data['user_id'] ?? null;

        try {
            if($amistadId) {
                // Borrar por ID de relación (solicitudes)
                $stmt = $pdo->prepare("DELETE FROM AMISTAD WHERE Id_Amistad = ?");
                $stmt->execute([$amistadId]);
            } elseif ($idUserDelete && $myId) {
                // Borrar por IDs de usuarios (eliminar amigo de la lista)
                $stmt = $pdo->prepare("DELETE FROM AMISTAD WHERE (Id_Usuario_1 = ? AND Id_Usuario_2 = ?) OR (Id_Usuario_1 = ? AND Id_Usuario_2 = ?)");
                $stmt->execute([$myId, $idUserDelete, $idUserDelete, $myId]);
            }
            echo json_encode(['success' => true, 'message' => 'Acción realizada']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

} else {
    // --- GET: OBTENER LISTAS ---
    $userId = $_GET['user_id'];
    
    try {
        // 1. AMIGOS
        $sqlFriends = "
            SELECT u.Id_Usuario as id, u.Nombre_Usuario as nombre, u.Avatar as avatar, c.Imagen_Url as corona
            FROM AMISTAD a
            JOIN USUARIO u ON (CASE WHEN a.Id_Usuario_1 = ? THEN a.Id_Usuario_2 = u.Id_Usuario ELSE a.Id_Usuario_1 = u.Id_Usuario END)
            LEFT JOIN CORONA_ACTIVA ca ON u.Id_Usuario = ca.Id_Usuario
            LEFT JOIN CORONA c ON ca.Id_Corona = c.Id_Corona
            WHERE (a.Id_Usuario_1 = ? OR a.Id_Usuario_2 = ?) AND a.Estado = 'aceptada'
        ";
        $stmt = $pdo->prepare($sqlFriends);
        $stmt->execute([$userId, $userId, $userId]);
        $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. SOLICITUDES PENDIENTES
        $sqlPending = "
            SELECT a.Id_Amistad as friendship_id, u.Id_Usuario as id, u.Nombre_Usuario as nombre, u.Avatar as avatar
            FROM AMISTAD a
            JOIN USUARIO u ON a.Id_Usuario_1 = u.Id_Usuario
            WHERE a.Id_Usuario_2 = ? AND a.Estado = 'pendiente'
        ";
        $stmt = $pdo->prepare($sqlPending);
        $stmt->execute([$userId]);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'friends' => $friends,
            'pending_requests' => $pending,
            'available_users' => [] // Ya no necesitamos cargar todos los usuarios, usamos búsqueda
        ]);

    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>