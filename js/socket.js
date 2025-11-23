// js/socket.js
// Configuración
const SOCKET_URL = 'http://localhost:3000'; 

// Verificar si hay usuario guardado
const currentUserGlobal = JSON.parse(localStorage.getItem('kingniela_user'));

if (currentUserGlobal) {
    // Si no existe una conexión previa, la creamos
    if (!window.socket) {
        console.log("🔄 Iniciando conexión global al Socket...");
        
        window.socket = io(SOCKET_URL);

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