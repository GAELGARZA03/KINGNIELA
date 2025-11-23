document.addEventListener('DOMContentLoaded', function() {
    
    // Referencias DOM
    const profileForm = document.getElementById('profileForm');
    const inpNombre = document.getElementById('nombre'); // Ojo: este es visual, la BD usa 'Usuario'
    const inpUsuario = document.getElementById('usuario');
    const inpFecha = document.getElementById('fecha_nacimiento');
    const selGenero = document.getElementById('genero');
    const inpCorreo = document.getElementById('correo');
    const chkEncriptacion = document.getElementById('encriptacion');
    const imgPreview = document.getElementById('profile-pic-preview');
    const headerImg = document.getElementById('header-profile-pic');
    const fileInput = document.getElementById('foto-upload');
    const btnLogout = document.getElementById('btn-logout');

    // 1. CARGAR DATOS AL INICIAR
    fetch('php/profile.php')
    .then(res => {
        if (res.status === 401) {
            window.location.href = 'IniciarSesion.html'; // Si no hay sesión, fuera
            throw new Error('No autorizado');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const u = data.data;
            // Rellenar campos
            // Nota: Tu tabla tiene Nombre_Usuario pero el form pide Nombre Y Usuario.
            // Usaremos Nombre_Usuario para ambos por ahora, o ajusta según prefieras.
            if(inpNombre) inpNombre.value = u.Nombre_Usuario; 
            if(inpUsuario) inpUsuario.value = u.Nombre_Usuario;
            
            if(inpFecha) inpFecha.value = u.Fecha_Nacimiento;
            if(selGenero) selGenero.value = u.Genero || 'Otro';
            if(inpCorreo) inpCorreo.value = u.Correo;
            
            // Checkbox (1 = true, 0 = false)
            if(chkEncriptacion) chkEncriptacion.checked = (u.Preferencias_Encriptacion == 1);

            // Foto
            if (u.Avatar) {
                imgPreview.src = u.Avatar;
                if(headerImg) headerImg.src = u.Avatar;
            }
        } else {
            alert("Error cargando perfil: " + data.message);
        }
    })
    .catch(err => console.error(err));


    // 2. PREVISUALIZAR FOTO ANTES DE SUBIR
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }


    // 3. GUARDAR CAMBIOS
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(profileForm);

            fetch('php/profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Perfil actualizado correctamente');
                    // Opcional: Recargar para asegurar que todo esté fresco
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => console.error('Error al guardar:', err));
        });
    }


// 4. CERRAR SESIÓN
    if (btnLogout) {
        btnLogout.addEventListener('click', function() {
            // 1. Desconectar Socket si existe
            if (window.socket) {
                window.socket.disconnect();
            }
            
            // 2. Borrar datos locales
            localStorage.removeItem('kingniela_user');
            
            // 3. Cerrar sesión en PHP y redirigir
            fetch('php/logout.php')
                .then(() => window.location.href = 'Principal.html');
        });
    }
});