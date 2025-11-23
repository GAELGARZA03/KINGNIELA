// js/socket.js
// Este script debe incluirse en TODAS las páginas HTML después de loguearse

// 1. Configuración de conexión (Ajusta IP si no es localhost)
const SOCKET_URL = 'http://localhost:3000'; 
let socket = null;

// 2. Obtener usuario
const currentUserGlobal = JSON.parse(localStorage.getItem('kingniela_user'));

if (currentUserGlobal) {
    // 3. Iniciar conexión global
    // Usamos 'window.socket' para que otros scripts (como Social.js) puedan usar la misma conexión
    window.socket = io(SOCKET_URL);

    window.socket.on('connect', () => {
        console.log('🟢 Conectado globalmente al Socket.io');
        // Registrar al usuario para aparecer "En línea"
        window.socket.emit('register', currentUserGlobal.id);
    });

    window.socket.on('disconnect', () => {
        console.log('🔴 Desconectado del Socket.io');
    });
    
    // Escuchar llamadas entrantes en cualquier página (Opcional: notificación)
    window.socket.on('video-call-offer', (data) => {
        // Si no estamos en Social.html, podríamos mostrar una notificación nativa
        if (!window.location.pathname.includes('Social.html')) {
            if(Notification.permission === "granted") {
                new Notification("Llamada Entrante", { body: `${data.caller.nombre} te está llamando.` });
            }
        }
    });
}