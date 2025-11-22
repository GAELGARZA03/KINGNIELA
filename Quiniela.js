document.addEventListener('DOMContentLoaded', () => {

    // ==========================================================================
    // 1. DATOS SIMULADOS (MOCK DATA)
    // ==========================================================================

    // Usuario Logueado (Tú)
    const usuarioLogueado = {
        id: 99, 
        nombre: "Tú (Usuario)", 
        avatar: "Imagenes/I_Perfil.png", 
        corona: "Imagenes/CoronaDiamante1.png"
    };

    // Lista de Amigos / Miembros (Con datos para clasificación)
    const miembrosData = [
        { id: 1, nombre: "Juan Pérez", avatar: "Imagenes/I_Perfil.png", corona: "Imagenes/CoronaOro1.png", pts: 150, jornadaPts: 25 },
        { id: 2, nombre: "Carlos López", avatar: "Imagenes/I_Perfil.png", corona: null, pts: 120, jornadaPts: 10 },
        { id: 3, nombre: "Ana Torres", avatar: "Imagenes/I_Perfil.png", corona: "Imagenes/CoronaPlata1.png", pts: 180, jornadaPts: 30 },
        { id: 4, nombre: "Luis Gomez", avatar: "Imagenes/I_Perfil.png", corona: "Imagenes/CoronaPlata1.png", pts: 90, jornadaPts: 15 },
        { id: 5, nombre: "Maria Ruiz", avatar: "Imagenes/I_Perfil.png", corona: "Imagenes/CoronaBronce1.png", pts: 200, jornadaPts: 40 },
        usuarioLogueado // Nos incluimos para la tabla
    ];

    // Mis Quinielas (Grupos a los que pertenezco)
    const misQuinielas = [
        { 
            id: 101, 
            nombre: "Mundialitos 2026", 
            tipo: "kingniela", 
            dificultad: "facil", 
            codigo: "KNG26A", 
            foto: "Imagenes/mundial_2026.png", 
            descripcion: "Quiniela tranqui para el mundial. Solo resultados (G/E/P)." 
        },
        { 
            id: 102, 
            nombre: "Liga Pro Expertos", 
            tipo: "kingniela", 
            dificultad: "dificil", 
            codigo: "PROEXP", 
            foto: "Imagenes/crown.png", 
            descripcion: "Solo para verdaderos conocedores. Marcador exacto y goleadores." 
        },
        { 
            id: 103, 
            nombre: "Fantasy League", 
            tipo: "clasico", 
            dificultad: null, 
            codigo: "FNTSTY", 
            foto: "Imagenes/I_Perfil.png", 
            descripcion: "Arma tu 11 ideal con el presupuesto disponible." 
        }
    ];

    // Datos de Partidos (Para modos Kingniela)
    const datosJornadas = {
        1: [
            { id: 'm1', local: 'MEX', localLogo: 'Imagenes/mexico.png', visit: 'BRA', visitLogo: 'Imagenes/brasil.png', fecha: '25 Nov - 14:00', pts: 5 },
            { id: 'm2', local: 'ARG', localLogo: 'Imagenes/argentina.png', visit: 'FRA', visitLogo: 'Imagenes/francia.png', fecha: '25 Nov - 16:00', pts: 5 }
        ],
        2: [
            { id: 'm3', local: 'ESP', localLogo: 'Imagenes/espana.png', visit: 'GER', visitLogo: 'Imagenes/alemania.png', fecha: '01 Dic - 12:00', pts: 5 }
        ]
    };

    // Jugadores para Goleadores (Modo Difícil)
    const jugadoresGoleadores = [
        { id: 'p1', nombre: 'S. Giménez (MEX)' }, 
        { id: 'p2', nombre: 'Vini Jr (BRA)' },
        { id: 'p3', nombre: 'L. Messi (ARG)' }, 
        { id: 'p4', nombre: 'K. Mbappé (FRA)' }
    ];

    // Jugadores para Fantasy (Modo Clásico)
    const jugadoresFantasy = {
        gk: [ 
            { id: 'f1', nombre: 'G. Ochoa', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 5.0 },
            { id: 'f2', nombre: 'Alisson', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 6.5 }
        ],
        def: [
            { id: 'f3', nombre: 'C. Montes', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 4.5 },
            { id: 'f4', nombre: 'Marquinhos', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 6.0 },
            { id: 'f4b', nombre: 'Rudiger', equipo: 'GER', foto: 'Imagenes/I_Perfil.png', costo: 6.0 }
        ],
        mid: [
            { id: 'f5', nombre: 'E. Álvarez', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 5.5 },
            { id: 'f6', nombre: 'Casemiro', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 7.0 }
        ],
        fwd: [
            { id: 'f7', nombre: 'H. Lozano', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 8.0 },
            { id: 'f8', nombre: 'Neymar Jr', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 9.5 }
        ]
    };

    // Mensajes Chat
    let chatMessagesData = [
        { userId: 1, text: "¡Hola a todos! ¿Listos para la jornada?", time: "10:00 AM" },
        { userId: 3, text: "¡Siii! Ya hice mis predicciones.", time: "10:05 AM" },
        { userId: 99, text: "Yo voy a esperar hasta el último momento jaja.", time: "10:10 AM" }
    ];


    // ==========================================================================
    // 2. REFERENCIAS DOM Y VARIABLES
    // ==========================================================================
    // Vistas y Navegación
    const sidebarList = document.getElementById('sidebar-quinielas-list');
    const mainContentView = document.getElementById('main-menu-view'); // Contenedor de Crear/Unirse
    const quinielaListView = document.getElementById('quiniela-list-view'); // Lista "Mis Quinielas" (Home)
    const createQuinielaView = document.getElementById('create-quiniela-view'); // Formulario Crear
    const groupView = document.getElementById('quiniela-group-view'); // Vista Grupo
    const crearQuinielaBtnSidebar = document.getElementById('crear__quiniela'); // Botón +

    // Elementos Grupo
    const groupHeaderImg = document.getElementById('group-header-img');
    const groupHeaderName = document.getElementById('group-header-name');
    const groupHeaderCode = document.getElementById('group-header-code');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const btnGroupConfig = document.getElementById('btn-group-config');

    // Elementos Crear Quiniela
    const btnShowCreate = document.getElementById('btn-show-create');
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const radioKingniela = document.getElementById('tipo-kingniela');
    const radioClasico = document.getElementById('tipo-clasico');
    const difficultySection = document.getElementById('difficulty-section');
    const btnOpenMembers = document.getElementById('btn-open-members');
    const btnCreateFinal = document.getElementById('btn-create-final');

    // Modales
    const membersModal = document.getElementById('membersModal');
    const membersListContainer = document.getElementById('members-list-container');
    const closeMembers = document.getElementById('close-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    const successModal = document.getElementById('successModal');
    const btnFinish = document.getElementById('btn-finish');
    const btnCopyCode = document.getElementById('btn-copy-code');
    const generatedCodeDisplay = document.getElementById('generated-code');

    // Variables de Estado
    let quinielaActiva = null;
    let fantasySlotSelected = null;


    // ==========================================================================
    // 3. LÓGICA BARRA LATERAL Y NAVEGACIÓN PRINCIPAL
    // ==========================================================================

    // Renderizar botones cuadrados 'Q' en el sidebar
    function renderSidebar() {
        sidebarList.innerHTML = ""; 
        misQuinielas.forEach(q => {
            const li = document.createElement('li');
            li.className = 'quiniela-sidebar-item';
            li.textContent = q.nombre.charAt(0).toUpperCase();
            li.title = q.nombre;
            li.style.backgroundColor = getRandomColor();
            
            li.addEventListener('click', () => {
                // Activar visualmente
                document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
                li.classList.add('active-group');
                crearQuinielaBtnSidebar.style.backgroundColor = 'white'; // Desactivar +
                
                loadGroupView(q.id);
            });
            sidebarList.appendChild(li);
        });
    }

    // Volver al menú principal (Crear/Unirse) al dar click en +
    crearQuinielaBtnSidebar.addEventListener('click', () => {
        groupView.classList.add('hidden');
        mainContentView.classList.remove('hidden');
        
        // Resetear vistas internas del menú principal
        quinielaListView.classList.remove('hidden');
        createQuinielaView.classList.add('hidden');

        // Estilos visuales sidebar
        document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
        crearQuinielaBtnSidebar.style.backgroundColor = '#ffdd00';
    });

    function getRandomColor() {
        const h = Math.floor(Math.random() * 360);
        return `hsl(${h}, 70%, 80%)`;
    }


    // ==========================================================================
    // 4. LÓGICA VISTA DE GRUPO
    // ==========================================================================

    function loadGroupView(id) {
        quinielaActiva = misQuinielas.find(q => q.id === id);
        if(!quinielaActiva) return;

        mainContentView.classList.add('hidden');
        groupView.classList.remove('hidden');

        // Cargar Header
        groupHeaderName.textContent = quinielaActiva.nombre;
        groupHeaderCode.textContent = quinielaActiva.codigo;
        groupHeaderImg.src = quinielaActiva.foto;

        // Resetear Pestañas
        clickTab('tab-kingnielar'); // Por defecto

        // Cargar contenidos iniciales
        renderKingnielarTab(quinielaActiva);
        // Las otras pestañas se cargan al hacer click en ellas para optimizar, o aquí directo:
        renderClasificacionTab(); 
        renderConfiguracionTab(quinielaActiva);
    }

    // Botón Configuración del Header
    btnGroupConfig.addEventListener('click', () => clickTab('tab-configuracion'));

    // Manejo de Pestañas
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => clickTab(btn.dataset.tab));
    });

    function clickTab(tabId) {
        tabButtons.forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if(activeBtn) activeBtn.classList.add('active');

        tabContents.forEach(c => c.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');

        // Cargas específicas
        if(tabId === 'tab-chat') renderChatTab();
    }


    // ==========================================================================
    // 5. PESTAÑA KINGNIELAR (Modos de Juego)
    // ==========================================================================
    const kingnielarContentArea = document.getElementById('kingnielar-content-area');
    const jornadaSelect = document.getElementById('jornada-select');

    function renderKingnielarTab(quiniela) {
        kingnielarContentArea.innerHTML = '';
        jornadaSelect.innerHTML = '';

        // Llenar selector jornadas
        [1, 2].forEach(num => {
            const opt = document.createElement('option');
            opt.value = num;
            opt.textContent = `Jornada ${num}`;
            jornadaSelect.appendChild(opt);
        });

        jornadaSelect.onchange = () => renderMatches(quiniela, jornadaSelect.value);
        renderMatches(quiniela, 1); // Cargar jornada 1
    }

    function renderMatches(quiniela, jornadaId) {
        kingnielarContentArea.innerHTML = '';
        
        // CASO FANTASY
        if (quiniela.tipo === 'clasico') {
            renderFantasyInterface();
            return;
        }

        // CASO KINGNIELA (Partidos)
        const partidos = datosJornadas[jornadaId];
        if(!partidos) {
            kingnielarContentArea.innerHTML = '<p>Sin partidos disponibles.</p>';
            return;
        }

        partidos.forEach(p => {
            const card = document.createElement('div');
            card.className = 'match-card';
            let inputHtml = '';

            // Decidir inputs según dificultad
            if (quiniela.dificultad === 'facil') {
                inputHtml = `
                    <div class="prediction-buttons">
                        <button class="btn-predict" onclick="selectPred(this)">L</button>
                        <button class="btn-predict btn-draw" onclick="selectPred(this)">Empate</button>
                        <button class="btn-predict" onclick="selectPred(this)">V</button>
                    </div>
                `;
            } else {
                // Medio y Difícil
                inputHtml = `
                    <div class="score-inputs-container">
                        <input type="number" class="score-input" placeholder="-">
                        <span>-</span>
                        <input type="number" class="score-input" placeholder="-">
                    </div>
                `;
                
                // Solo Difícil: Goleadores
                if (quiniela.dificultad === 'dificil') {
                    let opts = '<option value="">Goleador...</option>';
                    jugadoresGoleadores.forEach(j => opts += `<option value="${j.id}">${j.nombre}</option>`);
                    inputHtml += `
                        <div class="scorers-section">
                            <div class="scorer-select-group">
                                <select class="scorer-select">${opts}</select>
                                <select class="scorer-select">${opts}</select>
                            </div>
                        </div>
                    `;
                }
            }

            card.innerHTML = `
                <div class="match-header"><span>${p.fecha}</span><span class="match-points">+${p.pts} Pts</span></div>
                <div class="match-teams-area">
                    <div class="team-info"><img src="${p.localLogo}" class="team-logo"><div class="team-abbr">${p.local}</div></div>
                    <div class="match-center-area">${inputHtml}</div>
                    <div class="team-info"><img src="${p.visitLogo}" class="team-logo"><div class="team-abbr">${p.visit}</div></div>
                </div>
            `;
            kingnielarContentArea.appendChild(card);
        });
    }

    // Helper global para botones
    window.selectPred = function(btn) {
        const siblings = btn.parentNode.children;
        for(let s of siblings) s.classList.remove('selected');
        btn.classList.add('selected');
    }


    // --- RENDER FANTASY ---
    function renderFantasyInterface() {
        kingnielarContentArea.innerHTML = `
            <div class="fantasy-container">
                <div class="fantasy-field-area">
                    <div class="player-slot slot-gk" data-pos="gk"><span class="slot-label">POR</span></div>
                    <div class="player-slot slot-def-l" data-pos="def"><span class="slot-label">DEF</span></div>
                    <div class="player-slot slot-def-cl" data-pos="def"><span class="slot-label">DEF</span></div>
                    <div class="player-slot slot-def-cr" data-pos="def"><span class="slot-label">DEF</span></div>
                    <div class="player-slot slot-def-r" data-pos="def"><span class="slot-label">DEF</span></div>
                    <div class="player-slot slot-mid-l" data-pos="mid"><span class="slot-label">MED</span></div>
                    <div class="player-slot slot-mid-c" data-pos="mid"><span class="slot-label">MED</span></div>
                    <div class="player-slot slot-mid-r" data-pos="mid"><span class="slot-label">MED</span></div>
                    <div class="player-slot slot-fwd-l" data-pos="fwd"><span class="slot-label">DEL</span></div>
                    <div class="player-slot slot-fwd-c" data-pos="fwd"><span class="slot-label">DEL</span></div>
                    <div class="player-slot slot-fwd-r" data-pos="fwd"><span class="slot-label">DEL</span></div>
                </div>
                <div class="fantasy-selection-area">
                    <h4>Jugadores</h4>
                    <div class="fantasy-players-list" id="fantasy-players-list">
                        <p style="text-align:center; color:#ccc; padding:20px;">Selecciona una posición</p>
                    </div>
                </div>
            </div>
        `;

        document.querySelectorAll('.player-slot').forEach(slot => {
            slot.addEventListener('click', () => {
                document.querySelectorAll('.player-slot').forEach(s => s.classList.remove('active-slot'));
                slot.classList.add('active-slot');
                fantasySlotSelected = slot;
                loadFantasyList(slot.dataset.pos);
            });
        });
    }

    function loadFantasyList(pos) {
        const list = document.getElementById('fantasy-players-list');
        list.innerHTML = '';
        const players = jugadoresFantasy[pos];
        if(!players) return;

        players.forEach(p => {
            const div = document.createElement('div');
            div.className = 'fantasy-player-card';
            div.innerHTML = `
                <img src="${p.foto}" class="profile-pic">
                <div class="player-data"><p>${p.nombre}</p><span>${p.equipo}</span></div>
                <div class="player-cost">$${p.costo}M</div>
            `;
            div.onclick = () => {
                if(fantasySlotSelected) {
                    fantasySlotSelected.innerHTML = `<img src="${p.foto}">`;
                    fantasySlotSelected.classList.add('filled');
                    fantasySlotSelected.classList.remove('active-slot');
                    list.innerHTML = '<p style="text-align:center; color:#ffdd00; padding:20px;">¡Asignado!</p>';
                    fantasySlotSelected = null;
                }
            };
            list.appendChild(div);
        });
    }


    // ==========================================================================
    // 6. PESTAÑA CLASIFICACIÓN
    // ==========================================================================
    function renderClasificacionTab() {
        document.getElementById('total-participants').textContent = miembrosData.length;
        const userBody = document.getElementById('user-rank-body');
        const generalBody = document.getElementById('general-rank-body');

        const sorted = [...miembrosData].sort((a, b) => b.pts - a.pts);

        const rowHTML = (m, i) => {
            const crown = m.corona ? `<img src="${m.corona}" class="crown-badge">` : '';
            // Clase 'profile-pic' clave para el CSS
            return `
                <tr>
                    <td>#${i+1}</td>
                    <td class="user-cell">
                        <img src="${m.avatar}" class="profile-pic">
                        <div class="name-container"><span>${m.nombre}</span>${crown}</div>
                    </td>
                    <td><strong>${m.pts}</strong></td>
                    <td style="color:#ffdd00">+${m.jornadaPts}</td>
                </tr>
            `;
        };

        generalBody.innerHTML = sorted.map((m, i) => rowHTML(m, i)).join('');
        
        const myIndex = sorted.findIndex(m => m.id === usuarioLogueado.id);
        if(myIndex !== -1) userBody.innerHTML = rowHTML(sorted[myIndex], myIndex);
    }


    // ==========================================================================
    // 7. PESTAÑA CHAT GRUPAL
    // ==========================================================================
    function renderChatTab() {
        const chatBody = document.getElementById('group-chat-messages');
        chatBody.innerHTML = '';

        chatMessagesData.forEach(msg => {
            const sender = miembrosData.find(m => m.id === msg.userId);
            if(!sender) return;

            const isMe = sender.id === usuarioLogueado.id;
            const div = document.createElement('div');
            div.className = `message ${isMe ? 'sent' : 'received'}`;
            if(isMe) div.style.alignSelf = 'flex-end';

            let infoHtml = '';
            if(!isMe) {
                const crown = sender.corona ? `<img src="${sender.corona}" class="crown-badge">` : '';
                infoHtml = `
                    <div style="display:flex; align-items:center; margin-bottom:5px;">
                        <img src="${sender.avatar}" class="profile-pic" style="width:25px; height:25px; margin-right:8px;">
                        <div class="name-container" style="font-size:12px; color:#ffdd00;">
                            <span>${sender.nombre}</span>${crown}
                        </div>
                    </div>
                `;
            }

            div.innerHTML = `
                <div class="message-content" style="background:${isMe ? '#0f1b73' : '#1a2cbd'}; border-radius:10px; padding:10px;">
                    ${infoHtml}
                    <p style="margin:0">${msg.text}</p>
                    <span style="font-size:10px; opacity:0.7; display:block; text-align:right; margin-top:5px;">${msg.time}</span>
                </div>
            `;
            chatBody.appendChild(div);
        });
    }

    window.sendGroupMessage = function() {
        const inp = document.getElementById('group-message-input');
        if(inp.value.trim() === "") return;
        chatMessagesData.push({ userId: usuarioLogueado.id, text: inp.value, time: "Ahora" });
        renderChatTab();
        inp.value = "";
        const chatBody = document.getElementById('group-chat-messages');
        chatBody.scrollTop = chatBody.scrollHeight;
    }


    // ==========================================================================
    // 8. PESTAÑA CONFIGURACIÓN
    // ==========================================================================
    function renderConfiguracionTab(q) {
        document.getElementById('config-group-img').src = q.foto;
        document.getElementById('config-group-name').value = q.nombre;
        document.getElementById('config-group-desc').value = q.descripcion;

        const list = document.getElementById('config-members-list');
        list.innerHTML = '';

        const others = miembrosData.filter(m => m.id !== usuarioLogueado.id);
        others.forEach(m => {
            const crown = m.corona ? `<img src="${m.corona}" class="crown-badge">` : '';
            const item = document.createElement('div');
            item.className = 'member-manage-item';
            item.innerHTML = `
                <div class="member-info-block">
                    <img src="${m.avatar}" class="profile-pic" style="width:35px; height:35px;">
                    <div class="name-container"><span>${m.nombre}</span>${crown}</div>
                </div>
                <button class="btn-remove-member" onclick="alert('Eliminar a ${m.nombre}')">Eliminar</button>
            `;
            list.appendChild(item);
        });
    }


    // ==========================================================================
    // 9. LÓGICA CREACIÓN DE QUINIELA (Formulario y Modales)
    // ==========================================================================
    
    // Navegación Menú Crear
    btnShowCreate.addEventListener('click', () => {
        quinielaListView.classList.add('hidden');
        createQuinielaView.classList.remove('hidden');
    });

    btnCancelCreate.addEventListener('click', () => {
        createQuinielaView.classList.add('hidden');
        quinielaListView.classList.remove('hidden');
    });

    // Toggle Dificultad
    function toggleDifficulty() {
        if(radioKingniela.checked) difficultySection.classList.remove('hidden');
        else difficultySection.classList.add('hidden');
    }
    radioKingniela.addEventListener('change', toggleDifficulty);
    radioClasico.addEventListener('change', toggleDifficulty);

    // Modal Miembros
    function renderMembersModalList() {
        membersListContainer.innerHTML = ""; 
        // Filtramos al usuario logueado para no auto-invitarse
        const friendsToInvite = miembrosData.filter(m => m.id !== usuarioLogueado.id);

        friendsToInvite.forEach(f => {
            const crown = f.corona ? `<img src="${f.corona}" class="crown-badge" alt="Insignia">` : '';
            const item = document.createElement('div');
            item.className = "member-item";
            item.innerHTML = `
                <input type="checkbox" id="friend${f.id}">
                <label for="friend${f.id}">
                    <img src="${f.avatar}" class="profile-pic">
                    <div class="name-container">
                        <span>${f.nombre}</span>${crown}
                    </div>
                </label>
            `;
            membersListContainer.appendChild(item);
        });
    }

    btnOpenMembers.addEventListener('click', () => {
        renderMembersModalList();
        membersModal.classList.remove('hidden');
    });

    const closeModal = () => membersModal.classList.add('hidden');
    closeMembers.addEventListener('click', closeModal);
    btnConfirmMembers.addEventListener('click', () => {
        closeModal();
        alert("Amigos seleccionados.");
    });

    // Crear y Finalizar
    btnCreateFinal.addEventListener('click', () => {
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let code = "";
        for(let i=0; i<6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
        
        generatedCodeDisplay.textContent = code;
        createQuinielaView.classList.add('hidden');
        successModal.classList.remove('hidden');
    });

    btnCopyCode.addEventListener('click', () => {
        const txt = generatedCodeDisplay.textContent;
        navigator.clipboard.writeText(txt).then(() => {
            const prev = btnCopyCode.textContent;
            btnCopyCode.textContent = "✅";
            setTimeout(() => btnCopyCode.textContent = prev, 1500);
        });
    });

    btnFinish.addEventListener('click', () => {
        successModal.classList.add('hidden');
        quinielaListView.classList.remove('hidden');
        // Aquí podrías agregar la nueva quiniela al array 'misQuinielas' y llamar renderSidebar()
        alert("Quiniela creada (Simulación)");
    });


    // INICIO
    renderSidebar();
});