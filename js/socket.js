// ==========================================
// CONFIGURACIÓN CENTRAL (Edita aquí tu IP)
// ==========================================
// Cambia 'localhost' por tu IP (ej. '192.168.1.50') para probar en otros dispositivos
window.SERVER_URL = 'http://localhost:3000'; 
// ==========================================

// js/socket.js
const currentUserGlobal = JSON.parse(localStorage.getItem('kingniela_user'));

if (currentUserGlobal) {
    // Si no existe una conexión previa, la creamos usando la URL global
    if (!window.socket) {
        console.log("🔄 Iniciando conexión global al Socket en: " + window.SERVER_URL);
        
        window.socket = io(window.SERVER_URL);

        window.socket.on('connect', () => {
            console.log(`✅ Conectado como ${currentUserGlobal.nombre} (${currentUserGlobal.id})`);
            // Registramos al usuario para que aparezca EN LÍNEA
            window.socket.emit('register', currentUserGlobal.id);
        });

        window.socket.on('disconnect', () => {
            console.log('❌ Desconectado del servidor');
        });
        
        // Escuchar llamadas entrantes en CUALQUIER página
        window.socket.on('video-call-offer', (data) => {
            // Si NO estamos en Social.html (donde ya sale el modal), avisamos
            if (!window.location.pathname.includes('Social.html')) {
                if (confirm(`📞 Llamada entrante de ${data.caller.nombre}. ¿Ir al chat para contestar?`)) {
                    window.location.href = 'Social.html';
                }
            }
        });
    }
}