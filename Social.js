document.addEventListener('DOMContentLoaded', () => {
    
    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    // CONEXIÓN SOCKET.IO
    const socket = io('http://localhost:3000'); 

    // Referencias DOM
    const chatMessages = document.getElementById('chat-messages');
    const messageInput = document.getElementById('message-input');
    const fileInput = document.getElementById('fileInput');
    
    // Elementos Video
    const callingModal = document.getElementById('callingModal');
    const incomingCallModal = document.getElementById('incomingCallModal');
    const videoCallContainer = document.getElementById('videoCallContainer');
    const localVideo = document.getElementById('localVideo');
    const remoteVideo = document.getElementById('remoteVideo');

    // Variables de Estado
    let currentChatFriendId = null;
    let globalFriendsList = [];
    let onlineUsersSet = new Set();
    
    // WebRTC
    let localStream = null;
    let remoteStream = null;
    let peerConnection = null;
    let currentCallPartner = null;
    const iceServers = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

    // ============================================================
    // 1. SOCKETS: CONEXIÓN Y CHAT
    // ============================================================

    socket.on('connect', () => {
        console.log('Conectado a Socket.io');
        socket.emit('register', currentUser.id);
        
        // Pedir usuarios online iniciales
        fetch('http://localhost:3000/online-users')
            .then(r => r.json())
            .then(ids => {
                ids.forEach(id => onlineUsersSet.add(id));
                renderFriends('all');
            });
    });

    socket.on('user_status', (data) => {
        if(data.status === 'online') onlineUsersSet.add(parseInt(data.userId));
        else onlineUsersSet.delete(parseInt(data.userId));
        renderFriends('all'); // Actualizar UI
    });

    socket.on('newMessage', (msg) => {
        // Si es para mí y estoy en ese chat
        const senderId = parseInt(msg.Id_Remitente || msg.remitente_id || msg.sender_id);
        if(currentChatFriendId && senderId === currentChatFriendId) {
            appendMessageToChat(msg, 'received');
        }
    });


    // ============================================================
    // 2. CHAT Y MULTIMEDIA
    // ============================================================

    window.openChat = function(friendId) {
        const friend = globalFriendsList.find(f => parseInt(f.id) === friendId);
        if(!friend) return;

        currentChatFriendId = friendId;
        
        // Render Header
        const crown = friend.corona ? `<img src="${friend.corona}" class="crown-badge">` : '';
        document.getElementById('chat-current-name').innerHTML = `<div class="name-container">${friend.nombre} ${crown}</div>`;
        document.getElementById('chat-current-avatar').innerHTML = `<img src="${friend.avatar}" class="profile-pic">`;
        
        const isOnline = onlineUsersSet.has(friendId);
        document.getElementById('chat-current-status').innerText = isOnline ? 'En línea' : 'Desconectado';
        document.getElementById('chat-current-status').style.color = isOnline ? '#00ff26' : 'gray';

        // Mostrar vista
        document.getElementById('friends-view').classList.add('hidden');
        document.getElementById('chat-view').classList.remove('hidden');

        loadMessages(friendId);
    }

    function loadMessages(friendId) {
        chatMessages.innerHTML = "<p style='text-align:center;color:#ccc'>Cargando...</p>";
        fetch(`php/messages.php?sender_id=${currentUser.id}&receiver_id=${friendId}`)
            .then(r => r.json())
            .then(msgs => {
                chatMessages.innerHTML = "";
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
                contentHtml = `<a href="https://maps.google.com/?q=${contenido}" target="_blank" style="color:#ffdd00;">📍 Ver Ubicación</a>`;
                break;
            case 'archivo':
                const name = contenido.split('/').pop().substring(14); // Quitar uniqid
                contentHtml = `<a href="${contenido}" download style="color:white;">📎 ${name}</a>`;
                break;
            default: 
                contentHtml = `<p style="margin:0;">${contenido}</p>`;
        }

        div.innerHTML = `
            <div class="message-content" style="background:${type==='sent'?'#0f1b73':'#1a2cbd'}; border-radius:10px; padding:10px;">
                ${contentHtml}
                <span style="font-size:10px; opacity:0.7; display:block; text-align:right;">${msg.Fecha_Envio ? new Date(msg.Fecha_Envio).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : 'Ahora'}</span>
            </div>
        `;
        chatMessages.appendChild(div);
        
        // Activar reproductor de audio si hay
        if(tipo === 'audio') activateAudio(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function activateAudio(el) {
        const btn = el.querySelector('.play-btn');
        const audio = el.querySelector('audio');
        const fill = el.querySelector('.progress-fill');
        
        btn.onclick = () => audio.paused ? audio.play() : audio.pause();
        audio.onplay = () => btn.innerText = '⏸';
        audio.onpause = () => btn.innerText = '▶';
        audio.ontimeupdate = () => fill.style.width = (audio.currentTime / audio.duration * 100) + '%';
    }

    // ENVÍO DE MENSAJES
    window.sendMessage = function() {
        const text = messageInput.value.trim();
        if(!text) return;
        sendPayload({ action: 'send', content: text, tipo: 'texto' });
        messageInput.value = "";
    }

    // Botones Multimedia
    document.getElementById('attachFileBtn').onclick = () => fileInput.click();
    fileInput.onchange = () => {
        if(fileInput.files[0]) {
            const formData = new FormData();
            formData.append('action', 'send_file'); // PHP debe manejar esto
            formData.append('file', fileInput.files[0]);
            sendPayload(formData, true);
        }
    };

    document.getElementById('shareLocationBtn').onclick = () => {
        if(!navigator.geolocation) return alert("No soportado");
        navigator.geolocation.getCurrentPosition(pos => {
            sendPayload({ action: 'send', content: `${pos.coords.latitude},${pos.coords.longitude}`, tipo: 'ubicacion' });
        });
    };

    // Grabación de Audio (Simplificada)
    let mediaRecorder, audioChunks = [];
    document.getElementById('recordAudioBtn').onclick = async () => {
        if(mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            document.getElementById('recordAudioBtn').style.color = '#ccc';
        } else {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = () => {
                const blob = new Blob(audioChunks, { type: 'audio/webm' });
                const file = new File([blob], "voice_note.webm", { type: "audio/webm" });
                const fd = new FormData();
                fd.append('action', 'send_file');
                fd.append('file', file);
                sendPayload(fd, true);
            };
            mediaRecorder.start();
            document.getElementById('recordAudioBtn').style.color = 'red';
        }
    };

    function sendPayload(data, isFile = false) {
        if(!currentChatFriendId) return;
        
        let body;
        if(isFile) {
            body = data; // FormData
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
                    // Agregar a mi chat localmente (simulación inmediata)
                    // El socket se encargará de que le llegue al otro
                    if(!isFile && data.tipo === 'texto') {
                        appendMessageToChat({ Contenido: data.content, Tipo: data.tipo, Fecha_Envio: new Date() }, 'sent');
                    }
                }
            });
    }


    // ============================================================
    // 3. VIDEOLLAMADAS (WEBRTC)
    // ============================================================

    // A. Iniciar Llamada (Caller)
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

    // B. Cancelar
    document.getElementById('cancelCallBtn').onclick = () => {
        callingModal.classList.add('hidden');
        socket.emit('video-call-cancel', { receiverId: currentCallPartner.id });
        currentCallPartner = null;
    };

    // C. Recibir Llamada
    socket.on('video-call-offer', (data) => {
        if(currentCallPartner) return; // Ocupado
        const { caller } = data;
        currentCallPartner = caller; // Guardar ID
        
        document.getElementById('incomingCallFriendName').innerText = `${caller.nombre} te llama...`;
        incomingCallModal.classList.remove('hidden');
    });

    // D. Aceptar
    document.getElementById('acceptCallBtn').onclick = () => {
        incomingCallModal.classList.add('hidden');
        socket.emit('video-call-accept', { callerId: currentCallPartner.id, receiver: currentUser });
        startWebRTC(false); // Soy receiver
    };

    // E. Rechazar
    document.getElementById('rejectCallBtn').onclick = () => {
        incomingCallModal.classList.add('hidden');
        socket.emit('video-call-reject', { callerId: currentCallPartner.id });
        currentCallPartner = null;
    };

    // F. Respuestas del servidor
    socket.on('video-call-accepted', () => {
        callingModal.classList.add('hidden');
        startWebRTC(true); // Soy caller
    });

    socket.on('video-call-rejected', () => {
        callingModal.classList.add('hidden');
        alert('Llamada rechazada');
        currentCallPartner = null;
    });

    socket.on('call-ended', () => endCallUI());


    // G. WebRTC Logic
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
            alert('Error al acceder a cámara/micrófono');
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

    // Terminar
    document.getElementById('endCallBtn').onclick = () => {
        socket.emit('end-call', { partnerId: currentCallPartner.id });
        endCallUI();
    };

    function endCallUI() {
        videoCallContainer.classList.add('hidden');
        if(localStream) localStream.getTracks().forEach(t => t.stop());
        if(peerConnection) peerConnection.close();
        localStream = null;
        peerConnection = null;
        currentCallPartner = null;
    }


    // 4. CARGA INICIAL DE AMIGOS (Tu lógica existente)
    loadFriends();
    function loadFriends() {
        fetch(`php/friends.php?user_id=${currentUser.id}`)
            .then(r => r.json())
            .then(data => {
                globalFriendsList = data.friends || [];
                renderFriends('all');
                renderChatList();
            });
    }
    
    // (Mantener aquí tus funciones renderFriends, renderChatList, etc. del Social.js anterior)
    // Solo recuerda que ahora openChat llama a la nueva versión que maneja sockets.
    
    // ... (Copia aquí las funciones renderFriends y renderChatList de tu Social.js previo) ...

    window.closeChat = function() {
        document.getElementById('chat-view').classList.add('hidden');
        document.getElementById('friends-view').classList.remove('hidden');
    }
});