document.addEventListener('DOMContentLoaded', () => {
    const listContainer = document.querySelector('.crowns-list');
    
    // Cargar coronas al iniciar
    loadCrowns();

    function loadCrowns() {
        fetch('php/crowns.php')
            .then(r => r.json())
            .then(resp => {
                if(resp.success) {
                    renderCrowns(resp.data);
                } else {
                    console.error(resp.message);
                }
            })
            .catch(e => console.error(e));
    }

    function renderCrowns(crowns) {
        listContainer.innerHTML = ''; 

        crowns.forEach(c => {
            const item = document.createElement('div');
            item.className = `crown-item ${c.estado}`;
            
            let btnHtml = '';
            // LOGICA NUEVA: Botón "Quitar" si está activa
            if (c.estado === 'active') {
                // Estilo inline rojo para destacar la acción de quitar
                btnHtml = `<button style="background-color:#ff4d4d; color:white;" onclick="deactivateCrown()">Quitar</button>`;
            } else if (c.estado === 'available') {
                btnHtml = `<button class="btn-blue" onclick="activateCrown(${c.id})">Activar</button>`;
            } else {
                btnHtml = `<button class="btn-locked" disabled>Bloqueada</button>`;
            }

            item.innerHTML = `
                <div class="crown-info">
                    <img src="${c.imagen}" alt="Corona">
                    <div class="crown-text">
                        <h3>${c.nombre}</h3>
                        <p>${c.descripcion}</p>
                    </div>
                </div>
                ${btnHtml}
            `;
            listContainer.appendChild(item);
        });
    }

    // Activar corona
    window.activateCrown = function(id) {
        fetch('php/crowns.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'activate', crown_id: id })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                loadCrowns(); // Recargar lista
                updateLocalUserCrown(); // Actualizar header
            } else {
                alert(data.message);
            }
        });
    }

    // Desactivar (Quitar) corona
    window.deactivateCrown = function() {
        fetch('php/crowns.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'deactivate' })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                loadCrowns();
                updateLocalUserCrown();
            } else {
                alert(data.message);
            }
        });
    }

    function updateLocalUserCrown() {
        // Pedir perfil actualizado para refrescar la imagen del header
        fetch('php/profile.php')
            .then(r => r.json())
            .then(d => {
                if(d.success) {
                    const currentUser = JSON.parse(localStorage.getItem('kingniela_user'));
                    const updatedUser = { ...currentUser, ...d.data };
                    localStorage.setItem('kingniela_user', JSON.stringify(updatedUser));
                    
                    // Opcional: Forzar recarga para ver cambios en el header
                    // location.reload(); 
                }
            });
    }
});