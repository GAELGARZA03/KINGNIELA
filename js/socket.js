// ==========================================
// CONFIGURACIÓN CENTRAL
// ==========================================
window.SERVER_URL = 'http://localhost:3000'; 
// ==========================================

// js/socket.js
const currentUserGlobal = JSON.parse(localStorage.getItem('kingniela_user'));

if (currentUserGlobal) {
    
    // --- 1. ACTUALIZAR INTERFAZ (HEADER) ---
    const headerProfileImg = document.querySelector('.user-actions .perfil img');
    if (headerProfileImg && currentUserGlobal.avatar) {
        headerProfileImg.src = currentUserGlobal.avatar;
    }

    // --- 2. SISTEMA DE NOTIFICACIONES (AHORA GLOBAL) ---
    window.actualizarNotificaciones = function() {
        fetch('php/notificaciones.php')
            .then(r => r.json())
            .then(data => {
                // Buscar los enlaces en el menú
                const btnSocial = document.querySelector('a[href="Social.html"]');
                const btnTareas = document.querySelector('a[href="Tarea.html"]');
                
                if (btnSocial) {
                    const count = data.social > 9 ? '9+' : data.social;
                    btnSocial.setAttribute('data-count', data.social > 0 ? count : 0);
                }
                if (btnTareas) {
                    const count = data.tareas > 9 ? '9+' : data.tareas;
                    btnTareas.setAttribute('data-count', data.tareas > 0 ? count : 0);
                }
            })
            .catch(e => console.error("Error notificaciones:", e));
    };

    // Ejecutar al cargar
    window.actualizarNotificaciones();
    // Ejecutar cada 10 segundos (Polling suave)
    setInterval(window.actualizarNotificaciones, 10000);


    // --- 3. SOCKET.IO ---
    if (!window.socket) {
        console.log("🔄 Iniciando conexión global al Socket en: " + window.SERVER_URL);
        
        window.socket = io(window.SERVER_URL);

        window.socket.on('connect', () => {
            console.log(`✅ Conectado como ${currentUserGlobal.nombre} (${currentUserGlobal.id})`);
            window.socket.emit('register', currentUserGlobal.id);
        });

        // Si llega un mensaje nuevo, actualizamos las notificaciones al instante
        window.socket.on('newMessage', () => {
            if (window.actualizarNotificaciones) window.actualizarNotificaciones();
        });

        window.socket.on('disconnect', () => {
            console.log('❌ Desconectado del servidor');
        });
        
        window.socket.on('video-call-offer', (data) => {
            if (!window.location.pathname.includes('Social.html')) {
                if (confirm(`📞 Llamada entrante de ${data.caller.nombre}. ¿Ir al chat para contestar?`)) {
                    window.location.href = 'Social.html';
                }
            }
        });
    }
}