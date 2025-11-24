<?php
header('Content-Type: application/json');
require 'conexion.php';
session_start();

$idQuiniela = isset($_GET['id_quiniela']) ? intval($_GET['id_quiniela']) : 0;
$myId = $_SESSION['user_id'] ?? 0;

if ($idQuiniela <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de Quiniela inválido']);
    exit;
}

try {
    // CAMBIO CLAVE: JOIN a CORONA_ACTIVA y a CORONA para obtener la URL de la imagen.
    $sql = "SELECT 
                U.Id_Usuario, 
                U.Nombre_Usuario, 
                U.Avatar,
                C.Imagen_Url AS Corona_Path,
                Q.Id_Creador
            FROM QUINIELA_INTEGRANTES QI
            INNER JOIN USUARIO U ON QI.Id_Usuario = U.Id_Usuario 
            INNER JOIN QUINIELA Q ON QI.Id_Quiniela = Q.Id_Quiniela
            -- JOIN IZQUIERDO para obtener la corona si existe, si no, es NULL
            LEFT JOIN CORONA_ACTIVA CA ON U.Id_Usuario = CA.Id_Usuario 
            LEFT JOIN CORONA C ON CA.Id_Corona = C.Id_Corona 
            WHERE QI.Id_Quiniela = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idQuiniela]);
    $rawdata = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $miembros = [];
    $creadorDelGrupo = 0; 
    
    if (!empty($rawdata)) {
        $creadorDelGrupo = $rawdata[0]['Id_Creador'] ?? $rawdata[0]['ID_CREADOR'] ?? 0;
    }

    foreach ($rawdata as $row) {
        $id = $row['Id_Usuario'] ?? $row['ID_USUARIO'] ?? 0;
        $nombre = $row['Nombre_Usuario'] ?? $row['NOMBRE_USUARIO'] ?? 'Anónimo';
        $avatar = $row['Avatar'] ?? $row['AVATAR'] ?? 'Imagenes/I_Perfil.png';
        
        // LECTURA DE CORONA: Lee la columna que ahora se llama 'Corona_Path'
        $corona_path = $row['Corona_Path'] ?? ''; 

        $miembros[] = [
            'id' => $id,
            'nombre' => $nombre,
            'avatar' => $avatar,
            'corona' => $corona_path, // Enviamos la URL de la imagen al JS
            'es_propio' => ($id == $myId),
            'es_admin' => ($id == $creadorDelGrupo)
        ];
    }

    echo json_encode([
        'success' => true, 
        'miembros' => $miembros,
        'soy_admin' => ($myId == $creadorDelGrupo) // Indica al JS si TÚ puedes eliminar
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>