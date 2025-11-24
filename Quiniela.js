document.addEventListener('DOMContentLoaded', () => {

    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
    if (!currentUser) {
        window.location.href = 'IniciarSesion.html';
        return;
    }

    // --- REFERENCIAS DOM ---
    // Vistas
    const mainContentView = document.getElementById('main-menu-view');
    const quinielaListView = document.getElementById('quiniela-list-view');
    const createQuinielaView = document.getElementById('create-quiniela-view');
    const groupView = document.getElementById('quiniela-group-view');
    
    // Sidebar
    const sidebarList = document.getElementById('sidebar-quinielas-list');

    // Botones
    const btnShowCreate = document.getElementById('btn-show-create');
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const btnCreateFinal = document.getElementById('btn-create-final');
    const btnOpenMembers = document.getElementById('btn-open-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    const btnFinish = document.getElementById('btn-finish');
    const btnCopyCode = document.getElementById('btn-copy-code');

    // Modales e Inputs
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

    // Variables de Estado
    let amigosSeleccionados = [];
    let misQuinielas = [];

    // ==========================================================
    // 1. CARGAR BARRA LATERAL (QUINIELAS DEL USUARIO)
    // ==========================================================
    function cargarMisQuinielas() {
        fetch('php/mis_quinielas.php')
        .then(r => r.json())
        .then(data => {
            misQuinielas = data; // Guardamos para uso local
            renderSidebar();
        })
        .catch(e => console.error("Error cargando quinielas:", e));
    }

    function renderSidebar() {
        sidebarList.innerHTML = ""; 
        
        // Botón '+' (Crear)
        const liAdd = document.createElement('li');
        liAdd.id = 'crear__quiniela';
        liAdd.textContent = '+';
        liAdd.addEventListener('click', () => {
            groupView.classList.add('hidden');
            mainContentView.classList.remove('hidden');
            quinielaListView.classList.remove('hidden');
            createQuinielaView.classList.add('hidden');
            document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
            liAdd.style.backgroundColor = '#ffdd00';
            liAdd.style.color = '#001f5c';
        });
        sidebarList.appendChild(liAdd);

        // Lista de Grupos
        if(Array.isArray(misQuinielas)) {
            misQuinielas.forEach(q => {
                const li = document.createElement('li');
                li.className = 'quiniela-sidebar-item';
                // Usar iniciales
                li.textContent = q.Nombre_Quiniela.substring(0, 2).toUpperCase();
                li.title = q.Nombre_Quiniela;
                li.style.backgroundColor = getRandomColor(); // Función estética
                
                li.addEventListener('click', () => {
                    document.querySelectorAll('.quiniela-sidebar-item').forEach(i => i.classList.remove('active-group'));
                    li.classList.add('active-group');
                    
                    // Reset estilo botón +
                    const btnPlus = document.getElementById('crear__quiniela');
                    if(btnPlus) {
                        btnPlus.style.backgroundColor = 'white';
                        btnPlus.style.color = '#001f5c';
                    }
                    
                    loadGroupView(q);
                });
                sidebarList.appendChild(li);
            });
        }
    }

    function getRandomColor() {
        const h = Math.floor(Math.random() * 360);
        return `hsl(${h}, 70%, 80%)`;
    }

    // ==========================================================
    // 2. CREACIÓN DE QUINIELA
    // ==========================================================

    // Mostrar formulario
    if(btnShowCreate) {
        btnShowCreate.addEventListener('click', () => {
            quinielaListView.classList.add('hidden');
            createQuinielaView.classList.remove('hidden');
            // Reset form
            amigosSeleccionados = [];
            inpNombre.value = "";
            radioKingniela.checked = true;
            toggleDifficulty();
        });
    }

    if(btnCancelCreate) {
        btnCancelCreate.addEventListener('click', () => {
            createQuinielaView.classList.add('hidden');
            quinielaListView.classList.remove('hidden');
        });
    }

    function toggleDifficulty() {
        if(radioKingniela.checked) difficultySection.classList.remove('hidden');
        else difficultySection.classList.add('hidden');
    }
    if(radioKingniela) radioKingniela.addEventListener('change', toggleDifficulty);
    if(radioClasico) radioClasico.addEventListener('change', toggleDifficulty);


    // --- MODAL AMIGOS ---
    if(btnOpenMembers) {
        btnOpenMembers.addEventListener('click', () => {
            // Cargar amigos desde la BD
            fetch(`php/friends.php?user_id=${currentUser.id}`)
            .then(r => r.json())
            .then(data => {
                renderMembersModalList(data.friends);
                membersModal.classList.remove('hidden');
            });
        });
    }

    function renderMembersModalList(friends) {
        membersListContainer.innerHTML = ""; 
        if(!friends || friends.length === 0) {
            membersListContainer.innerHTML = "<p>No tienes amigos agregados aún.</p>";
            return;
        }

        friends.forEach(f => {
            const item = document.createElement('div');
            item.className = "member-item";
            // Checkbox logic
            const isChecked = amigosSeleccionados.includes(parseInt(f.id)) ? 'checked' : '';
            
            item.innerHTML = `
                <input type="checkbox" id="friend${f.id}" value="${f.id}" ${isChecked}>
                <label for="friend${f.id}">
                    <img src="${f.avatar || 'Imagenes/I_Perfil.png'}" class="profile-pic">
                    <div class="name-container">
                        <span>${f.nombre}</span>
                    </div>
                </label>
            `;
            membersListContainer.appendChild(item);
        });
    }

    if(closeMembers) closeMembers.addEventListener('click', () => membersModal.classList.add('hidden'));
    
    if(btnConfirmMembers) {
        btnConfirmMembers.addEventListener('click', () => {
            // Guardar seleccionados
            const checkboxes = membersListContainer.querySelectorAll('input[type="checkbox"]:checked');
            amigosSeleccionados = Array.from(checkboxes).map(cb => parseInt(cb.value));
            membersModal.classList.add('hidden');
            alert(`${amigosSeleccionados.length} amigos seleccionados.`);
        });
    }

    // --- ENVIAR AL SERVIDOR ---
    if(btnCreateFinal) {
        btnCreateFinal.addEventListener('click', () => {
            const nombre = inpNombre.value.trim();
            if(!nombre) return alert("Ponle un nombre a la quiniela");

            const tipo = radioKingniela.checked ? 'kingniela' : 'clasico'; // Ojo: Ajustar a 'fantasy' si la BD espera eso
            // NOTA: En BD tu enum es 'kingniela', 'fantasy'. Ajustamos el JS:
            const tipoBD = radioKingniela.checked ? 'kingniela' : 'fantasy';
            const dificultad = selectDifficulty.value;

            const payload = {
                nombre: nombre,
                tipo: tipoBD,
                dificultad: dificultad,
                amigos: amigosSeleccionados
            };

            fetch('php/crear_quiniela.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(resp => {
                if(resp.success) {
                    generatedCodeDisplay.textContent = resp.codigo;
                    createQuinielaView.classList.add('hidden');
                    successModal.classList.remove('hidden');
                    // Recargar sidebar
                    cargarMisQuinielas();
                } else {
                    alert("Error: " + resp.message);
                }
            })
            .catch(e => console.error(e));
        });
    }

    if(btnFinish) {
        btnFinish.addEventListener('click', () => {
            successModal.classList.add('hidden');
            quinielaListView.classList.remove('hidden');
        });
    }

    // ==========================================================
    // 3. CARGAR VISTA DE GRUPO (Visualización)
    // ==========================================================
    function loadGroupView(quiniela) {
        mainContentView.classList.add('hidden');
        groupView.classList.remove('hidden');

        // Llenar datos del header
        document.getElementById('group-header-name').textContent = quiniela.Nombre_Quiniela;
        document.getElementById('group-header-code').textContent = quiniela.Codigo_Acceso;
        const img = document.getElementById('group-header-img');
        if(img) img.src = quiniela.Foto_Grupo || 'Imagenes/mundial_2026.png';

        // Reset tabs
        clickTab('tab-kingnielar');
        
        // Aquí deberías llamar a funciones para cargar los partidos de ESA quiniela específica
        // renderKingnielarTab(quiniela); 
        // renderClasificacionTab(quiniela.Id_Quiniela);
        // renderConfiguracionTab(quiniela);
    }

    // Tabs Logic
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => clickTab(btn.dataset.tab));
    });

    function clickTab(tabId) {
        tabButtons.forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if(activeBtn) activeBtn.classList.add('active');

        tabContents.forEach(c => c.classList.add('hidden'));
        const content = document.getElementById(tabId);
        if(content) content.classList.remove('hidden');
    }

    // Inicializar
    cargarMisQuinielas();

});