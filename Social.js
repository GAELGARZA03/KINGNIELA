// --- DATOS ESTÁTICOS (MOCK DATA) ---
const amigosData = [
    { 
        id: 1, 
        nombre: "Juan Pérez", 
        status: "online", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaDiamante1.png"
    },
    { 
        id: 2, 
        nombre: "Carlos López", 
        status: "offline", 
        avatar: "Imagenes/I_Perfil.png",
        corona: null 
    },
    { 
        id: 3, 
        nombre: "Ana Torres", 
        status: "online", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaOro1.png"
    },
    { 
        id: 4, 
        nombre: "Luis Gomez", 
        status: "offline", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaPlata1.png"
    },
    { 
        id: 5, 
        nombre: "Maria Ruiz", 
        status: "online", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaBronce1.png"
    }
];

let currentFilter = 'all';
let friendToDeleteId = null; 

document.addEventListener('DOMContentLoaded', () => {
    renderFriends(currentFilter);
    renderChatList();
});

// --- RENDERIZADO ---

function renderFriends(filter) {
    const grid = document.getElementById('friends-grid');
    grid.innerHTML = "";

    const filteredFriends = amigosData.filter(friend => {
        if (filter === 'online') return friend.status === 'online';
        return true; 
    });

    filteredFriends.forEach(friend => {
        const friendCard = document.createElement('div');
        friendCard.className = 'friend';
        
        const statusColor = friend.status === 'online' ? '#00ff26' : 'gray';
        
        const crownBadge = friend.corona 
            ? `<img src="${friend.corona}" class="crown-badge" alt="Insignia">` 
            : '';

        friendCard.innerHTML = `
            <div class="friend-info">
                <img src="${friend.avatar}" alt="Avatar" class="profile-pic">
                <div>
                    <div class="name-container">
                        <strong>${friend.nombre}</strong>
                        ${crownBadge}
                    </div>
                    
                    <span style="font-size: 12px; color: #ccc; display: flex; align-items: center; margin-top: 2px;">
                        <span style="display:inline-block; width:8px; height:8px; background:${statusColor}; border-radius:50%; margin-right:5px;"></span>
                        ${friend.status === 'online' ? 'En línea' : 'Desconectado'}
                    </span>
                </div>
            </div>
            <div class="friend-actions">
                <button title="Enviar mensaje" onclick="openChat(${friend.id})">💬</button>
                <button class="delete-friend" title="Eliminar amigo" onclick="openDeleteModal(${friend.id})">⋯</button>
            </div>
        `;
        grid.appendChild(friendCard);
    });

    if(filteredFriends.length === 0) {
        grid.innerHTML = "<p style='text-align:center; padding:20px;'>No se encontraron amigos.</p>";
    }
}

function renderChatList() {
    const container = document.getElementById('chat-list-container');
    container.innerHTML = "";

    amigosData.forEach(friend => {
        const item = document.createElement('div');
        item.className = 'user';
        item.onclick = () => openChat(friend.id);

        const crownBadge = friend.corona 
            ? `<img src="${friend.corona}" class="crown-badge" alt="Insignia">` 
            : '';

        item.innerHTML = `
            <img src="${friend.avatar}" alt="Avatar" class="profile-pic">
            <div class="name-container">
                <span>${friend.nombre}</span>
                ${crownBadge}
            </div>
        `;
        container.appendChild(item);
    });
}

// ... (Resto de funciones: filterFriends, searchFriends se mantienen igual) ...

// --- LÓGICA DE CHAT ---

function openChat(userId) {
    const user = amigosData.find(u => u.id === userId);
    if(!user) return;

    const crownBadge = user.corona 
        ? `<img src="${user.corona}" class="crown-badge" alt="Insignia">` 
        : '';

    // AÑADIDA CLASE profile-pic AL AVATAR DEL CHAT
    document.getElementById('chat-current-avatar').innerHTML = `<img src="${user.avatar}" class="profile-pic" style="width:100%; height:100%; border-radius:50%;">`;
    
    document.getElementById('chat-current-name').innerHTML = `
        <div class="name-container">
            ${user.nombre} ${crownBadge}
        </div>
    `;

    document.getElementById('chat-current-status').innerText = user.status === 'online' ? 'En línea' : 'Desconectado';
    document.getElementById('chat-current-status').style.color = user.status === 'online' ? '#00ff26' : 'gray';
    
    // ... (Resto de la función openChat y mensajes simulados se mantiene igual) ...
    const chatBody = document.getElementById('chat-messages');
    chatBody.innerHTML = `
        <div class="message received">
            <div class="message-content" style="background:#1a2cbd; border-radius:10px; padding:10px;">
                <p style="margin:0;">Hola ${user.nombre}, ¿cómo estás?</p>
                <span style="font-size:10px; opacity:0.7;">10:00 AM</span>
            </div>
        </div>
        <div class="message sent" style="align-self: flex-end;">
             <div class="message-content" style="background:#0f1b73; border-radius:10px; padding:10px;">
                <p style="margin:0;">¡Todo listo para la quiniela!</p>
                <span style="font-size:10px; opacity:0.7;">10:05 AM</span>
            </div>
        </div>
    `;

    document.getElementById('friends-view').classList.add('hidden');
    document.getElementById('chat-view').classList.remove('hidden');
}

// ... (Resto del archivo: closeChat, sendMessage, Modales, etc. se mantiene igual) ...

function closeChat() {
    document.getElementById('chat-view').classList.add('hidden');
    document.getElementById('friends-view').classList.remove('hidden');
    document.getElementById('chat-options-menu').classList.add('hidden');
}

function toggleChatOptions() {
    const menu = document.getElementById('chat-options-menu');
    menu.classList.toggle('hidden');
}

function sendMessage() {
    const input = document.getElementById('message-input');
    const text = input.value;
    if(text.trim() === "") return;

    const chatBody = document.getElementById('chat-messages');
    const msgDiv = document.createElement('div');
    msgDiv.className = "message sent";
    msgDiv.style.alignSelf = "flex-end";
    msgDiv.innerHTML = `
        <div class="message-content" style="background:#0f1b73; border-radius:10px; padding:10px;">
            <p style="margin:0;">${text}</p>
            <span style="font-size:10px; opacity:0.7;">Ahora</span>
        </div>
    `;
    chatBody.appendChild(msgDiv);
    input.value = "";
    chatBody.scrollTop = chatBody.scrollHeight;
}

// --- MODALES: AÑADIR Y ELIMINAR ---

function openAddFriendModal() {
    document.getElementById('addFriendModal').classList.remove('hidden');
}

function closeAddFriendModal() {
    document.getElementById('addFriendModal').classList.add('hidden');
}

function openDeleteModal(id) {
    friendToDeleteId = id; 
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    friendToDeleteId = null; 
}

function confirmDeleteFriend() {
    if (friendToDeleteId !== null) {
        const index = amigosData.findIndex(f => f.id === friendToDeleteId);
        if (index !== -1) {
            amigosData.splice(index, 1); 
            renderFriends(currentFilter); 
            renderChatList();
        }
        closeDeleteModal(); 
    }
}