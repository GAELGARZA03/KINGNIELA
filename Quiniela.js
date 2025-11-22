document.addEventListener('DOMContentLoaded', () => {

    // ==========================================================================
    // 1. DATOS SIMULADOS (MOCK DATA)
    // ==========================================================================

    // Usuario Logueado (Tú) - CORREGIDO: Puntos inicializados
    const usuarioLogueado = {
        id: 99, 
        nombre: "Tú (Usuario)", 
        avatar: "Imagenes/I_Perfil.png", 
        corona: "Imagenes/CoronaDiamante1.png",
        pts: 0,
        jornadaPts: 0
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

    // Mis Quinielas (Grupos en la barra lateral) - AÑADIDA NUEVA QUINIELA PROFESIONAL
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
            id: 104, 
            nombre: "Liga Profesional", 
            tipo: "kingniela", 
            dificultad: "medio", 
            codigo: "PRO123", 
            foto: "Imagenes/mundial_2026.png", 
            descripcion: "Pronostica el marcador exacto." 
        },
        { 
            id: 103, 
            nombre: "Fantasy League", 
            tipo: "clasico", 
            dificultad: null, 
            codigo: "FNTSTY", 
            foto: "Imagenes/I_Perfil.png", 
            descripcion: "Arma tu 11 ideal." 
        }
    ];

    // Datos de Partidos (Jornadas)
    const datosJornadas = {
        1: [
            { id: 'm1', local: 'MEX', localLogo: 'Imagenes/mundial_2026.png', visit: 'BRA', visitLogo: 'Imagenes/mundial_2026.png', fecha: '25 Nov - 14:00', pts: 5 },
            { id: 'm2', local: 'ARG', localLogo: 'Imagenes/mundial_2026.png', visit: 'FRA', visitLogo: 'Imagenes/mundial_2026.png', fecha: '25 Nov - 16:00', pts: 5 }
        ],
        2: [
            { id: 'm3', local: 'ESP', localLogo: 'Imagenes/mundial_2026.png', visit: 'GER', visitLogo: 'Imagenes/mundial_2026.png', fecha: '01 Dic - 12:00', pts: 5 }
        ]
    };

    // Jugadores Goleadores
    const jugadoresGoleadores = [
        { id: 'p1', nombre: 'S. Giménez (MEX)' }, 
        { id: 'p2', nombre: 'Vini Jr (BRA)' },
        { id: 'p3', nombre: 'L. Messi (ARG)' }, 
        { id: 'p4', nombre: 'K. Mbappé (FRA)' }
    ];

    // Jugadores Fantasy
    const jugadoresFantasy = {
        gk: [ { id: 'f1', nombre: 'G. Ochoa', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 5.0 }, { id: 'f2', nombre: 'Alisson', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 6.5 } ],
        def: [ { id: 'f3', nombre: 'C. Montes', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 4.5 }, { id: 'f4', nombre: 'Marquinhos', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 6.0 } ],
        mid: [ { id: 'f5', nombre: 'E. Álvarez', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 5.5 }, { id: 'f6', nombre: 'Casemiro', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 7.0 } ],
        fwd: [ { id: 'f7', nombre: 'H. Lozano', equipo: 'MEX', foto: 'Imagenes/I_Perfil.png', costo: 8.0 }, { id: 'f8', nombre: 'Neymar Jr', equipo: 'BRA', foto: 'Imagenes/I_Perfil.png', costo: 9.5 } ]
    };

    // Mensajes Chat
    let chatMessagesData = [
        { userId: 1, text: "¡Hola a todos!", time: "10:00 AM" },
        { userId: 3, text: "¡Listos para ganar!", time: "10:05 AM" }
    ];


    // ==========================================================================
    // 2. REFERENCIAS DOM (ELEMENTOS HTML)
    // ==========================================================================
    
    // Vistas Principales
    const mainContentView = document.getElementById('main-menu-view');
    const quinielaListView = document.getElementById('quiniela-list-view');
    const createQuinielaView = document.getElementById('create-quiniela-view');
    const groupView = document.getElementById('quiniela-group-view');
    
    // Sidebar
    const sidebarList = document.getElementById('sidebar-quinielas-list');
    const crearQuinielaBtnSidebar = document.getElementById('crear__quiniela'); // El círculo +

    // Botones Menú Principal
    const btnShowCreate = document.getElementById('btn-show-create'); // Botón amarillo "Crear nueva quiniela"
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const btnCreateFinal = document.getElementById('btn-create-final');

    // Formulario Crear
    const radioKingniela = document.getElementById('tipo-kingniela');
    const radioClasico = document.getElementById('tipo-clasico');
    const difficultySection = document.getElementById('difficulty-section');
    const btnOpenMembers = document.getElementById('btn-open-members');

    // Modales
    const membersModal = document.getElementById('membersModal');
    const membersListContainer = document.getElementById('members-list-container');
    const closeMembers = document.getElementById('close-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    
    const successModal = document.getElementById('successModal');
    const btnFinish = document.getElementById('btn-finish');
    const btnCopyCode = document.getElementById('btn-copy-code');
    const generatedCodeDisplay = document.getElementById('generated-code');

    // Elementos Vista Grupo
    const groupHeaderImg = document.getElementById('group-header-img');
    const groupHeaderName = document.getElementById('group-header-name');
    const groupHeaderCode = document.getElementById('group-header-code');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    const btnGroupConfig = document.getElementById('btn-group-config');
    
    // Elementos Kingnielar
    const kingnielarContentArea = document.getElementById('kingnielar-content-area');
    const jornadaSelect = document.getElementById('jornada-select');

    let quinielaActiva = null;
    let fantasySlotSelected = null;


    // ==========================================================================
    // 3. LÓGICA: CREAR NUEVA QUINIELA
    // ==========================================================================

    // ABRIR FORMULARIO DE CREAR
    if(btnShowCreate) {
        btnShowCreate.addEventListener('click', () => {
            quinielaListView.classList.add('hidden');
            createQuinielaView.classList.remove('hidden');
        });
    }

    // CANCELAR CREACIÓN
    if(btnCancelCreate) {
        btnCancelCreate.addEventListener('click', () => {
            createQuinielaView.classList.add('hidden');
            quinielaListView.classList.remove('hidden');
        });
    }

    // MOSTRAR/OCULTAR DIFICULTAD
    function toggleDifficulty() {
        if(radioKingniela.checked) difficultySection.classList.remove('hidden');
        else difficultySection.classList.add('hidden');
    }
    if(radioKingniela) radioKingniela.addEventListener('change', toggleDifficulty);
    if(radioClasico) radioClasico.addEventListener('change', toggleDifficulty);

    // --- MODAL MIEMBROS ---
    function renderMembersModalList() {
        membersListContainer.innerHTML = ""; 
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

    if(btnOpenMembers) {
        btnOpenMembers.addEventListener('click', () => {
            renderMembersModalList();
            membersModal.classList.remove('hidden');
        });
    }

    const closeModal = () => membersModal.classList.add('hidden');
    if(closeMembers) closeMembers.addEventListener('click', closeModal);
    if(btnConfirmMembers) btnConfirmMembers.addEventListener('click', () => {
        closeModal();
        alert("Amigos seleccionados.");
    });

    // --- FINALIZAR CREACIÓN ---
    if(btnCreateFinal) {
        btnCreateFinal.addEventListener('click', () => {
            const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            let code = "";
            for(let i=0; i<6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));
            
            generatedCodeDisplay.textContent = code;
            createQuinielaView.classList.add('hidden');
            successModal.classList.remove('hidden');
        });
    }

    if(btnCopyCode) {
        btnCopyCode.addEventListener('click', () => {
            const txt = generatedCodeDisplay.textContent;
            navigator.clipboard.writeText(txt).then(() => {
                const prev = btnCopyCode.textContent;
                btnCopyCode.textContent = "✅";
                setTimeout(() => btnCopyCode.textContent = prev, 1500);
            });
        });
    }

    if(btnFinish) {
        btnFinish.addEventListener('click', () => {
            successModal.classList.add('hidden');
            quinielaListView.classList.remove('hidden');
            alert("Quiniela creada con éxito.");
        });
    }


    // ==========================================================================
    // 4. LÓGICA: BARRA LATERAL Y GRUPOS
    // ==========================================================================

    function renderSidebar() {
        sidebarList.innerHTML = ""; 
        
        // 1. CREAR BOTÓN '+' (DINÁMICO)
        const liAdd = document.createElement('li');
        liAdd.id = 'crear__quiniela'; // Asignar ID para que tome el CSS
        liAdd.textContent = '+';
        
        // Evento del botón +: Volver al menú principal
        liAdd.addEventListener('click', () => {
            groupView.classList.add('hidden');
            mainContentView.classList.remove('hidden');
            
            quinielaListView.classList.remove('hidden');
            createQuinielaView.classList.add('hidden');

            // Resetear estilos
            document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
            liAdd.style.backgroundColor = '#ffdd00'; // Amarillo (CSS se encarga, pero forzamos por si acaso)
            liAdd.style.color = '#001f5c';
        });

        sidebarList.appendChild(liAdd);

        // 2. CREAR BOTONES DE GRUPOS
        misQuinielas.forEach(q => {
            const li = document.createElement('li');
            li.className = 'quiniela-sidebar-item';
            li.textContent = q.nombre.charAt(0).toUpperCase();
            li.title = q.nombre;
            li.style.backgroundColor = getRandomColor();
            
            li.addEventListener('click', () => {
                document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
                li.classList.add('active-group');
                
                // Desactivar visualmente el +
                const btnPlus = document.getElementById('crear__quiniela');
                if(btnPlus) {
                    btnPlus.style.backgroundColor = 'white';
                    btnPlus.style.color = '#001f5c';
                }
                
                loadGroupView(q.id);
            });
            sidebarList.appendChild(li);
        });
    }

    function getRandomColor() {
        const h = Math.floor(Math.random() * 360);
        return `hsl(${h}, 70%, 80%)`;
    }


    // ==========================================================================
    // 5. LÓGICA: VISTA DE GRUPO
    // ==========================================================================

    function loadGroupView(id) {
        quinielaActiva = misQuinielas.find(q => q.id === id);
        if(!quinielaActiva) return;

        mainContentView.classList.add('hidden');
        groupView.classList.remove('hidden');

        groupHeaderName.textContent = quinielaActiva.nombre;
        groupHeaderCode.textContent = quinielaActiva.codigo;
        groupHeaderImg.src = quinielaActiva.foto;

        clickTab('tab-kingnielar'); // Tab por defecto

        // Cargar contenidos
        renderKingnielarTab(quinielaActiva);
        renderClasificacionTab();
        renderConfiguracionTab(quinielaActiva);
    }

    if(btnGroupConfig) btnGroupConfig.addEventListener('click', () => clickTab('tab-configuracion'));

    // Tabs
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => clickTab(btn.dataset.tab));
    });

    function clickTab(tabId) {
        tabButtons.forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if(activeBtn) activeBtn.classList.add('active');

        tabContents.forEach(c => c.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');

        if(tabId === 'tab-chat') renderChatTab();
    }


    // ==========================================================================
    // 6. PESTAÑA: KINGNIELAR
    // ==========================================================================

    function renderKingnielarTab(quiniela) {
        kingnielarContentArea.innerHTML = '';
        jornadaSelect.innerHTML = '';

        // Llenar jornadas
        [1, 2].forEach(num => {
            const opt = document.createElement('option');
            opt.value = num;
            opt.textContent = `Jornada ${num}`;
            jornadaSelect.appendChild(opt);
        });

        jornadaSelect.onchange = () => renderMatches(quiniela, jornadaSelect.value);
        renderMatches(quiniela, 1);
    }

    function renderMatches(quiniela, jornadaId) {
        kingnielarContentArea.innerHTML = '';
        
        if (quiniela.tipo === 'clasico') {
            renderFantasyInterface();
            return;
        }

        const partidos = datosJornadas[jornadaId];
        if(!partidos) {
            kingnielarContentArea.innerHTML = '<p>Sin partidos.</p>';
            return;
        }

        partidos.forEach(p => {
            const card = document.createElement('div');
            card.className = 'match-card';
            let inputHtml = '';

            if (quiniela.dificultad === 'facil') {
                inputHtml = `
                    <div class="prediction-buttons">
                        <button class="btn-predict" onclick="selectPred(this)">L</button>
                        <button class="btn-predict btn-draw" onclick="selectPred(this)">Empate</button>
                        <button class="btn-predict" onclick="selectPred(this)">V</button>
                    </div>
                `;
            } else {
                inputHtml = `
                    <div class="score-inputs-container">
                        <input type="number" class="score-input" placeholder="-">
                        <span>-</span>
                        <input type="number" class="score-input" placeholder="-">
                    </div>
                `;
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

    window.selectPred = function(btn) {
        const siblings = btn.parentNode.children;
        for(let s of siblings) s.classList.remove('selected');
        btn.classList.add('selected');
    }

    // Fantasy
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
                        <p style="text-align:center; color:#ccc;">Selecciona una posición</p>
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
                    list.innerHTML = '<p style="text-align:center; color:#ffdd00;">¡Asignado!</p>';
                    fantasySlotSelected = null;
                }
            };
            list.appendChild(div);
        });
    }


    // ==========================================================================
    // 7. PESTAÑA: CLASIFICACIÓN
    // ==========================================================================
    function renderClasificacionTab() {
        document.getElementById('total-participants').textContent = miembrosData.length;
        const userBody = document.getElementById('user-rank-body');
        const generalBody = document.getElementById('general-rank-body');

        const sorted = [...miembrosData].sort((a, b) => b.pts - a.pts);

        const rowHTML = (m, i) => {
            const crown = m.corona ? `<img src="${m.corona}" class="crown-badge">` : '';
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
    // 8. PESTAÑA: CHAT
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
    // 9. PESTAÑA: CONFIGURACIÓN
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

    // INICIO
    renderSidebar();
});