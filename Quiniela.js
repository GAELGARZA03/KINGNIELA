document.addEventListener('DOMContentLoaded', () => {

    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    // --- REFERENCIAS DOM ---
    const mainContentView = document.getElementById('main-menu-view');
    const quinielaListView = document.getElementById('quiniela-list-view');
    const createQuinielaView = document.getElementById('create-quiniela-view');
    const groupView = document.getElementById('quiniela-group-view');
    const sidebarList = document.getElementById('sidebar-quinielas-list');
    
    // Botones
    const btnShowCreate = document.getElementById('btn-show-create');
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const btnCreateFinal = document.getElementById('btn-create-final');
    const btnOpenMembers = document.getElementById('btn-open-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    const btnFinish = document.getElementById('btn-finish');
    const btnGuardarPredicciones = document.querySelector('#tab-kingnielar .btn-primary');

    // Modales
    const membersModal = document.getElementById('membersModal');
    const membersListContainer = document.getElementById('members-list-container');
    const closeMembers = document.getElementById('close-members');
    const successModal = document.getElementById('successModal');
    const generatedCodeDisplay = document.getElementById('generated-code');
    
    // Inputs
    const inpNombre = document.querySelector('#form-create-quiniela input[type="text"]');
    const radioKingniela = document.getElementById('tipo-kingniela');
    const radioClasico = document.getElementById('tipo-clasico');
    const difficultySection = document.getElementById('difficulty-section');
    const selectDifficulty = document.getElementById('select-difficulty');

    // Vistas Grupo
    const kingnielarContentArea = document.getElementById('kingnielar-content-area');
    const jornadaSelect = document.getElementById('jornada-select');
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    let amigosSeleccionados = [];
    let misQuinielas = [];
    let quinielaActiva = null;

    // --- CARGA INICIAL ---
    function cargarMisQuinielas() {
        fetch('php/mis_quinielas.php').then(r => r.json()).then(data => { misQuinielas = data; renderSidebar(); });
    }

    function renderSidebar() {
        sidebarList.innerHTML = ""; 
        const liAdd = document.createElement('li'); liAdd.id = 'crear__quiniela'; liAdd.textContent = '+';
        liAdd.addEventListener('click', () => {
            groupView.classList.add('hidden'); mainContentView.classList.remove('hidden');
            quinielaListView.classList.remove('hidden'); createQuinielaView.classList.add('hidden');
            document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
            liAdd.style.backgroundColor = '#ffdd00'; liAdd.style.color = '#001f5c';
        });
        sidebarList.appendChild(liAdd);

        if(Array.isArray(misQuinielas)) {
            misQuinielas.forEach(q => {
                const li = document.createElement('li'); li.className = 'quiniela-sidebar-item';
                li.textContent = q.Nombre_Quiniela.substring(0, 2).toUpperCase();
                li.style.backgroundColor = `hsl(${Math.floor(Math.random()*360)}, 70%, 80%)`;
                li.addEventListener('click', () => {
                    document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
                    li.classList.add('active-group'); loadGroupView(q);
                });
                sidebarList.appendChild(li);
            });
        }
    }

    // --- VISTA GRUPO ---
    function loadGroupView(quiniela) {
        quinielaActiva = quiniela;
        mainContentView.classList.add('hidden'); groupView.classList.remove('hidden');
        document.getElementById('group-header-name').textContent = quiniela.Nombre_Quiniela;
        document.getElementById('group-header-code').textContent = quiniela.Codigo_Acceso;
        const img = document.getElementById('group-header-img'); if(img) img.src = quiniela.Foto_Grupo || 'Imagenes/mundial_2026.png';
        clickTab('tab-kingnielar');
    }

    // --- RENDER CLASIFICACIÓN (ACTUALIZADO CON COLUMNAS) ---
    function renderClasificacionTab() {
        if(!quinielaActiva) return;
        
        fetch(`php/quiniela_ranking.php?id_quiniela=${quinielaActiva.Id_Quiniela}`)
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                document.getElementById('total-participants').textContent = data.ranking.length;
                
                // Actualizar encabezados de tabla dinámicamente
                const headersHtml = `
                    <tr>
                        <th>Pos</th>
                        <th>Usuario</th>
                        <th>Total</th>
                        <th>J1</th>
                        <th>J2</th>
                        <th>J3</th>
                        <th>Final</th>
                    </tr>
                `;
                
                // Aplicar a ambas tablas (Usuario y General)
                const tableHeads = document.querySelectorAll('.leaderboard-table thead');
                tableHeads.forEach(th => th.innerHTML = headersHtml);

                const tbody = document.getElementById('general-rank-body');
                const userBody = document.getElementById('user-rank-body');
                
                tbody.innerHTML = "";
                userBody.innerHTML = "";

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
                            <td>${u.Pts_Elim}</td>
                        </tr>`;
                    
                    tbody.innerHTML += rowHtml;

                    // Llenar tabla individual
                    if(parseInt(u.Id_Usuario) === currentUser.id) {
                        userBody.innerHTML = rowHtml;
                    }
                });
            }
        });
    }

    // --- RESTO DE FUNCIONES (Partidos, Guardar, Crear) ---
    function renderKingnielarTab(quiniela) {
        if(jornadaSelect.innerHTML.trim() === "") {
            ['Jornada 1', 'Jornada 2', 'Jornada 3', 'Dieciseisavos de final', 'Octavos de final', 'Cuartos de final', 'Semifinal', 'Tercer Puesto', 'Final'].forEach(j => {
                const opt = document.createElement('option'); opt.value = j; opt.textContent = j;
                jornadaSelect.appendChild(opt);
            });
            jornadaSelect.onchange = () => cargarPartidos(quiniela.Id_Quiniela, jornadaSelect.value);
        }
        cargarPartidos(quiniela.Id_Quiniela, jornadaSelect.value || 'Jornada 1');
    }

    function cargarPartidos(idQuiniela, jornada) {
        kingnielarContentArea.innerHTML = "<p style='color:white; text-align:center;'>Cargando...</p>";
        fetch(`php/quiniela_partidos.php?id_quiniela=${idQuiniela}&jornada=${jornada}`)
        .then(r => r.json())
        .then(data => {
            kingnielarContentArea.innerHTML = "";
            if(!data.success || data.partidos.length === 0) {
                kingnielarContentArea.innerHTML = "<p style='color:#ccc; text-align:center;'>No hay partidos disponibles.</p>"; return;
            }
            
            const dificultad = data.config.Dificultad;
            data.partidos.forEach(p => {
                const card = document.createElement('div');
                card.className = 'match-card';
                card.dataset.id = p.id;
                
                const isFinalizado = (p.estado === 'finalizado');
                const disabled = isFinalizado ? 'disabled style="pointer-events:none; opacity:0.8;"' : '';
                const readOnly = isFinalizado ? 'readonly' : '';
                const puntosBadge = (isFinalizado && p.puntos !== null) ? `<span class="points-badge">+${p.puntos} pts</span>` : '';
                
                let inputHtml = '';
                let realSigno = 'E';
                if(p.goles_real_L > p.goles_real_V) realSigno = 'L';
                else if(p.goles_real_V > p.goles_real_L) realSigno = 'V';

                if (dificultad === 'Aficionado') {
                    let classL = '', classE = '', classV = '';
                    if (p.pred_L == '1') classL = (isFinalizado && realSigno == 'L') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');
                    if (p.pred_L == '-1') classE = (isFinalizado && realSigno == 'E') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');
                    if (p.pred_V == '1') classV = (isFinalizado && realSigno == 'V') ? 'selected correct' : (isFinalizado ? 'selected wrong' : 'selected');

                    inputHtml = `
                        <div class="prediction-buttons" ${disabled}>
                            <button class="btn-predict ${classL}" onclick="selectPred(this, 'L')">L</button>
                            <button class="btn-predict btn-draw ${classE}" onclick="selectPred(this, 'E')">Empate</button>
                            <button class="btn-predict ${classV}" onclick="selectPred(this, 'V')">V</button>
                        </div>`;
                } else {
                    let inputClass = '';
                    let resultadoRealHtml = '';
                    if(isFinalizado) {
                        inputClass = (p.puntos > 0) ? 'correct' : 'wrong';
                        resultadoRealHtml = `<span class="real-result">Final: ${p.goles_real_L} - ${p.goles_real_V}</span>`;
                    }
                    inputHtml = `
                        <div class="score-inputs-container">
                            <input type="number" class="score-input input-local ${inputClass}" value="${p.pred_L}" placeholder="-" ${readOnly}>
                            <span>-</span>
                            <input type="number" class="score-input input-visit ${inputClass}" value="${p.pred_V}" placeholder="-" ${readOnly}>
                        </div>
                        ${resultadoRealHtml}`;
                }

                const statusText = isFinalizado ? '<span style="color:#ff4d4d">Finalizado</span>' : '<span style="color:#00ff26">Programado</span>';
                card.innerHTML = `
                    <div class="match-header"><span>${p.fecha}</span><div>${statusText} ${puntosBadge}</div></div>
                    <div class="match-teams-area">
                        <div class="team-info"><img src="${p.escudoL}" class="team-logo"><div class="team-abbr">${p.local}</div></div>
                        <div class="match-center-area">${inputHtml}</div>
                        <div class="team-info"><img src="${p.escudoV}" class="team-logo"><div class="team-abbr">${p.visitante}</div></div>
                    </div>
                `;
                kingnielarContentArea.appendChild(card);
            });
        });
    }

    window.selectPred = function(btn, tipo) {
        const container = btn.parentElement;
        Array.from(container.children).forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        container.dataset.val = tipo; 
    }

    if(btnGuardarPredicciones) {
        btnGuardarPredicciones.addEventListener('click', () => {
            if(!quinielaActiva) return;
            const matchCards = document.querySelectorAll('.match-card');
            let predicciones = [];
            
            matchCards.forEach(card => {
                if(card.querySelector('[disabled]') || card.querySelector('[readonly]')) return;
                const idPartido = card.dataset.id;
                const btnContainer = card.querySelector('.prediction-buttons');
                const inputLocal = card.querySelector('.input-local');
                let gL = null, gV = null;

                if (btnContainer) {
                    const selected = btnContainer.querySelector('.selected');
                    if(selected) {
                        const txt = selected.innerText;
                        if(txt === 'L') { gL = 1; gV = 0; }
                        else if(txt === 'V') { gL = 0; gV = 1; }
                        else { gL = -1; gV = -1; }
                    }
                } else if (inputLocal) {
                    const inputVisit = card.querySelector('.input-visit');
                    if(inputLocal.value !== '' && inputVisit.value !== '') {
                        gL = inputLocal.value; gV = inputVisit.value;
                    }
                }
                if (gL !== null) predicciones.push({ id_partido: idPartido, local: gL, visitante: gV });
            });

            if(predicciones.length === 0) return alert("No hay predicciones nuevas o editables.");

            fetch('php/guardar_predicciones.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id_quiniela: quinielaActiva.Id_Quiniela, predicciones: predicciones })
            })
            .then(r => r.json())
            .then(res => {
                if(res.success) alert("Guardado correctamente.");
                else alert("Error: " + res.message);
            });
        });
    }

    // Funcionalidad Botones UI
    if(btnShowCreate) btnShowCreate.addEventListener('click', () => { quinielaListView.classList.add('hidden'); createQuinielaView.classList.remove('hidden'); });
    if(btnCancelCreate) btnCancelCreate.addEventListener('click', () => { createQuinielaView.classList.add('hidden'); quinielaListView.classList.remove('hidden'); });
    if(radioKingniela) radioKingniela.addEventListener('change', () => difficultySection.classList.remove('hidden'));
    if(radioClasico) radioClasico.addEventListener('change', () => difficultySection.classList.add('hidden'));
    
    if(btnOpenMembers) btnOpenMembers.addEventListener('click', () => {
        fetch(`php/friends.php?user_id=${currentUser.id}`).then(r=>r.json()).then(d=>{
            membersListContainer.innerHTML = "";
            d.friends.forEach(f => {
                const isChecked = amigosSeleccionados.includes(parseInt(f.id)) ? 'checked' : '';
                membersListContainer.innerHTML += `<div class="member-item"><input type="checkbox" id="friend${f.id}" value="${f.id}" ${isChecked}><label for="friend${f.id}"><img src="${f.avatar||'Imagenes/I_Perfil.png'}" class="profile-pic"><span>${f.nombre}</span></label></div>`;
            });
            membersModal.classList.remove('hidden');
        });
    });
    
    if(btnConfirmMembers) btnConfirmMembers.addEventListener('click', () => {
        amigosSeleccionados = Array.from(membersListContainer.querySelectorAll('input:checked')).map(cb => parseInt(cb.value));
        membersModal.classList.add('hidden');
    });
    if(closeMembers) closeMembers.addEventListener('click', () => membersModal.classList.add('hidden'));

    if(btnCreateFinal) btnCreateFinal.addEventListener('click', () => {
        const nombre = inpNombre.value.trim();
        if(!nombre) return alert("Nombre requerido");
        fetch('php/crear_quiniela.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ nombre: nombre, tipo: radioKingniela.checked?'kingniela':'fantasy', dificultad: selectDifficulty.value, amigos: amigosSeleccionados })
        }).then(r=>r.json()).then(resp => {
            if(resp.success) {
                generatedCodeDisplay.textContent = resp.codigo;
                createQuinielaView.classList.add('hidden'); successModal.classList.remove('hidden'); cargarMisQuinielas();
            }
        });
    });
    if(btnFinish) btnFinish.addEventListener('click', () => { successModal.classList.add('hidden'); quinielaListView.classList.remove('hidden'); });

    // Tabs
    tabButtons.forEach(btn => btn.addEventListener('click', () => clickTab(btn.dataset.tab)));
    function clickTab(tabId) {
        tabButtons.forEach(b => b.classList.remove('active'));
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        tabContents.forEach(c => c.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');
        if(tabId === 'tab-kingnielar' && quinielaActiva) renderKingnielarTab(quinielaActiva);
        if(tabId === 'tab-clasificacion' && quinielaActiva) renderClasificacionTab();
    }
    
    cargarMisQuinielas();
});