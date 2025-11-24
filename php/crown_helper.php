<?php
// Helper para desbloquear coronas
function desbloquearCorona($pdo, $userId, $nombreCorona) {
    try {
        // 1. Obtener ID de la corona por nombre
        $stmtID = $pdo->prepare("SELECT Id_Corona FROM CORONA WHERE Nombre_Corona = ?");
        $stmtID->execute([$nombreCorona]);
        $crownId = $stmtID->fetchColumn();

        if (!$crownId) return false; // No existe esa corona

        // 2. Verificar si el usuario ya la tiene
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM USUARIO_CORONAS WHERE Id_Usuario = ? AND Id_Corona = ?");
        $stmtCheck->execute([$userId, $crownId]);
        
        if ($stmtCheck->fetchColumn() == 0) {
            // 3. Insertar la corona
            $stmtInsert = $pdo->prepare("INSERT INTO USUARIO_CORONAS (Id_Usuario, Id_Corona, Fecha_Obtencion) VALUES (?, ?, NOW())");
            $stmtInsert->execute([$userId, $crownId]);
            return true; // ¡Desbloqueada nueva!
        }
    } catch (Exception $e) {
        // Silenciar errores para no romper el flujo principal
        return false;
    }
    return false; // Ya la tenía
}
?>