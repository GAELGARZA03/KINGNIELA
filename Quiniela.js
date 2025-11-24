document.addEventListener('DOMContentLoaded', () => {

    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    const serverUrl = window.SERVER_URL || 'http://localhost:3000';
    const socket = window.socket || io(serverUrl);

    // --- REFERENCIAS DOM ---
    const mainContentView = document.getElementById('main-menu-view');
    const quinielaListView = document.getElementById('quiniela-list-view');
    const createQuinielaView = document.getElementById('create-quiniela-view');
    const groupView = document.getElementById('quiniela-group-view');
    const sidebarList = document.getElementById('sidebar-quinielas-list');
    
    const btnShowCreate = document.getElementById('btn-show-create');
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const btnCreateFinal = document.getElementById('btn-create-final');
    const btnOpenMembers = document.getElementById('btn-open-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    const btnFinish = document.getElementById('btn-finish');
    const btnJoinGroup = document.getElementById('btn-join-group');
    const inputJoinCode = document.getElementById('join-code-input');
    
    const btnGuardarPredicciones = document.querySelector('#tab-kingnielar .btn-primary');
    const groupChatBody = document.getElementById('group-chat-messages');
    const groupChatInput = document.getElementById('group-message-input');
    const btnSendGroup = document.querySelector('.chat-input-wrapper .send-btn');

    const membersModal = document.getElementById('membersModal');
    const membersListContainer = document.getElementById('members-list-container');
    const closeMembers = document.getElementById('close-members');
    const successModal = document.getElementById('successModal');
    const generatedCodeDisplay = document.getElementById('generated-code');
    
    const inpNombre = document.querySelector('#form-create-quiniela input[type="text"]');
    const radioKingniela = document.getElementById('tipo-kingniela');
    const radioClasico = document.getElementById('tipo-clasico');
    const difficultySection = document.getElementById('difficulty-section');
    const selectDifficulty = document.getElementById('select-difficulty');

    const kingnielarContentArea = document.getElementById('kingnielar-content-area');
    const jornadaSelect = document.getElementById('jornada-select');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    let amigosSeleccionados = [];
    let misQuinielas = [];
    let quinielaActiva = null;

    // Variables FANTASY
    let fantasyBudget = 100.0;
    let fantasyPointsTotal = 0; 
    let myFantasyTeam = { POR:[], DEF:[], MED:[], DEL:[] };
    let isFantasyLocked = false; // Nueva variable de control
    const FANTASY_LIMITS = { POR:1, DEF:4, MED:3, DEL:3 }; 

    // --- SOCKET LISTENERS ---
    socket.on('newGroupMessage', (msg) => {
        if (quinielaActiva && parseInt(msg.Id_Quiniela) === parseInt(quinielaActiva.Id_Quiniela)) {
            appendGroupMessage(msg);
        }
    });

    // --- CARGA INICIAL ---
    function cargarMisQuinielas() {
        fetch('php/mis_quinielas.php')
            .then(r => r.json())
            .then(data => { 
                misQuinielas = data; 
                renderSidebar(); 
            })
            .catch(e => console.error(e));
    }

    function renderSidebar() {
        sidebarList.innerHTML = ""; 
        const liAdd = document.createElement('li'); 
        liAdd.id = 'crear__quiniela'; 
        liAdd.textContent = '+';
        liAdd.addEventListener('click', () => {
            if(quinielaActiva) socket.emit('leave_group', quinielaActiva.Id_Quiniela);
            quinielaActiva = null;
            groupView.classList.add('hidden'); 
            mainContentView.classList.remove('hidden');
            quinielaListView.classList.remove('hidden'); 
            createQuinielaView.classList.add('hidden');
            document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
            liAdd.style.backgroundColor = '#ffdd00'; 
            liAdd.style.color = '#001f5c';
        });
        sidebarList.appendChild(liAdd);

        if(Array.isArray(misQuinielas)) {
            misQuinielas.forEach(q => {
                const li = document.createElement('li'); 
                li.className = 'quiniela-sidebar-item';
                li.textContent = q.Nombre_Quiniela.substring(0, 2).toUpperCase();
                li.title = q.Nombre_Quiniela;
                li.style.backgroundColor = `hsl(${Math.floor(Math.random()*360)}, 70%, 80%)`;
                li.addEventListener('click', () => {
                    document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
                    li.classList.add('active-group'); 
                    loadGroupView(q);
                });
                sidebarList.appendChild(li);
            });
        }
    }

    if(btnJoinGroup) {
        btnJoinGroup.addEventListener('click', () => {
            const codigo = inputJoinCode.value.trim();
            if(!codigo) return alert("Escribe un código");
            fetch('php/unirse_quiniela.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ codigo: codigo }) 
            })
            .then(r => r.json())
            .then(res => { 
                if(res.success) { 
                    alert(res.message); 
                    inputJoinCode.value = ""; 
                    cargarMisQuinielas(); 
                } else { 
                    alert("Error: " + res.message); 
                } 
            });
        });
    }

    function loadGroupView(quiniela) {
        if (quinielaActiva) socket.emit('leave_group', quinielaActiva.Id_Quiniela);
        quinielaActiva = quiniela;
        socket.emit('join_group', quiniela.Id_Quiniela);

        mainContentView.classList.add('hidden'); 
        groupView.classList.remove('hidden');
        
        document.getElementById('group-header-name').textContent = quiniela.Nombre_Quiniela;
        document.getElementById('group-header-code').textContent = quiniela.Codigo_Acceso;
        const img = document.getElementById('group-header-img'); 
        if(img) img.src = quiniela.Foto_Grupo || 'Imagenes/mundial_2026.png';
        
        jornadaSelect.value = 'Jornada 1'; 
        
        if (quiniela.Tipo_Quiniela === 'fantasy') {
            document.querySelector('[data-tab="tab-kingnielar"]').textContent = "Mi Equipo";
            if(btnGuardarPredicciones) btnGuardarPredicciones.style.display = 'none';
        } else {
            document.querySelector('[data-tab="tab-kingnielar"]').textContent = "Kingnielar";
            if(btnGuardarPredicciones) btnGuardarPredicciones.style.display = 'inline-block';
        }
        
        clickTab('tab-kingnielar');
    }

    function renderKingnielarTab(quiniela) {
        if(jornadaSelect.innerHTML.trim() === "") {
            ['Jornada 1', 'Jornada 2', 'Jornada 3', 'Dieciseisavos de final', 'Octavos de final', 'Cuartos de final', 'Semifinal', 'Final'].forEach(j => {
                const opt = document.createElement('option'); opt.value = j; opt.textContent = j;
                jornadaSelect.appendChild(opt);
            });
        }
        
        jornadaSelect.onchange = () => {
            if (quiniela.Tipo_Quiniela === 'fantasy') loadFantasyData(quiniela.Id_Quiniela, jornadaSelect.value);
            else cargarPartidos(quiniela.Id_Quiniela, jornadaSelect.value);
            
            if(!document.getElementById('tab-clasificacion').classList.contains('hidden')) renderClasificacionTab();
        };

        if (quiniela.Tipo_Quiniela === 'fantasy') loadFantasyData(quiniela.Id_Quiniela, jornadaSelect.value || 'Jornada 1');
        else cargarPartidos(quiniela.Id_Quiniela, jornadaSelect.value || 'Jornada 1');
    }

    // ==========================================================
    // LÓGICA FANTASY
    // ==========================================================
    function loadFantasyData(idQuiniela, jornada) {
        kingnielarContentArea.innerHTML = "<p style='color:white;text-align:center;'>Cargando mercado...</p>";
        
        fetch(`php/fantasy_data.php?id_quiniela=${idQuiniela}&jornada=${jornada}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { kingnielarContentArea.innerHTML = `<p style='color:white;text-align:center;'>${data.message}</p>`; return; }

            myFantasyTeam = { POR:[], DEF:[], MED:[], DEL:[] };
            fantasyBudget = parseFloat(data.presupuesto);
            fantasyPointsTotal = parseFloat(data.puntos_totales || 0);
            isFantasyLocked = data.locked; // Estado de bloqueo desde backend

            data.mi_equipo.forEach(p => {
                if (myFantasyTeam[p.Posicion]) myFantasyTeam[p.Posicion].push(p);
            });

            renderFantasyUI(data.mercado, data.es_exclusivo);
        });
    }

    function renderFantasyUI(marketPlayers, isExclusive) {
        const budgetColor = fantasyBudget >= 0 ? '#ffdd00' : '#ff4d4d';
        const exclusiveBadge = isExclusive ? '<span style="background:#ff4d4d; padding:2px 5px; border-radius:4px; font-size:10px;">EXCLUSIVO</span>' : '<span style="background:#00ff26; color:black; padding:2px 5px; border-radius:4px; font-size:10px;">LIBRE</span>';

        let ptsColor = '#ccc';
        if (fantasyPointsTotal > 0) ptsColor = '#00ff26';
        else if (fantasyPointsTotal < 0) ptsColor = '#ff4d4d';

        // Botón de guardar solo si NO está bloqueado
        const saveBtn = isFantasyLocked 
            ? '<span style="color:#ff4d4d; font-weight:bold; font-size:12px; border:1px solid #ff4d4d; padding:5px 10px; border-radius:5px;">JORNADA CERRADA</span>' 
            : `<button class="btn-primary" onclick="saveFantasyTeam()" style="padding:5px 15px; font-size:12px; margin-top:5px;">Guardar Equipo</button>`;

        let html = `
            <div class="fantasy-header" style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.3); padding:10px; border-radius:10px; margin-bottom:15px;">
                <div style="display:flex; gap:20px; text-align:left;">
                    <div>
                        <div style="font-size:12px; color:#ccc;">Presupuesto</div>
                        <div class="budget-display" style="font-size:20px; font-weight:bold; color:${budgetColor};">$${fantasyBudget.toFixed(1)}M</div>
                    </div>
                    <div style="border-left: 1px solid rgba(255,255,255,0.2); padding-left: 20px;">
                        <div style="font-size:12px; color:#ccc;">Puntos Jornada</div>
                        <div style="font-size:20px; font-weight:bold; color:${ptsColor};">${fantasyPointsTotal} pts</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px; color:#ccc;">Mercado ${exclusiveBadge}</div>
                    ${saveBtn}
                </div>
            </div>

            <div class="fantasy-container" style="display:grid; grid-template-columns: 1.5fr 1fr; gap:20px; height:550px;">
                <div class="fantasy-field-area" style="position:relative; background-image:url('Imagenes/image_b0963d.jpg'); background-size:cover; border-radius:15px; border:2px solid #ffdd00;">
                    ${renderFieldSlots()}
                </div>

                <div class="fantasy-market" style="display:flex; flex-direction:column; background:rgba(0,0,0,0.2); border-radius:15px; padding:10px; overflow:hidden;">
                    <div style="margin-bottom:10px;">
                        <input type="text" id="player-search" placeholder="Buscar jugador..." 
                               style="width:100%; padding:8px; border-radius:20px; border:none; font-size:12px; text-align:center;"
                               onkeyup="filterMarket(null)">
                    </div>
                    <div class="market-filters" style="display:flex; gap:5px; margin-bottom:10px;">
                        <button class="btn-small" onclick="filterMarket('ALL')">Todo</button>
                        <button class="btn-small" onclick="filterMarket('POR')">POR</button>
                        <button class="btn-small" onclick="filterMarket('DEF')">DEF</button>
                        <button class="btn-small" onclick="filterMarket('MED')">MED</button>
                        <button class="btn-small" onclick="filterMarket('DEL')">DEL</button>
                    </div>
                    <div id="market-list" style="flex:1; overflow-y:auto; padding-right:5px;"></div>
                </div>
            </div>
        `;
        
        kingnielarContentArea.innerHTML = html;

        window.currentMarket = marketPlayers;
        window.currentPosFilter = 'ALL'; // Guardar filtro actual
        filterMarket('ALL'); 
        updateFieldVisuals();
    }

    function renderFieldSlots() {
        const slots = [
            {pos:'POR', t:'85%', l:'50%'},
            {pos:'DEF', t:'65%', l:'20%'}, {pos:'DEF', t:'65%', l:'40%'}, {pos:'DEF', t:'65%', l:'60%'}, {pos:'DEF', t:'65%', l:'80%'},
            {pos:'MED', t:'45%', l:'30%'}, {pos:'MED', t:'45%', l:'50%'}, {pos:'MED', t:'45%', l:'70%'},
            {pos:'DEL', t:'20%', l:'25%'}, {pos:'DEL', t:'20%', l:'50%'}, {pos:'DEL', t:'20%', l:'75%'}
        ];

        return slots.map((s, i) => `
            <div class="player-slot slot-${s.pos}" id="slot-${s.pos}-${i}" 
                 style="position:absolute; top:${s.t}; left:${s.l}; transform:translate(-50%,-50%); width:45px; height:45px; background:rgba(0,0,0,0.5); border:2px solid white; border-radius:50%; cursor:${isFantasyLocked ? 'default' : 'pointer'}; display:flex; align-items:center; justify-content:center;"
                 onclick="removePlayerFromSlot('${s.pos}', ${i})">
                <span style="font-size:10px; font-weight:bold;">${s.pos}</span>
            </div>
        `).join('');
    }

    window.filterMarket = function(pos) {
        // Si pos es null, usar el último filtro activo
        if (pos === null) pos = window.currentPosFilter;
        else window.currentPosFilter = pos;

        const searchText = document.getElementById('player-search').value.toLowerCase();
        const container = document.getElementById('market-list');
        container.innerHTML = "";
        
        const filtered = window.currentMarket.filter(p => {
            const matchPos = (pos === 'ALL' || p.Posicion === pos);
            const matchName = p.Nombre_Jugador.toLowerCase().includes(searchText);
            return matchPos && matchName;
        });

        filtered.forEach(p => {
            const isOwnedByMe = p.lo_tengo;
            const isTaken = p.ocupado && !isOwnedByMe;
            
            let btnHtml = '';
            if (isOwnedByMe) btnHtml = `<span style="color:#00ff26; font-size:10px;">EN EQUIPO</span>`;
            else if (isTaken) btnHtml = `<span style="color:#ff4d4d; font-size:16px;">🔒</span>`; 
            else if (isFantasyLocked) btnHtml = `<span style="color:#888; font-size:12px;">🚫</span>`; // Bloqueado
            else btnHtml = `<button style="background:#ffdd00; border:none; border-radius:50%; width:20px; height:20px; font-weight:bold; cursor:pointer;" onclick="buyPlayer(${p.Id_Jugador})">+</button>`;

            const div = document.createElement('div');
            div.style.cssText = "display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.1); padding:5px; border-radius:5px; margin-bottom:5px;";
            div.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px;">
                    <img src="${p.Escudo || 'Imagenes/mundial_2026.png'}" style="width:20px;">
                    <div>
                        <div style="font-size:12px; font-weight:bold;">${p.Nombre_Jugador}</div>
                        <div style="font-size:10px; color:#ccc;">${p.Posicion} - $${p.Costo}M</div>
                    </div>
                </div>
                <div>${btnHtml}</div>
            `;
            container.appendChild(div);
        });
    }

    window.buyPlayer = function(id) {
        if (isFantasyLocked) return alert("La jornada ya ha comenzado o finalizado. No se pueden hacer cambios.");
        
        const player = window.currentMarket.find(p => parseInt(p.Id_Jugador) === parseInt(id));
        if (!player) return;

        if (fantasyBudget - player.Costo < 0) return alert("Presupuesto insuficiente");
        if (myFantasyTeam[player.Posicion].length >= FANTASY_LIMITS[player.Posicion]) {
            return alert(`Cupo de ${player.Posicion} lleno. Vende a uno primero.`);
        }

        myFantasyTeam[player.Posicion].push(player);
        fantasyBudget -= parseFloat(player.Costo);
        player.lo_tengo = true; 
        
        updateUI();
    }

    window.sellPlayer = function(id) {
        if (isFantasyLocked) return alert("La jornada ya ha comenzado o finalizado. No se pueden hacer cambios.");

        let foundPos = null;
        Object.keys(myFantasyTeam).forEach(pos => {
            const idx = myFantasyTeam[pos].findIndex(p => parseInt(p.Id_Jugador) === parseInt(id));
            if (idx !== -1) {
                const p = myFantasyTeam[pos][idx];
                fantasyBudget += parseFloat(p.Costo);
                myFantasyTeam[pos].splice(idx, 1);
                foundPos = pos;
            }
        });

        const marketPlayer = window.currentMarket.find(p => parseInt(p.Id_Jugador) === parseInt(id));
        if(marketPlayer) marketPlayer.lo_tengo = false;

        if (foundPos) updateUI();
    }

    function updateUI() {
        const budgetDiv = document.querySelector('.budget-display');
        if(budgetDiv) {
            budgetDiv.innerText = `$${fantasyBudget.toFixed(1)}M`;
            budgetDiv.style.color = fantasyBudget >= 0 ? '#ffdd00' : 'red';
        }
        updateFieldVisuals();
        filterMarket(null); // Refrescar con filtro actual
    }

    function updateFieldVisuals() {
        document.querySelectorAll('.player-slot').forEach(s => {
            const pos = s.id.split('-')[1];
            s.innerHTML = `<span style="font-size:10px; font-weight:bold; color:white;">${pos}</span>`;
            s.style.background = "rgba(0,0,0,0.5)";
            s.style.border = "2px solid rgba(255,255,255,0.5)";
            s.onclick = null; 
            s.classList.remove('filled');
        });

        const mapBase = { POR:0, DEF:1, MED:5, DEL:8 };

        Object.keys(myFantasyTeam).forEach(pos => {
            myFantasyTeam[pos].forEach((p, i) => {
                const slotIndex = mapBase[pos] + i;
                const slot = document.querySelectorAll('.player-slot')[slotIndex];
                
                if (slot) {
                    const imgSrc = p.Foto ? p.Foto : 'Imagenes/Jugadores/default.webp';
                    
                    let badgeHtml = '';
                    if (p.Puntos !== null && p.Puntos !== undefined) {
                        const pts = parseInt(p.Puntos);
                        let colorClass = 'points-zero';
                        if(pts > 0) colorClass = 'points-positive';
                        if(pts < 0) colorClass = 'points-negative';
                        badgeHtml = `<div class="player-points-badge ${colorClass}">${pts}</div>`;
                    }

                    const nameParts = p.Nombre_Jugador.split(' ');
                    const shortName = nameParts.length > 1 ? nameParts[1] : nameParts[0];

                    slot.innerHTML = `
                        <img src="${imgSrc}" class="player-img" onerror="this.src='Imagenes/Jugadores/default.webp'">
                        <div class="player-name-label">${shortName}</div>
                        ${badgeHtml}
                    `;
                    
                    slot.style.background = "#001f5c";
                    slot.style.border = "2px solid #ffdd00";
                    slot.classList.add('filled');
                    
                    // Solo permitir vender si no está bloqueado
                    if (!isFantasyLocked) {
                        slot.onclick = () => window.sellPlayer(p.Id_Jugador); 
                    }
                }
            });
        });
    }

    window.saveFantasyTeam = function() {
        if (isFantasyLocked) return alert("No se puede guardar. Jornada cerrada.");

        let allIds = [];
        Object.values(myFantasyTeam).forEach(arr => arr.forEach(p => allIds.push(p.Id_Jugador)));

        if (allIds.length !== 11) return alert(`Necesitas 11 jugadores. Tienes ${allIds.length}.`);
        if (fantasyBudget < 0) return alert("Estás en bancarrota. Ajusta tu presupuesto.");

        fetch('php/guardar_fantasy.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                id_quiniela: quinielaActiva.Id_Quiniela,
                jornada: jornadaSelect.value,
                jugadores: allIds
            })
        })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                alert("¡Equipo guardado!");
                loadFantasyData(quinielaActiva.Id_Quiniela, jornadaSelect.value);
            }
            else alert("Error: " + res.message);
        });
    }

    // ... (El resto de funciones cargarPartidos, selectPred, etc. se mantienen igual) ...
    function cargarPartidos(idQuiniela, jornada) {
        kingnielarContentArea.innerHTML = "<p style='color:white; text-align:center;'>Cargando...</p>";
        fetch(`php/quiniela_partidos.php?id_quiniela=${idQuiniela}&jornada=${jornada}`)
        .then(r => r.json())
        .then(data => {
            kingnielarContentArea.innerHTML = "";
            if(!data.success || !data.partidos || data.partidos.length === 0) {
                kingnielarContentArea.innerHTML = "<p style='color:#ccc; text-align:center;'>No hay partidos disponibles.</p>"; return;
            }
            
            const dificultad = data.config.Dificultad;
            data.partidos.forEach(p => {
                const card = document.createElement('div'); card.className = 'match-card'; card.dataset.id = p.id;
                if (dificultad === 'Leyenda') { 
                    card.dataset.playersL = JSON.stringify(p.jugadores_L); 
                    card.dataset.playersV = JSON.stringify(p.jugadores_V); 
                }
                const isFinalizado = (p.estado === 'finalizado');
                const disabled = isFinalizado ? 'disabled style="pointer-events:none; opacity:0.8;"' : '';
                const readOnly = isFinalizado ? 'readonly' : '';
                const puntosBadge = (isFinalizado && p.puntos !== null) ? `<span class="points-badge">+${p.puntos} pts</span>` : '';
                let inputHtml = ''; let extraHtml = ''; let realSigno = 'E';
                
                if(p.goles_real_L > p.goles_real_V) realSigno = 'L'; else if(p.goles_real_V > p.goles_real_L) realSigno = 'V';

                if (dificultad === 'Aficionado') {
                    let cL = '', cE = '', cV = '';
                    if (p.pred_L == '1') cL = (isFinalizado && realSigno == 'L') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');
                    if (p.pred_L == '-1') cE = (isFinalizado && realSigno == 'E') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');
                    if (p.pred_V == '1') cV = (isFinalizado && realSigno == 'V') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');
                    inputHtml = `<div class="prediction-buttons" ${disabled}><button class="btn-predict ${cL}" onclick="selectPred(this, 'L')">L</button><button class="btn-predict btn-draw ${cE}" onclick="selectPred(this, 'E')">Empate</button><button class="btn-predict ${cV}" onclick="selectPred(this, 'V')">V</button></div>`;
                } else {
                    let inputClass = ''; let resReal = '';
                    if(isFinalizado) { 
                        inputClass = (p.puntos > 0) ? 'correct' : 'wrong'; 
                        resReal = `<span class="real-result">Final: ${p.goles_real_L} - ${p.goles_real_V}</span>`; 
                    }
                    const onInputFn = (dificultad === 'Leyenda') ? 'oninput="updateScorersUI(this)"' : '';
                    inputHtml = `<div class="score-inputs-container"><input type="number" min="0" class="score-input input-local ${inputClass}" value="${p.pred_L}" placeholder="-" ${readOnly} ${onInputFn}><span>-</span><input type="number" min="0" class="score-input input-visit ${inputClass}" value="${p.pred_V}" placeholder="-" ${readOnly} ${onInputFn}></div>${resReal}`;
                    if (dificultad === 'Leyenda') { 
                        extraHtml = `<div class="scorers-section"><div class="scorers-column"><div class="scorers-list list-local"></div><div class="scorer-controls" ${isFinalizado?'style="display:none"':''}><select class="scorer-select-input select-local"><option value="">Goleador Local...</option></select><button class="btn-add-scorer" onclick="addScorerUI(this, 'L')">+</button></div></div><div class="scorers-column"><div class="scorers-list list-visit"></div><div class="scorer-controls" ${isFinalizado?'style="display:none"':''}><select class="scorer-select-input select-visit"><option value="">Goleador Visita...</option></select><button class="btn-add-scorer" onclick="addScorerUI(this, 'V')">+</button></div></div></div>`; 
                    }
                }

                const statusText = isFinalizado ? '<span style="color:#ff4d4d">Finalizado</span>' : '<span style="color:#00ff26">Programado</span>';
                card.innerHTML = `<div class="match-header"><span>${p.fecha}</span><div>${statusText} ${puntosBadge}</div></div><div class="match-teams-area"><div class="team-info"><img src="${p.escudoL}" class="team-logo"><div class="team-abbr">${p.local}</div></div><div class="match-center-area">${inputHtml}</div><div class="team-info"><img src="${p.escudoV}" class="team-logo"><div class="team-abbr">${p.visitante}</div></div></div>${extraHtml}`;
                kingnielarContentArea.appendChild(card);

                if (dificultad === 'Leyenda') { 
                    initScorerSelects(card); 
                    if (p.mis_goleadores && p.mis_goleadores.length > 0) loadSavedScorers(card, p.mis_goleadores); 
                }
            });
        });
    }

    window.selectPred = function(btn, tipo) { 
        const container = btn.parentElement; 
        Array.from(container.children).forEach(b => b.classList.remove('selected')); 
        btn.classList.add('selected'); 
        container.dataset.val = tipo; 
    }

    window.addScorerUI = function(btn, side) { 
        const card = btn.closest('.match-card'); 
        const inputScore = card.querySelector(side === 'L' ? '.input-local' : '.input-visit'); 
        const select = card.querySelector(side === 'L' ? '.select-local' : '.select-visit'); 
        const listContainer = card.querySelector(side === 'L' ? '.list-local' : '.list-visit'); 
        
        const golesPrevistos = parseInt(inputScore.value); 
        if (isNaN(golesPrevistos) || golesPrevistos < 0) return alert("Primero define un marcador válido."); 
        if (listContainer.children.length >= golesPrevistos) return alert(`Solo pronosticaste ${golesPrevistos} goles.`); 
        
        const idJugador = select.value; 
        const nombreJugador = select.options[select.selectedIndex].text; 
        if (!idJugador) return; 

        if (listContainer.querySelector(`.scorer-tag[data-id="${idJugador}"]`)) return alert("Este jugador ya fue seleccionado.");

        createScorerTag(listContainer, idJugador, nombreJugador, false); 
        select.value = ""; 
    }

    function createScorerTag(container, id, name, readOnly) { 
        const tag = document.createElement('div'); tag.className = 'scorer-tag'; tag.dataset.id = id; 
        const removeBtn = readOnly ? '' : `<button class="btn-remove-scorer" onclick="this.parentElement.remove()">✕</button>`; 
        tag.innerHTML = `<span>${name}</span>${removeBtn}`; 
        container.appendChild(tag); 
    }

    function initScorerSelects(card) { 
        const playersL = JSON.parse(card.dataset.playersL || '[]'); 
        const playersV = JSON.parse(card.dataset.playersV || '[]'); 
        const selL = card.querySelector('.select-local'); 
        const selV = card.querySelector('.select-visit'); 
        playersL.forEach(p => selL.add(new Option(p.Nombre_Jugador, p.Id_Jugador))); 
        playersV.forEach(p => selV.add(new Option(p.Nombre_Jugador, p.Id_Jugador))); 
    }

    function loadSavedScorers(card, savedIds) { 
        const playersL = JSON.parse(card.dataset.playersL || '[]'); 
        const playersV = JSON.parse(card.dataset.playersV || '[]'); 
        const listL = card.querySelector('.list-local'); 
        const listV = card.querySelector('.list-visit'); 
        const isFinished = card.querySelector('.score-input').hasAttribute('readonly'); 
        savedIds.forEach(id => { 
            const pL = playersL.find(p => p.Id_Jugador == id); 
            if (pL) createScorerTag(listL, pL.Id_Jugador, pL.Nombre_Jugador, isFinished); 
            const pV = playersV.find(p => p.Id_Jugador == id); 
            if (pV) createScorerTag(listV, pV.Id_Jugador, pV.Nombre_Jugador, isFinished); 
        }); 
    }

    if(btnGuardarPredicciones) { 
        btnGuardarPredicciones.addEventListener('click', () => { 
            if(!quinielaActiva) return; 
            const matchCards = document.querySelectorAll('.match-card'); 
            let predicciones = []; 
            let errorNegativo = false; 
            for (const card of matchCards) { 
                if(card.querySelector('[disabled]') || card.querySelector('[readonly]')) continue; 
                const idPartido = card.dataset.id; 
                const btnContainer = card.querySelector('.prediction-buttons'); 
                const inputLocal = card.querySelector('.input-local'); 
                let gL = null, gV = null; let scorers = []; 
                
                if (btnContainer) { 
                    const selected = btnContainer.querySelector('.selected'); 
                    if(selected) { 
                        const v = btnContainer.dataset.val || selected.innerText; 
                        if(v==='L') { gL=1; gV=0; } else if(v==='V') { gL=0; gV=1; } else { gL=-1; gV=-1; } 
                    } 
                } else if (inputLocal) { 
                    const inputVisit = card.querySelector('.input-visit'); 
                    if(inputLocal.value !== '' && inputVisit.value !== '') { 
                        if(parseInt(inputLocal.value)<0 || parseInt(inputVisit.value)<0) { errorNegativo=true; break; } 
                        gL = inputLocal.value; gV = inputVisit.value; 
                        card.querySelectorAll('.scorer-tag').forEach(tag => scorers.push(tag.dataset.id)); 
                    } 
                } 
                if (gL !== null) predicciones.push({ id_partido: idPartido, local: gL, visitante: gV, goleadores: scorers }); 
            } 
            if(errorNegativo) return alert("No números negativos."); 
            if(predicciones.length === 0) return alert("No hay predicciones nuevas."); 
            fetch('php/guardar_predicciones.php', { 
                method: 'POST', headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ id_quiniela: quinielaActiva.Id_Quiniela, predicciones: predicciones }) 
            }).then(r => r.json()).then(res => { if(res.success) alert("Guardado."); else alert("Error: " + res.message); }); 
        }); 
    }

    // --- CLASIFICACIÓN ---
    function renderClasificacionTab() {
        if(!quinielaActiva) return;
        const jornadaActual = jornadaSelect.value || 'Jornada 1';
        
        let url = `php/quiniela_ranking.php?id_quiniela=${quinielaActiva.Id_Quiniela}&jornada=${jornadaActual}`;
        if (quinielaActiva.Tipo_Quiniela === 'fantasy') {
            url = `php/ranking_fantasy.php?id_quiniela=${quinielaActiva.Id_Quiniela}&jornada=${jornadaActual}`;
        }

        fetch(url).then(r => r.json()).then(data => {
            if(data.success) {
                document.getElementById('total-participants').textContent = data.ranking.length;
                
                // NUEVOS ENCABEZADOS (16vos, 8vos, 4tos, Semis, Final)
                const headersHtml = `
                    <tr>
                        <th>Pos</th>
                        <th>Usuario</th>
                        <th>Total</th>
                        <th>J1</th>
                        <th>J2</th>
                        <th>J3</th>
                        <th>16vos</th>
                        <th>8vos</th>
                        <th>4tos</th>
                        <th>Semis</th>
                        <th>Final</th>
                    </tr>`;
                document.querySelectorAll('.leaderboard-table thead').forEach(th => th.innerHTML = headersHtml);
                
                const tbody = document.getElementById('general-rank-body');
                const userBody = document.getElementById('user-rank-body');
                tbody.innerHTML = ""; userBody.innerHTML = "";
                
                data.ranking.forEach((u, index) => {
                    const crown = u.Corona ? `<img src="${u.Corona}" class="crown-badge">` : '';
                    const rowHtml = `
                        <tr>
                            <td>#${index + 1}</td>
                            <td class="user-cell">
                                <img src="${u.Avatar || 'Imagenes/I_Perfil.png'}" class="profile-pic">
                                <div class="name-container"><span>${u.Nombre_Usuario}</span>${crown}</div>
                            </td>
                            <td><strong>${u.PuntosTotales}</strong></td>
                            <td>${u.Pts_J1}</td>
                            <td>${u.Pts_J2}</td>
                            <td>${u.Pts_J3}</td>
                            <td>${u.Pts_16 || 0}</td>
                            <td>${u.Pts_8 || 0}</td>
                            <td>${u.Pts_4 || 0}</td>
                            <td>${u.Pts_Semi || 0}</td>
                            <td>${u.Pts_Final || 0}</td>
                        </tr>`;
                    tbody.innerHTML += rowHtml;
                    if(parseInt(u.Id_Usuario) === currentUser.id) { userBody.innerHTML = rowHtml; }
                });
            }
        });
    }

    function loadGroupChat() { 
        if(!quinielaActiva) return; 
        groupChatBody.innerHTML = "<p style='text-align:center;color:#ccc;'>Cargando...</p>"; 
        fetch(`php/chat_grupo.php?id_quiniela=${quinielaActiva.Id_Quiniela}`).then(r => r.json()).then(msgs => { 
            groupChatBody.innerHTML = ""; 
            msgs.forEach(m => appendGroupMessage(m)); 
            groupChatBody.scrollTop = groupChatBody.scrollHeight; 
        }); 
    }
    function appendGroupMessage(msg) { 
        const isMe = parseInt(msg.Id_Emisor) === currentUser.id; 
        const div = document.createElement('div'); 
        div.className = `message ${isMe ? 'sent' : 'received'}`; 
        if(isMe) div.style.alignSelf = 'flex-end'; 
        let infoHtml = ''; 
        if(!isMe) { 
            const crown = msg.Corona ? `<img src="${msg.Corona}" class="crown-badge">` : ''; 
            infoHtml = `<div style="display:flex; align-items:center; margin-bottom:5px;"><img src="${msg.Avatar || 'Imagenes/I_Perfil.png'}" class="profile-pic" style="width:20px; height:20px; margin-right:5px;"><div class="name-container" style="font-size:11px; color:#ffdd00;"><span>${msg.Nombre_Usuario}</span>${crown}</div></div>`; 
        } 
        const dateObj = msg.Fecha_Envio ? new Date(msg.Fecha_Envio) : new Date(); 
        const timeStr = dateObj.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); 
        div.innerHTML = `<div class="message-content" style="background:${isMe?'#0f1b73':'#1a2cbd'}; border-radius:10px; padding:8px 12px;">${infoHtml}<p style="margin:0; font-size:13px;">${msg.Contenido}</p><span style="font-size:9px; opacity:0.7; display:block; text-align:right; margin-top:3px;">${timeStr}</span></div>`; 
        groupChatBody.appendChild(div); 
        groupChatBody.scrollTop = groupChatBody.scrollHeight; 
    }
    if(btnSendGroup) btnSendGroup.onclick = sendGroupMessageAction;
    if(groupChatInput) groupChatInput.onkeypress = (e) => { if(e.key === 'Enter') sendGroupMessageAction(); };
    function sendGroupMessageAction() { 
        const text = groupChatInput.value.trim(); 
        if(!text || !quinielaActiva) return; 
        const fd = new FormData(); 
        fd.append('id_quiniela', quinielaActiva.Id_Quiniela); 
        fd.append('content', text); 
        fetch('php/chat_grupo.php', { method: 'POST', body: fd }).then(r => r.json()).then(res => { if(res.success) groupChatInput.value = ""; }); 
    }

    if(btnShowCreate) btnShowCreate.addEventListener('click', () => { quinielaListView.classList.add('hidden'); createQuinielaView.classList.remove('hidden'); amigosSeleccionados = []; inpNombre.value = ""; toggleDifficulty(); });
    if(btnCancelCreate) btnCancelCreate.addEventListener('click', () => { createQuinielaView.classList.add('hidden'); quinielaListView.classList.remove('hidden'); });
    function toggleDifficulty() { if(radioKingniela.checked) difficultySection.classList.remove('hidden'); else difficultySection.classList.add('hidden'); }
    if(radioKingniela) radioKingniela.addEventListener('change', toggleDifficulty); if(radioClasico) radioClasico.addEventListener('change', toggleDifficulty);
    if(btnOpenMembers) btnOpenMembers.addEventListener('click', () => { fetch(`php/friends.php?user_id=${currentUser.id}`).then(r=>r.json()).then(d=>{ renderMembersModalList(d.friends); membersModal.classList.remove('hidden'); }); });
    function renderMembersModalList(friends) { membersListContainer.innerHTML = ""; if(!friends) return; friends.forEach(f => { const isChecked = amigosSeleccionados.includes(parseInt(f.id)) ? 'checked' : ''; membersListContainer.innerHTML += `<div class="member-item"><input type="checkbox" id="friend${f.id}" value="${f.id}" ${isChecked}><label for="friend${f.id}"><img src="${f.avatar||'Imagenes/I_Perfil.png'}" class="profile-pic"><span>${f.nombre}</span></label></div>`; }); }
    if(btnConfirmMembers) btnConfirmMembers.addEventListener('click', () => { amigosSeleccionados = Array.from(membersListContainer.querySelectorAll('input:checked')).map(cb => parseInt(cb.value)); membersModal.classList.add('hidden'); });
    if(closeMembers) closeMembers.addEventListener('click', () => membersModal.classList.add('hidden'));
    if(btnCreateFinal) btnCreateFinal.addEventListener('click', () => { const nombre = inpNombre.value.trim(); if(!nombre) return alert("Nombre requerido"); fetch('php/crear_quiniela.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ nombre: nombre, tipo: radioKingniela.checked?'kingniela':'fantasy', dificultad: selectDifficulty.value, amigos: amigosSeleccionados }) }).then(r=>r.json()).then(resp => { if(resp.success) { generatedCodeDisplay.textContent = resp.codigo; createQuinielaView.classList.add('hidden'); successModal.classList.remove('hidden'); cargarMisQuinielas(); } }); });
    if(btnFinish) btnFinish.addEventListener('click', () => { successModal.classList.add('hidden'); quinielaListView.classList.remove('hidden'); });

    tabButtons.forEach(btn => btn.addEventListener('click', () => clickTab(btn.dataset.tab)));
    function clickTab(tabId) { 
        tabButtons.forEach(b => b.classList.remove('active')); 
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active'); 
        tabContents.forEach(c => c.classList.add('hidden')); 
        document.getElementById(tabId).classList.remove('hidden'); 
        if(tabId === 'tab-kingnielar' && quinielaActiva) renderKingnielarTab(quinielaActiva); 
        if(tabId === 'tab-clasificacion' && quinielaActiva) renderClasificacionTab(); 
        if(tabId === 'tab-chat' && quinielaActiva) loadGroupChat(); 
    }

    cargarMisQuinielas();
});