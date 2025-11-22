// --- DATOS SIMULADOS ---
const amigosData = [
    { 
        id: 1, 
        nombre: "Juan Pérez", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaDiamante1.png"
    },
    { 
        id: 2, 
        nombre: "Carlos López", 
        avatar: "Imagenes/I_Perfil.png",
        corona: null 
    },
    { 
        id: 3, 
        nombre: "Ana Torres", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaOro1.png"
    },
    { 
        id: 4, 
        nombre: "Luis Gomez", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaPlata1.png"
    },
    { 
        id: 5, 
        nombre: "Maria Ruiz", 
        avatar: "Imagenes/I_Perfil.png",
        corona: "Imagenes/CoronaBronce1.png"
    }
];

document.addEventListener('DOMContentLoaded', () => {
    
    // REFERENCIAS DOM
    const btnShowCreate = document.getElementById('btn-show-create');
    const btnCancelCreate = document.getElementById('btn-cancel-create');
    const listView = document.getElementById('quiniela-list-view');
    const createView = document.getElementById('create-quiniela-view');
    
    const radioKingniela = document.getElementById('tipo-kingniela');
    const radioClasico = document.getElementById('tipo-clasico');
    const difficultySection = document.getElementById('difficulty-section');

    const btnOpenMembers = document.getElementById('btn-open-members');
    const btnConfirmMembers = document.getElementById('btn-confirm-members');
    const closeMembers = document.getElementById('close-members');
    const membersModal = document.getElementById('membersModal');
    const membersListContainer = document.getElementById('members-list-container');

    const btnCreateFinal = document.getElementById('btn-create-final');
    const successModal = document.getElementById('successModal');
    const btnFinish = document.getElementById('btn-finish');
    const btnCopyCode = document.getElementById('btn-copy-code');
    const generatedCodeDisplay = document.getElementById('generated-code');


    // 1. NAVEGACIÓN
    btnShowCreate.addEventListener('click', () => {
        listView.classList.add('hidden');
        createView.classList.remove('hidden');
    });

    btnCancelCreate.addEventListener('click', () => {
        createView.classList.add('hidden');
        listView.classList.remove('hidden');
    });

    // 2. LÓGICA TIPO DE QUINIELA
    function toggleDifficulty() {
        if (radioKingniela.checked) {
            difficultySection.classList.remove('hidden');
        } else {
            difficultySection.classList.add('hidden');
        }
    }
    radioKingniela.addEventListener('change', toggleDifficulty);
    radioClasico.addEventListener('change', toggleDifficulty);


    // 3. MODAL DE MIEMBROS (Renderizado Dinámico)
    function renderMembersList() {
        membersListContainer.innerHTML = ""; 

        amigosData.forEach(friend => {
            const crownBadge = friend.corona 
                ? `<img src="${friend.corona}" class="crown-badge" alt="Insignia">` 
                : '';

            const item = document.createElement('div');
            item.className = "member-item";
            
            // AQUÍ ESTÁ EL CAMBIO IMPORTANTE: class="profile-pic"
            item.innerHTML = `
                <input type="checkbox" id="friend${friend.id}">
                <label for="friend${friend.id}">
                    <img src="${friend.avatar}" alt="Avatar" class="profile-pic">
                    <div class="name-container">
                        <span>${friend.nombre}</span>
                        ${crownBadge}
                    </div>
                </label>
            `;
            membersListContainer.appendChild(item);
        });
    }

    btnOpenMembers.addEventListener('click', () => {
        renderMembersList(); 
        membersModal.classList.remove('hidden');
    });

    const closeMembersModal = () => membersModal.classList.add('hidden');
    
    closeMembers.addEventListener('click', closeMembersModal);
    btnConfirmMembers.addEventListener('click', () => {
        closeMembersModal();
        alert("Miembros seleccionados agregados");
    });


    // 4. CREAR QUINIELA Y GENERAR CÓDIGO
    btnCreateFinal.addEventListener('click', () => {
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let code = "";
        for(let i=0; i<6; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        generatedCodeDisplay.textContent = code;
        
        createView.classList.add('hidden');
        successModal.classList.remove('hidden');
    });


    // 5. COPIAR CÓDIGO
    btnCopyCode.addEventListener('click', () => {
        const code = generatedCodeDisplay.textContent;
        navigator.clipboard.writeText(code).then(() => {
            const originalText = btnCopyCode.textContent;
            btnCopyCode.textContent = "✅"; 
            setTimeout(() => btnCopyCode.textContent = originalText, 1500);
        });
    });

    // 6. FINALIZAR
    btnFinish.addEventListener('click', () => {
        successModal.classList.add('hidden');
        listView.classList.remove('hidden'); 
    });
});