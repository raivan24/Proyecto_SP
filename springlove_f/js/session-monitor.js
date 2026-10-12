let temporizadorInactividad;
let tiempoLimite = 300000; // 5 minutos en milisegundos
let tiempoAlerta = 60000;  // Mostrar alerta 1 minuto antes (60s)

function iniciarTemporizador() {
    clearTimeout(temporizadorInactividad);
    temporizadorInactividad = setTimeout(mostrarAlertaExpiracion, tiempoLimite - tiempoAlerta);
}

function reiniciarTemporizador() {
    if (!Swal.isVisible()) {
        iniciarTemporizador();
    }
}

function mostrarAlertaExpiracion() {
    let tiempoRestante = tiempoAlerta / 1000;
    let intervaloCuentaRegresiva;

    Swal.fire({
        title: '¡Tu sesión está por caducar!',
        html: 'Serás redirigido en <b>60</b> segundos debido a la inactividad.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Mantener sesión abierta',
        cancelButtonText: 'Cerrar sesión ahora',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            intervaloCuentaRegresiva = setInterval(() => {
                tiempoRestante--;
                const b = Swal.getHtmlContainer().querySelector('b');
                if (b) b.textContent = tiempoRestante;

                if (tiempoRestante <= 0) {
                    clearInterval(intervaloCuentaRegresiva);
                    window.location.href = "../vista/logout.php"; 
                }
            }, 1000);
        },
        willClose: () => {
            clearInterval(intervaloCuentaRegresiva);
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Llama a PHP para actualizar la sesión en el servidor
            fetch('../vista/verificar_sesion.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === "active") {
                        iniciarTemporizador(); 
                    } else {
                        window.location.href = "../vista/index.php";
                    }
                });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            window.location.href = "../vista/logout.php";
        }
    });
}

// Captura cualquier interacción del usuario
window.onload = iniciarTemporizador;
document.onmousemove = reiniciarTemporizador;
document.onkeypress = reiniciarTemporizador;
document.onclick = reiniciarTemporizador;
document.onscroll = reiniciarTemporizador; // Añadido para detectar scroll
