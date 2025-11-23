<?php
header('Content-Type: application/json');
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    // --- ENVIAR SOLICITUD ---
    if ($action === 'add_friend') {
        $id1 = $data['user_id1'];
        $id2 = $data['user_id2'];
        
        try {
            // Verificar duplicados (Id_Usuario_1 siempre menor que Id_Usuario_2 para unicidad lógica)
            // O simplemente checar ambas combinaciones
            $stmt = $pdo->prepare("SELECT Id_Amistad FROM AMISTAD WHERE (Id_Usuario_1 = ? AND Id_Usuario_2 = ?) OR (Id_Usuario_1 = ? AND Id_Usuario_2 = ?)");
            $stmt->execute([$id1, $id2, $id2, $id1]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una relación o solicitud']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO AMISTAD (Id_Usuario_1, Id_Usuario_2, Estado) VALUES (?, ?, 'pendiente')");
            $stmt->execute([$id1, $id2]);
            echo json_encode(['success' => true, 'message' => 'Solicitud enviada']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    // --- ACEPTAR SOLICITUD ---
    if ($action === 'accept_friend') {
        $amistadId = $data['friendship_id'];
        try {
            $stmt = $pdo->prepare("UPDATE AMISTAD SET Estado = 'aceptada' WHERE Id_Amistad = ?");
            $stmt->execute([$amistadId]);
            echo json_encode(['success' => true, 'message' => '¡Ahora son amigos!']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

} else {
    // --- OBTENER LISTAS (GET) ---
    $userId = $_GET['user_id'];
    
    try {
        // 1. Obtener amigos confirmados
        // Buscamos donde soy Usuario_1 O Usuario_2 y el estado es aceptada
        $sqlFriends = "
            SELECT u.Id_Usuario as id, u.Nombre_Usuario as nombre, u.Avatar as avatar
            FROM AMISTAD a
            JOIN USUARIO u ON (
                CASE 
                    WHEN a.Id_Usuario_1 = ? THEN a.Id_Usuario_2 = u.Id_Usuario
                    ELSE a.Id_Usuario_1 = u.Id_Usuario
                END
            )
            WHERE (a.Id_Usuario_1 = ? OR a.Id_Usuario_2 = ?)
            AND a.Estado = 'aceptada'
        ";
        $stmt = $pdo->prepare($sqlFriends);
        $stmt->execute([$userId, $userId, $userId]);
        $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Obtener solicitudes pendientes (Donde yo soy el #2, el que recibe)
        $sqlPending = "
            SELECT a.Id_Amistad as friendship_id, u.Id_Usuario as id, u.Nombre_Usuario as nombre, u.Avatar as avatar
            FROM AMISTAD a
            JOIN USUARIO u ON a.Id_Usuario_1 = u.Id_Usuario
            WHERE a.Id_Usuario_2 = ? AND a.Estado = 'pendiente'
        ";
        $stmt = $pdo->prepare($sqlPending);
        $stmt->execute([$userId]);
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Buscar usuarios disponibles (Excluyendo amigos y a mí mismo)
        // Nota: Esta consulta puede ser pesada si hay muchos usuarios, idealmente se filtra por búsqueda de texto
        $sqlAvailable = "
            SELECT Id_Usuario as id, Nombre_Usuario as nombre, Avatar as avatar
            FROM USUARIO
            WHERE Id_Usuario != ?
            AND Id_Usuario NOT IN (
                SELECT Id_Usuario_1 FROM AMISTAD WHERE Id_Usuario_2 = ?
                UNION
                SELECT Id_Usuario_2 FROM AMISTAD WHERE Id_Usuario_1 = ?
            )
            LIMIT 20
        ";
        $stmt = $pdo->prepare($sqlAvailable);
        $stmt->execute([$userId, $userId, $userId]);
        $available = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'friends' => $friends,
            'pending_requests' => $pending,
            'available_users' => $available
        ]);

    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>