document.addEventListener('DOMContentLoaded', () => {
    
    // --- REFERENCIAS DOM ---
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

    const btnCreateFinal = document.getElementById('btn-create-final');
    const successModal = document.getElementById('successModal');
    const btnFinish = document.getElementById('btn-finish');
    const btnCopyCode = document.getElementById('btn-copy-code');
    const generatedCodeDisplay = document.getElementById('generated-code');


    // 1. NAVEGACIÓN ENTRE VISTAS
    btnShowCreate.addEventListener('click', () => {
        listView.classList.add('hidden');
        createView.classList.remove('hidden');
    });

    btnCancelCreate.addEventListener('click', () => {
        createView.classList.add('hidden');
        listView.classList.remove('hidden');
        // Opcional: Resetear formulario
    });

    // 2. LÓGICA TIPO DE QUINIELA (Mostrar/Ocultar Dificultad)
    function toggleDifficulty() {
        if (radioKingniela.checked) {
            difficultySection.classList.remove('hidden');
        } else {
            difficultySection.classList.add('hidden');
        }
    }

    radioKingniela.addEventListener('change', toggleDifficulty);
    radioClasico.addEventListener('change', toggleDifficulty);


    // 3. MODAL DE MIEMBROS
    btnOpenMembers.addEventListener('click', () => {
        membersModal.classList.remove('hidden');
    });

    const closeMembersModal = () => membersModal.classList.add('hidden');
    
    closeMembers.addEventListener('click', closeMembersModal);
    btnConfirmMembers.addEventListener('click', () => {
        // Aquí podrías guardar los miembros seleccionados en un array
        closeMembersModal();
        alert("Miembros agregados temporalmente");
    });


    // 4. CREAR QUINIELA Y GENERAR CÓDIGO
    btnCreateFinal.addEventListener('click', () => {
        // Validar campos si es necesario
        
        // Generar código aleatorio de 6 caracteres
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        let code = "";
        for(let i=0; i<6; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        // Mostrar código en modal
        generatedCodeDisplay.textContent = code;
        
        // Ocultar vista crear y mostrar modal éxito
        createView.classList.add('hidden');
        successModal.classList.remove('hidden');
    });


    // 5. COPIAR CÓDIGO
    btnCopyCode.addEventListener('click', () => {
        const code = generatedCodeDisplay.textContent;
        
        navigator.clipboard.writeText(code).then(() => {
            const originalText = btnCopyCode.textContent;
            btnCopyCode.textContent = "✅"; // Feedback visual
            setTimeout(() => btnCopyCode.textContent = originalText, 1500);
        }).catch(err => {
            console.error('Error al copiar: ', err);
        });
    });

    // 6. FINALIZAR
    btnFinish.addEventListener('click', () => {
        successModal.classList.add('hidden');
        listView.classList.remove('hidden'); // Volver al inicio
    });

});