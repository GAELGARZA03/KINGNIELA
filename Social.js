document.addEventListener('DOMContentLoaded', () => {
    
    // 1. VERIFICACIÓN DE USUARIO
    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    // 2. CONEXIÓN SOCKET.IO
    // Usamos la conexión global si existe, si no, creamos una (fallback)
    const socket = window.socket || io('http://localhost:3000'); 

    // --- REFERENCIAS DOM ---
    const chatMessages = document.getElementById('chat-messages');
    const messageInput = document.getElementById('message-input');
    const fileInput = document.getElementById('fileInput');
    const inputAddFriend = document.getElementById('input-add-friend');
    
    // Elementos Video
    const callingModal = document.getElementById('callingModal');
    const incomingCallModal = document.getElementById('incomingCallModal');
    const videoCallContainer = document.getElementById('videoCallContainer');
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');

    // Variables de Estado
    let currentChatFriendId = null;
    let globalFriendsList = [];
    let globalRequestsList = [];
    let onlineUsersSet = new Set();
    let currentFilter = 'all'; // Para saber qué pestaña refrescar
    
    // WebRTC Variables
    let localStream = null;
    let remoteStream = null;
    let peerConnection = null;
    let currentCallPartner = null;
    const iceServers = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };


    // ============================================================
    // 3. SOCKET LISTENERS (LÓGICA DE ESTADO EN LÍNEA)
    // ============================================================

    // Función para pedir al servidor quién está conectado
    function requestOnlineUsers() {
        fetch('http://localhost:3000/online-users')
            .then(r => r.json())
            .then(ids => {
                // Limpiamos y llenamos el Set asegurando que sean NÚMEROS
                onlineUsersSet.clear();
                ids.forEach(id => onlineUsersSet.add(parseInt(id)));
                console.log("👥 Usuarios online actualizados:", Array.from(onlineUsersSet));
                
                // Refrescamos la interfaz
                renderFriends(currentFilter);
                renderChatList();
            })
            .catch(err => console.log("Error contactando Node:", err));
    }

    // Si el socket ya estaba conectado (por socket.js), pedimos datos YA
    if(socket.connected) {
        console.log("⚡ Socket ya conectado, pidiendo usuarios...");
        requestOnlineUsers();
    }

    socket.on('connect', () => {
        console.log('🟢 Social.js conectado al Socket');
        if(!window.socket) socket.emit('register', currentUser.id);
        requestOnlineUsers();
    });

    // Escuchar cuando alguien entra o sale
    socket.on('user_status', (data) => {
        const uid = parseInt(data.userId);
        console.log(`🔔 Cambio de estado: Usuario ${uid} está ${data.status}`);
        
        if(data.status === 'online') onlineUsersSet.add(uid);
        else onlineUsersSet.delete(uid);
        
        // Actualizar UI inmediatamente
        renderFriends(currentFilter); 
        renderChatList();
        
        // Si estoy chateando con él, actualizar texto header
        if(currentChatFriendId == uid) {
             updateChatHeaderStatus(data.status === 'online');
        }
    });

    // Escuchar mensajes nuevos
    socket.on('newMessage', (msg) => {
        const senderId = parseInt(msg.Id_Remitente || msg.remitente_id || msg.sender_id);
        // Si es el chat abierto, mostrarlo
        if(currentChatFriendId && senderId === currentChatFriendId) {
            appendMessageToChat(msg, 'received');
        }
    });


    // ============================================================
    // 4. CARGA DE DATOS (PHP/MYSQL)
    // ============================================================
    
    loadSocialData();

    function loadSocialData() {
        fetch(`php/friends.php?user_id=${currentUser.id}`)
        .then(r => r.json())
        .then(data => {
            globalFriendsList = data.friends || [];
            globalRequestsList = data.pending_requests || [];
            
            renderChatList(); 
            renderFriends('all'); 
            // Una vez cargados los amigos, volvemos a pedir estados por si acaso
            requestOnlineUsers();
        })
        .catch(e => console.error("Error cargando amigos:", e));
    }


    // ============================================================
    // 5. RENDERIZADO DE INTERFAZ
    // ============================================================

    window.renderFriends = function(filter) {
        currentFilter = filter; // Guardamos el filtro actual
        const grid = document.getElementById('friends-grid');
        if(!grid) return;
        grid.innerHTML = "";
        
        // Botones visuales
        const btnAll = document.getElementById('btn-all');
        const btnOnline = document.getElementById('btn-online');
        if(btnAll) btnAll.classList.toggle('active', filter === 'all');
        if(btnOnline) btnOnline.classList.toggle('active', filter === 'online');

        if (filter === 'all') {
            // A. Solicitudes
            if (globalRequestsList.length > 0) {
                const h3 = document.createElement('h3'); 
                h3.innerText = "Solicitudes"; 
                h3.style.color='white'; h3.style.marginLeft='10px';
                grid.appendChild(h3);

                globalRequestsList.forEach(req => grid.appendChild(createFriendCard(req, 'request')));
                
                const hr = document.createElement('hr'); hr.className='separator'; grid.appendChild(hr);
            }

            // B. Amigos
            if (globalFriendsList.length > 0) {
                globalFriendsList.forEach(f => grid.appendChild(createFriendCard(f, 'friend')));
            } else if (globalRequestsList.length === 0) {
                grid.innerHTML += "<p style='text-align:center;color:#ccc;padding:20px;'>Sin amigos aún.</p>";
            }

        } else if (filter === 'online') {
            // C. Solo En Línea
            const online = globalFriendsList.filter(f => onlineUsersSet.has(parseInt(f.id)));
            if(online.length === 0) {
                grid.innerHTML = "<p style='text-align:center;color:#ccc;padding:20px;'>Nadie en línea.</p>";
            } else {
                online.forEach(f => grid.appendChild(createFriendCard(f, 'friend')));
            }
        }
    }

    function createFriendCard(user, type) {
        const div = document.createElement('div');
        div.className = 'friend';
        
        // CORRECCIÓN CLAVE: Parsear siempre a entero para comparar
        const isOnline = onlineUsersSet.has(parseInt(user.id));
        const statusColor = isOnline ? '#00ff26' : 'gray';
        const statusText = isOnline ? 'En línea' : 'Desconectado';
        
        const crown = user.corona ? `<img src="${user.corona}" class="crown-badge" alt="Insignia">` : '';
        const avatar = user.avatar || 'Imagenes/I_Perfil.png';

        let actionsHtml = '';
        if(type === 'request') {
            actionsHtml = `
                <div class="friend-actions">
                    <button class="btn-accept" onclick="acceptRequest(${user.friendship_id})" title="Aceptar">✅</button>
                    <button class="btn-reject" onclick="rejectRequest(${user.friendship_id})" title="Rechazar">❌</button>
                </div>`;
        } else {
            actionsHtml = `
                <div class="friend-actions">
                    <button onclick="openChat(${user.id})" title="Chat">💬</button>
                    <button class="delete-friend" onclick="deleteFriend(${user.id})" title="Eliminar">⋯</button>
                </div>`;
        }

        div.innerHTML = `
            <div class="friend-info">
                <img src="${avatar}" class="profile-pic">
                <div>
                    <div class="name-container"><strong>${user.nombre}</strong>${crown}</div>
                    ${type === 'friend' ? 
                      `<span style="font-size:12px;color:#ccc;display:flex;align-items:center;gap:5px;">
                         <span class="status-dot" style="background:${statusColor};width:8px;height:8px;border-radius:50%;"></span> ${statusText}
                       </span>` 
                      : '<span style="font-size:12px;color:#ffdd00">Pendiente</span>'}
                </div>
            </div>
            ${actionsHtml}
        `;
        return div;
    }

    function renderChatList() {
        const container = document.getElementById('chat-list-container');
        if(!container) return;
        container.innerHTML = "";

        globalFriendsList.forEach(friend => {
            const item = document.createElement('div');
            item.className = 'user';
            item.onclick = () => openChat(friend.id);

            const crown = friend.corona ? `<img src="${friend.corona}" class="crown-badge">` : '';
            const avatar = friend.avatar || 'Imagenes/I_Perfil.png';
            
            // Puntito en sidebar también
            const isOnline = onlineUsersSet.has(parseInt(friend.id));
            const dotColor = isOnline ? '#00ff26' : 'gray';

            item.innerHTML = `
                <div style="position:relative;">
                    <img src="${avatar}" class="profile-pic">
                    <div style="position:absolute; bottom:0; right:0; width:10px; height:10px; border-radius:50%; background:${dotColor}; border:1px solid #001f5c;"></div>
                </div>
                <div class="name-container"><span>${friend.nombre}</span>${crown}</div>
            `;
            container.appendChild(item);
        });
    }


    // ============================================================
    // 6. CHAT Y MULTIMEDIA
    // ============================================================

    window.openChat = function(friendId) {
        const friend = globalFriendsList.find(f => parseInt(f.id) === friendId);
        if(!friend) return;

        currentChatFriendId = friendId;
        
        // Header Chat
        const crown = friend.corona ? `<img src="${friend.corona}" class="crown-badge">` : '';
        document.getElementById('chat-current-name').innerHTML = `<div class="name-container">${friend.nombre} ${crown}</div>`;
        document.getElementById('chat-current-avatar').innerHTML = `<img src="${friend.avatar || 'Imagenes/I_Perfil.png'}" class="profile-pic" style="width:100%;height:100%;border-radius:50%;">`;
        
        updateChatHeaderStatus(onlineUsersSet.has(parseInt(friendId)));

        document.getElementById('friends-view').classList.add('hidden');
        document.getElementById('chat-view').classList.remove('hidden');

        loadMessages(friendId);
    }

    function updateChatHeaderStatus(isOnline) {
        const label = document.getElementById('chat-current-status');
        if(label) {
            label.innerText = isOnline ? 'En línea' : 'Desconectado';
            label.style.color = isOnline ? '#00ff26' : 'gray';
        }
    }

    function loadMessages(friendId) {
        chatMessages.innerHTML = "<p style='text-align:center;color:#ccc;margin-top:20px;'>Cargando...</p>";
        fetch(`php/messages.php?sender_id=${currentUser.id}&receiver_id=${friendId}`)
            .then(r => r.json())
            .then(msgs => {
                chatMessages.innerHTML = "";
                if(msgs.error) return;
                
                msgs.forEach(m => {
                    const isMe = parseInt(m.Id_Remitente) == currentUser.id;
                    appendMessageToChat(m, isMe ? 'sent' : 'received');
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
    }

    function appendMessageToChat(msg, type) {
        const div = document.createElement('div');
        div.className = `message ${type}`;
        if(type === 'sent') div.style.alignSelf = 'flex-end';
        
        let contentHtml = '';
        const tipo = msg.Tipo || msg.tipo;
        const contenido = msg.Contenido || msg.contenido;

        switch(tipo) {
            case 'imagen': 
                contentHtml = `<img src="${contenido}" style="max-width:200px; border-radius:10px;">`; 
                break;
            case 'audio': 
                contentHtml = `<div class="audio-message"><button class="play-btn">▶</button><div class="progress-bar"><div class="progress-fill"></div></div><span class="audio-time">Audio</span><audio src="${contenido}"></audio></div>`; 
                break;
            case 'ubicacion':
                contentHtml = `<a href="https://maps.google.com/?q=${contenido}" target="_blank" style="color:#ffdd00; text-decoration:underline;">📍 Ver Ubicación</a>`;
                break;
            case 'archivo':
                const name = contenido.split('/').pop().substring(14); // Limpiar nombre
                contentHtml = `<a href="${contenido}" download style="color:white; text-decoration:underline;">📎 ${name}</a>`;
                break;
            default: 
                contentHtml = `<p style="margin:0;">${contenido}</p>`;
        }

        const dateObj = msg.Fecha_Envio ? new Date(msg.Fecha_Envio) : new Date();
        const timeStr = dateObj.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

        div.innerHTML = `
            <div class="message-content" style="background:${type==='sent'?'#0f1b73':'#1a2cbd'}; border-radius:10px; padding:10px;">
                ${contentHtml}
                <span style="font-size:10px; opacity:0.7; display:block; text-align:right; margin-top:4px;">${timeStr}</span>
            </div>
        `;
        chatMessages.appendChild(div);
        
        if(tipo === 'audio') activateAudio(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function activateAudio(el) {
        const btn = el.querySelector('.play-btn');
        const audio = el.querySelector('audio');
        const fill = el.querySelector('.progress-fill');
        
        if(!audio || !btn) return;
        btn.onclick = () => audio.paused ? audio.play() : audio.pause();
        audio.onplay = () => btn.innerText = '⏸';
        audio.onpause = () => btn.innerText = '▶';
        audio.ontimeupdate = () => { if(audio.duration) fill.style.width = (audio.currentTime / audio.duration * 100) + '%'; };
        audio.onended = () => { btn.innerText = '▶'; fill.style.width = '0%'; }
    }

    // ENVÍO
    window.sendMessage = function() {
        const text = messageInput.value.trim();
        if(!text) return;
        sendPayload({ action: 'send', content: text, tipo: 'texto' });
        messageInput.value = "";
    }

    if(document.getElementById('attachFileBtn')) {
        document.getElementById('attachFileBtn').onclick = () => fileInput.click();
        fileInput.onchange = () => {
            if(fileInput.files[0]) {
                const fd = new FormData();
                fd.append('action', 'send_file');
                fd.append('file', fileInput.files[0]);
                sendPayload(fd, true);
                fileInput.value = ""; 
            }
        };
    }

    if(document.getElementById('shareLocationBtn')) {
        document.getElementById('shareLocationBtn').onclick = () => {
            if(!navigator.geolocation) return alert("Navegador no soporta geolocalización");
            navigator.geolocation.getCurrentPosition(pos => {
                sendPayload({ action: 'send', content: `${pos.coords.latitude},${pos.coords.longitude}`, tipo: 'ubicacion' });
            });
        };
    }

    // Grabador Audio
    let mediaRecorder;
    const btnRec = document.getElementById('recordAudioBtn');
    if(btnRec) {
        btnRec.onclick = async () => {
            if(mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                btnRec.style.color = '#ccc';
            } else {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    mediaRecorder = new MediaRecorder(stream);
                    const chunks = [];
                    mediaRecorder.ondataavailable = e => chunks.push(e.data);
                    mediaRecorder.onstop = () => {
                        const blob = new Blob(chunks, { type: 'audio/webm' });
                        const file = new File([blob], "audio_msg.webm", { type: "audio/webm" });
                        const fd = new FormData();
                        fd.append('action', 'send_file');
                        fd.append('file', file);
                        sendPayload(fd, true);
                    };
                    mediaRecorder.start();
                    btnRec.style.color = 'red'; 
                } catch(e) {
                    alert("Permiso de micrófono denegado");
                }
            }
        };
    }

    function sendPayload(data, isFile = false) {
        if(!currentChatFriendId) return;
        let body;
        if(isFile) {
            body = data; 
            body.append('sender_id', currentUser.id);
            body.append('receiver_id', currentChatFriendId);
        } else {
            body = new FormData();
            body.append('action', 'send');
            body.append('sender_id', currentUser.id);
            body.append('receiver_id', currentChatFriendId);
            body.append('content', data.content);
            body.append('tipo', data.tipo);
        }

        fetch('php/messages.php', { method: 'POST', body: body })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    if(!isFile && data.tipo === 'texto') {
                        appendMessageToChat({ Contenido: data.content, Tipo: data.tipo, Fecha_Envio: new Date() }, 'sent');
                    }
                }
            });
    }


    // ============================================================
    // 7. VIDEOLLAMADAS (WEBRTC)
    // ============================================================

    if(document.getElementById('startVideoCallBtn')) {
        document.getElementById('startVideoCallBtn').onclick = () => {
            if(!currentChatFriendId) return;
            const friend = globalFriendsList.find(f => parseInt(f.id) === currentChatFriendId);
            
            currentCallPartner = friend;
            document.getElementById('callingFriendName').innerText = friend.nombre;
            callingModal.classList.remove('hidden');
            
            socket.emit('video-call-offer', {
                caller: { id: currentUser.id, nombre: currentUser.nombre },
                receiver: { id: friend.id }
            });
        };
    }

    if(document.getElementById('cancelCallBtn')) {
        document.getElementById('cancelCallBtn').onclick = () => {
            callingModal.classList.add('hidden');
            socket.emit('video-call-cancel', { receiverId: currentCallPartner.id });
            currentCallPartner = null;
        };
    }

    socket.on('video-call-offer', (data) => {
        if(currentCallPartner) return; 
        const { caller } = data;
        currentCallPartner = caller;
        document.getElementById('incomingCallFriendName').innerText = `${caller.nombre} te llama...`;
        incomingCallModal.classList.remove('hidden');
    });

    if(document.getElementById('acceptCallBtn')) {
        document.getElementById('acceptCallBtn').onclick = () => {
            incomingCallModal.classList.add('hidden');
            socket.emit('video-call-accept', { callerId: currentCallPartner.id, receiver: currentUser });
            startWebRTC(false); // receiver
        };
    }

    if(document.getElementById('rejectCallBtn')) {
        document.getElementById('rejectCallBtn').onclick = () => {
            incomingCallModal.classList.add('hidden');
            socket.emit('video-call-reject', { callerId: currentCallPartner.id });
            currentCallPartner = null;
        };
    }

    socket.on('video-call-accepted', () => {
        callingModal.classList.add('hidden');
        startWebRTC(true); // caller
    });

    socket.on('video-call-rejected', () => {
        callingModal.classList.add('hidden');
        alert('Llamada rechazada');
        currentCallPartner = null;
    });

    socket.on('video-call-cancelled', () => {
        incomingCallModal.classList.add('hidden');
        currentCallPartner = null;
    });

    socket.on('call-ended', () => endCallUI());

    // G. WebRTC
    async function startWebRTC(isCaller) {
        videoCallContainer.classList.remove('hidden');
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            localVideo.srcObject = localStream;
            peerConnection = new RTCPeerConnection(iceServers);
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            peerConnection.ontrack = event => {
                if(!remoteStream) {
                    remoteStream = new MediaStream();
                    remoteVideo.srcObject = remoteStream;
                }
                remoteStream.addTrack(event.track);
            };

            peerConnection.onicecandidate = event => {
                if(event.candidate) {
                    socket.emit('webrtc-signal', { partnerId: currentCallPartner.id, signal: { ice: event.candidate } });
                }
            };

            if(isCaller) {
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                socket.emit('webrtc-signal', { partnerId: currentCallPartner.id, signal: { sdp: peerConnection.localDescription } });
            }
        } catch(e) {
            console.error(e);
            alert("Error acceso cámara/mic");
            endCallUI();
        }
    }

    socket.on('webrtc-signal', async (data) => {
        if(!peerConnection) return;
        const { signal } = data;

        if(signal.sdp) {
            await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.sdp));
            if(signal.sdp.type === 'offer') {
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                socket.emit('webrtc-signal', { partnerId: currentCallPartner.id, signal: { sdp: peerConnection.localDescription } });
            }
        } else if(signal.ice) {
            await peerConnection.addIceCandidate(new RTCIceCandidate(signal.ice));
        }
    });

    if(document.getElementById('endCallBtn')) {
        document.getElementById('endCallBtn').onclick = () => {
            socket.emit('end-call', { partnerId: currentCallPartner.id });
            endCallUI();
        };
    }

    function endCallUI() {
        videoCallContainer.classList.add('hidden');
        if(localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
        if(peerConnection) { peerConnection.close(); peerConnection = null; }
        remoteVideo.srcObject = null;
        currentCallPartner = null;
    }


    // ============================================================
    // 8. ACCIONES DE AMISTAD Y UI
    // ============================================================
    
    window.sendFriendRequest = function() {
        const target = inputAddFriend.value.trim();
        if(!target) return alert("Ingresa un usuario o correo");
        fetch('php/friends.php', { method: 'POST', body: JSON.stringify({ action: 'add_friend', user_id1: currentUser.id, user_id2: target })})
        .then(r=>r.json()).then(d => { alert(d.message); if(d.success) { inputAddFriend.value=""; closeAddFriendModal(); loadSocialData(); }});
    }
    window.acceptRequest = (id) => fetch('php/friends.php', { method: 'POST', body: JSON.stringify({ action: 'accept_friend', friendship_id: id })}).then(()=>loadSocialData());
    window.rejectRequest = (id) => { if(confirm("¿Rechazar solicitud?")) fetch('php/friends.php', { method: 'POST', body: JSON.stringify({ action: 'reject_friend', friendship_id: id })}).then(()=>loadSocialData()); }
    window.deleteFriend = (id) => { if(confirm("¿Eliminar de amigos?")) fetch('php/friends.php', { method: 'POST', body: JSON.stringify({ action: 'delete_friend', friend_id: id, user_id: currentUser.id })}).then(()=>loadSocialData()); }

    window.openAddFriendModal = () => document.getElementById('addFriendModal').classList.remove('hidden');
    window.closeAddFriendModal = () => document.getElementById('addFriendModal').classList.add('hidden');
    window.closeChat = () => { currentChatFriendId = null; document.getElementById('chat-view').classList.add('hidden'); document.getElementById('friends-view').classList.remove('hidden'); }
    window.toggleChatOptions = () => document.getElementById('chat-options-menu').classList.toggle('hidden');
    window.openDeleteModal = (id) => deleteFriend(id); 
    window.filterFriends = (t) => renderFriends(t);

});