// Valida formularios y consulta las franjas permitidas en el servidor.
document.addEventListener("DOMContentLoaded", function () {
    const hoy = obtenerFechaLocal();
    const fechaMaxima = obtenerFechaLimite();

    document.querySelectorAll('input[type="date"]').forEach(function (campoFecha) {
        campoFecha.min = hoy;
        campoFecha.max = fechaMaxima;
    });

    document.querySelectorAll("form[data-horarios-url]").forEach(function (formulario) {
        const escenario = formulario.querySelector('[name="escenario"]');
        const fecha = formulario.querySelector('[name="fecha"]');
        const hora = formulario.querySelector('[name="hora_inicio"]');

        if (!escenario || !fecha || !hora) {
            return;
        }

        const cargarFranjas = async function () {
            const idEscenario = escenario.value;
            const fechaElegida = fecha.value;
            const horaAnterior = hora.value || hora.dataset.horaActual || "";
            hora.disabled = true;
            hora.replaceChildren(new Option("Consultando horarios...", ""));

            if (!idEscenario || !fechaElegida) {
                hora.replaceChildren(new Option("Selecciona escenario y fecha", ""));
                mostrarMensaje(formulario, "");
                return;
            }

            const parametros = new URLSearchParams({
                escenario: idEscenario,
                fecha: fechaElegida
            });
            if (formulario.dataset.reservaId) {
                parametros.set("reserva", formulario.dataset.reservaId);
            }

            try {
                const respuesta = await fetch(formulario.dataset.horariosUrl + "?" + parametros.toString(), {
                    headers: { "Accept": "application/json" }
                });
                const datos = await respuesta.json();
                if (!respuesta.ok) {
                    throw new Error(datos.mensaje || "No fue posible consultar las franjas.");
                }

                hora.replaceChildren(new Option(
                    datos.franjas.length ? "Selecciona una franja" : "No hay franjas disponibles",
                    ""
                ));
                datos.franjas.forEach(function (franja) {
                    const opcion = new Option(franja.etiqueta, franja.inicio);
                    hora.add(opcion);
                });
                if (horaAnterior && Array.from(hora.options).some(function (opcion) {
                    return opcion.value === horaAnterior;
                })) {
                    hora.value = horaAnterior;
                }
                hora.disabled = datos.franjas.length === 0;
                mostrarMensaje(formulario, datos.mensaje || "");
            } catch (error) {
                hora.replaceChildren(new Option("No se pudieron cargar las franjas", ""));
                hora.disabled = true;
                mostrarMensaje(formulario, error.message || "No fue posible consultar los horarios.");
            }
        };

        escenario.addEventListener("change", cargarFranjas);
        fecha.addEventListener("change", cargarFranjas);
        if (escenario.value && fecha.value) {
            cargarFranjas();
        } else {
            hora.disabled = true;
        }
    });

    document.querySelectorAll("form[data-validacion]").forEach(function (formulario) {
        formulario.noValidate = true;

        formulario.addEventListener("submit", function (evento) {
            const telefono = formulario.querySelector('[name="telefono"]');
            const nombre = formulario.querySelector('[name="nombre_solicitante"], [name="nombre"]');
            const fecha = formulario.querySelector('[name="fecha"]');
            let error = "";

            if (!formulario.checkValidity()) {
                error = "Completa todos los campos obligatorios y selecciona una franja disponible.";
            } else if (nombre && (!nombre.value.trim() || !/^[\p{L}\p{M} ]+$/u.test(nombre.value.trim()))) {
                error = "El nombre solo puede contener letras y espacios.";
            } else if (telefono && !/^3[0-9]{9}$/.test(telefono.value.trim())) {
                error = "El celular debe tener 10 dígitos y comenzar por 3.";
            } else if (fecha && (fecha.value < hoy || fecha.value > fechaMaxima)) {
                error = "La fecha debe estar entre hoy y los próximos 30 días.";
            }

            if (error) {
                evento.preventDefault();
                mostrarMensaje(formulario, error);
                const primerInvalido = formulario.querySelector(":invalid");
                if (primerInvalido) {
                    primerInvalido.focus();
                }
                return;
            }

            const confirmacion = formulario.dataset.confirmacion;
            if (confirmacion && !window.confirm(confirmacion)) {
                evento.preventDefault();
            }
        });
    });
});

function mostrarMensaje(formulario, texto) {
    const mensaje = formulario.querySelector("#mensaje-validacion");
    if (!mensaje) {
        return;
    }

    mensaje.textContent = texto;
    mensaje.hidden = texto === "";
}

function obtenerFechaLocal() {
    const fecha = new Date();
    const diferenciaZona = fecha.getTimezoneOffset() * 60000;
    return new Date(fecha.getTime() - diferenciaZona).toISOString().slice(0, 10);
}

function obtenerFechaLimite() {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() + 30);
    const diferenciaZona = fecha.getTimezoneOffset() * 60000;
    return new Date(fecha.getTime() - diferenciaZona).toISOString().slice(0, 10);
}
