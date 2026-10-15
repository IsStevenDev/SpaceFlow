/* ==================================================================
   SpaceFlow — Comportamiento del lado del cliente
   Mejora la experiencia, pero NUNCA sustituye las validaciones,
   la autenticación ni la autorización del servidor.
   ================================================================== */

(function () {
  "use strict";

  /* ------------------------------------------------------------
     1. Menú lateral en dispositivos pequeños
     ------------------------------------------------------------ */
  const btnMenuMovil = document.querySelector("[data-menu-movil]");
  const sidebar = document.querySelector(".sf-sidebar");
  const fondoMenu = document.querySelector(".sf-fondo-menu");

  if (btnMenuMovil && sidebar) {
    btnMenuMovil.addEventListener("click", function () {
      sidebar.classList.toggle("abierto");
      if (fondoMenu) fondoMenu.classList.toggle("abierto");
    });
    if (fondoMenu) {
      fondoMenu.addEventListener("click", function () {
        sidebar.classList.remove("abierto");
        fondoMenu.classList.remove("abierto");
      });
    }
  }

  /* ------------------------------------------------------------
     2. Mostrar / ocultar contraseña
     ------------------------------------------------------------ */
  document.querySelectorAll("[data-alternar-clave]").forEach(function (boton) {
    boton.addEventListener("click", function () {
      const input = document.getElementById(boton.dataset.alternarClave);
      if (!input) return;
      const visible = input.type === "text";
      input.type = visible ? "password" : "text";
      boton.classList.toggle("bi-eye", visible);
      boton.classList.toggle("bi-eye-slash", !visible);
      input.classList.toggle("clave-visible", !visible);
    });
  });

  /* ------------------------------------------------------------
     3. Estado de carga al enviar formularios
        Evita envíos repetidos mientras el servidor procesa.
     ------------------------------------------------------------ */
  document.querySelectorAll("form[data-cargando]").forEach(function (formulario) {
    formulario.addEventListener("submit", function () {
      if (!formulario.checkValidity()) return; // El navegador ya avisó.
      const boton = formulario.querySelector('button[type="submit"]');
      if (!boton) return;
      boton.disabled = true;
      boton.classList.add("btn-cargando");
      const textoOriginal = boton.innerHTML;
      boton.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
      boton.dataset.textoOriginal = textoOriginal;
    });
  });

  /* ------------------------------------------------------------
     4. Búsqueda y filtros del listado de usuarios
        Filtrado en vivo sobre las filas ya cargadas en el DOM;
        la autoridad real la sigue teniendo el servidor.
     ------------------------------------------------------------ */
  const tablaUsuarios = document.querySelector("[data-tabla-usuarios]");
  if (tablaUsuarios) {
    const campoBusqueda = document.querySelector("[data-filtro-busqueda]");
    const selectorRol = document.querySelector("[data-filtro-rol]");
    const selectorEstado = document.querySelector("[data-filtro-estado]");
    const estadoVacio = document.querySelector("[data-estado-vacio]");
    const contador = document.querySelector("[data-contador-filas]");

    const aplicarFiltros = function () {
      const texto = (campoBusqueda ? campoBusqueda.value : "").trim().toLowerCase();
      const rol = selectorRol ? selectorRol.value : "";
      const estado = selectorEstado ? selectorEstado.value : "";
      let visibles = 0;

      tablaUsuarios.querySelectorAll("tbody tr").forEach(function (fila) {
        const coincideTexto =
          !texto ||
          fila.dataset.nombre.toLowerCase().includes(texto) ||
          fila.dataset.correo.toLowerCase().includes(texto);
        const coincideRol = !rol || fila.dataset.rol === rol;
        const coincideEstado = !estado || fila.dataset.estado === estado;
        const visible = coincideTexto && coincideRol && coincideEstado;
        fila.style.display = visible ? "" : "none";
        if (visible) visibles++;
      });

      if (estadoVacio) estadoVacio.classList.toggle("contenedor-vacio-oculto", visibles > 0);
      if (contador) contador.textContent = visibles;
    };

    if (campoBusqueda) campoBusqueda.addEventListener("input", aplicarFiltros);
    if (selectorRol) selectorRol.addEventListener("change", aplicarFiltros);
    if (selectorEstado) selectorEstado.addEventListener("change", aplicarFiltros);
  }

  /* ------------------------------------------------------------
     5. Confirmación destructiva (eliminar usuario o equipo)
        El modal recibe el nombre del registro y envía el formulario
        correspondiente solo cuando el usuario confirma.
     ------------------------------------------------------------ */
  const modalConfirmacion = document.getElementById("modalConfirmacion");
  if (modalConfirmacion) {
    modalConfirmacion.addEventListener("show.bs.modal", function (evento) {
      const disparador = evento.relatedTarget;
      if (!disparador) return;
      const destino = document.getElementById("confirmacionTexto");
      const formulario = document.getElementById("confirmacionFormulario");
      if (destino) destino.textContent = disparador.dataset.confirmar || "este registro";
      if (formulario) formulario.action = disparador.dataset.url || "#";
    });
  }

  /* ------------------------------------------------------------
     6. Validación visual de formularios (complementa al servidor)
     ------------------------------------------------------------ */
  document.querySelectorAll("form[data-validar]").forEach(function (formulario) {
    formulario.addEventListener("submit", function (evento) {
      if (!formulario.checkValidity()) {
        evento.preventDefault();
        evento.stopPropagation();
      }
      formulario.classList.add("was-validated");
    });
  });

  /* ------------------------------------------------------------
     7. Autocierre de alertas de éxito tras unos segundos
     ------------------------------------------------------------ */
  document.querySelectorAll(".alerta-sf[data-auto-cerrar]").forEach(function (alerta) {
    setTimeout(function () {
      alerta.style.transition = "opacity .4s ease";
      alerta.style.opacity = "0";
      setTimeout(function () { alerta.remove(); }, 400);
    }, 5000);
  });

  /* ------------------------------------------------------------
     8. Conteo animado de los valores de los KPI (count-up)
        Solo decora: el número final ya está en el HTML, así que si
        el JS falla o está deshabilitado, el dato sigue visible.
     ------------------------------------------------------------ */
  const sinMovimiento = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  document.querySelectorAll("[data-contar]").forEach(function (elemento) {
    const destino = parseFloat(elemento.dataset.contar);
    if (isNaN(destino) || sinMovimiento) return;

    const duracion = 700; // ms
    const inicio = performance.now();
    const paso = function (ahora) {
      const avance = Math.min((ahora - inicio) / duracion, 1);
      // Ease-out suave: rápido al principio, lento al final.
      const suave = 1 - Math.pow(1 - avance, 3);
      elemento.textContent = Math.round(destino * suave);
      if (avance < 1) requestAnimationFrame(paso);
    };
    requestAnimationFrame(paso);
  });

  /* ------------------------------------------------------------
     9. Sistema de toasts (avisos flotantes de la biblioteca)
        textContent en todo el contenido dinámico: inmune a XSS.
     ------------------------------------------------------------ */
  const contenedorToasts = document.querySelector("[data-toasts]");

  function mostrarToast(tipo, titulo, mensaje, persistente) {
    if (!contenedorToasts) return null;

    const toast = document.createElement("div");
    toast.className = "sf-toast toast-" + tipo + (persistente ? " toast-persistente" : "");
    toast.setAttribute("role", "status");

    const icone = document.createElement("i");
    icone.className = "bi " + (persistente || tipo === "error"
      ? "bi-exclamation-octagon"
      : tipo === "aviso"
        ? "bi-hourglass-split"
        : "bi-check-circle");
    icone.setAttribute("aria-hidden", "true");

    const cuerpo = document.createElement("div");
    const tituloEl = document.createElement("strong");
    tituloEl.className = "toast-titulo";
    tituloEl.textContent = titulo;
    const texto = document.createElement("span");
    texto.textContent = mensaje;
    cuerpo.appendChild(tituloEl);
    cuerpo.appendChild(texto);

    const cerrar = document.createElement("button");
    cerrar.type = "button";
    cerrar.className = "toast-cerrar";
    cerrar.setAttribute("aria-label", "Cerrar aviso");
    cerrar.innerHTML = '<i class="bi bi-x" aria-hidden="true"></i>';
    cerrar.addEventListener("click", function () { toast.remove(); });

    toast.appendChild(icone);
    toast.appendChild(cuerpo);
    toast.appendChild(cerrar);
    contenedorToasts.appendChild(toast);

    // Los avisos no críticos se cierran solos; el crítico persiste.
    if (!persistente) {
      setTimeout(function () {
        toast.style.transition = "opacity .3s ease";
        toast.style.opacity = "0";
        setTimeout(function () { toast.remove(); }, 300);
      }, 9000);
    }
    return toast;
  }

  /* ------------------------------------------------------------
     10. Cuenta regresiva en los badges [data-cuenta-regresiva]
        El epoch en el atributo data viene del servidor (la fecha y
        hora de fin reales de la reserva); aquí solo se cuenta hacia
        atrás para la comodidad visual del usuario.
     ------------------------------------------------------------ */
  const avisosLanzados = new Set(); // Evita repetir toasts por reserva.

  function restantesTexto(segundos) {
    const abs = Math.abs(segundos);
    const horas = Math.floor(abs / 3600);
    const minutos = Math.floor((abs % 3600) / 60);
    const partes = [];
    if (horas > 0) partes.push(horas + " h");
    partes.push(minutos + " min");
    return (segundos < 0 ? "vencida hace " : "faltan ") + partes.join(" ");
  }

  function actualizarCuentaRegresiva() {
    const ahora = Math.floor(Date.now() / 1000);
    const cfg = document.querySelector("[data-config-biblioteca]");
    const avisoTemprano = parseInt(cfg?.dataset.avisoTemprano || "30", 10) * 60;
    const avisoTardio = parseInt(cfg?.dataset.avisoTardio || "10", 10) * 60;

    document.querySelectorAll("[data-cuenta-regresiva]").forEach(function (badge) {
      const fin = parseInt(badge.dataset.cuentaRegresiva, 10);
      if (isNaN(fin)) return;

      const restantes = fin - ahora;
      const texto = badge.querySelector("[data-texto-cuenta]");
      if (texto) texto.textContent = restantesTexto(restantes);

      badge.classList.remove("badge-cuenta-normal", "badge-cuenta-aviso", "badge-cuenta-critico",
        "badge-venciendo", "badge-atrasado");
      if (restantes <= 0) {
        badge.classList.add("badge-cuenta-critico", "badge-atrasado");
      } else if (restantes <= avisoTardio) {
        badge.classList.add("badge-cuenta-critico", "badge-venciendo");
      } else if (restantes <= avisoTemprano) {
        badge.classList.add("badge-cuenta-aviso", "badge-venciendo");
      } else {
        badge.classList.add("badge-cuenta-normal");
      }
    });
  }
  actualizarCuentaRegresiva();
  setInterval(actualizarCuentaRegresiva, 15000);

  /* ------------------------------------------------------------
     11. Polling de notificaciones (cada 30 s)
        El servidor calcula los segundos restantes de cada reserva.
        El administrador ve además su panel "Próximas a vencer"
        refrescado en vivo; el estudiante recibe avisos de vencimiento.
     ------------------------------------------------------------ */
  const configBiblioteca = document.querySelector("[data-config-biblioteca]");

  function refrescarPanelVencimientos(datos) {
    const panel = document.querySelector("[data-panel-vencimientos]");
    if (!panel) return;
    const contador = panel.querySelector("[data-vencimientos-contador]");
    const lista = panel.querySelector("[data-vencimientos-lista]");
    const vencer = datos.vencer || [];
    const atrasados = datos.atrasados || [];

    if (contador) {
      contador.textContent = vencer.length + " por vencer · " + atrasados.length + " atrasado(s)";
      contador.className = "badge-sf " + (vencer.length + atrasados.length > 0 ? "badge-warning" : "badge-info");
    }
    if (!lista) return;

    // Reconstrucción segura del contenido: nodos + textContent.
    lista.textContent = "";
    if (vencer.length === 0 && atrasados.length === 0) {
      const p = document.createElement("p");
      p.className = "texto-auxiliar mb-0";
      p.innerHTML = '<i class="bi bi-check-circle me-1" aria-hidden="true"></i>';
      p.appendChild(document.createTextNode(
        "No hay reservas por vencer ni préstamos atrasados en este momento."));
      lista.appendChild(p);
      return;
    }
    const fila = document.createElement("div");
    fila.className = "row g-2";
    vencer.forEach(function (r) {
      const col = document.createElement("div");
      col.className = "col-md-6";
      const alerta = document.createElement("div");
      alerta.className = "alerta-sf alerta-aviso mb-0";
      alerta.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';
      const span = document.createElement("span");
      span.appendChild(document.createTextNode(
        "PC " + r.equipo_codigo + " — " + r.usuario_nombre + " termina a las " + r.hora_fin));
      alerta.appendChild(span);
      col.appendChild(alerta);
      fila.appendChild(col);
    });
    atrasados.forEach(function (r) {
      const col = document.createElement("div");
      col.className = "col-md-6";
      const alerta = document.createElement("div");
      alerta.className = "alerta-sf alerta-error mb-0";
      alerta.innerHTML = '<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>';
      const span = document.createElement("span");
      span.appendChild(document.createTextNode(
        "PC " + r.equipo_codigo + " — " + r.usuario_nombre +
        " vencida sin devolución (hora fin " + r.hora_fin + ")"));
      alerta.appendChild(span);
      col.appendChild(alerta);
      fila.appendChild(col);
    });
    lista.appendChild(fila);
  }

  function procesarAvisosEstudiante(datos) {
    const cfg = configBiblioteca;
    const avisoTemprano = parseInt(cfg?.dataset.avisoTemprano || "30", 10) * 60;
    const avisoTardio = parseInt(cfg?.dataset.avisoTardio || "10", 10) * 60;

    (datos.propias || []).forEach(function (r) {
      if (r.estado !== "prestamo") return;
      const seg = r.segundos_restantes;
      const claveCritico = "critico-" + r.id;
      const claveAviso = "aviso-" + r.id;

      if (seg > 0 && seg <= avisoTardio && !avisosLanzados.has(claveCritico)) {
        avisosLanzados.add(claveCritico);
        mostrarToast("error", "Tu tiempo se está acabando",
          "El portátil " + r.equipo_codigo + " debe devolverse pronto. "
          + "Dirígete a la biblioteca para entregarlo.", true);
      } else if (seg > 0 && seg <= avisoTemprano && !avisosLanzados.has(claveAviso)) {
        avisosLanzados.add(claveAviso);
        mostrarToast("aviso", "Tu reserva está por vencer",
          "El portátil " + r.equipo_codigo + " debe devolverse en los próximos "
          + Math.ceil(seg / 60) + " minutos.", false);
      }
    });
  }

  if (configBiblioteca && contenedorToasts) {
    const urlNotificaciones = configBiblioteca.dataset.urlNotificaciones;

    function consultarNotificaciones() {
      fetch(urlNotificaciones, { credentials: "same-origin" })
        .then(function (respuesta) {
          if (!respuesta.ok) throw new Error("HTTP " + respuesta.status);
          return respuesta.json();
        })
        .then(function (datos) {
          if (configBiblioteca.dataset.esAdmin === "1") {
            refrescarPanelVencimientos(datos);
          }
          procesarAvisosEstudiante(datos);
        })
        .catch(function () {
          // Silencioso: las notificaciones son decorativas; la página
          // ya está renderizada y su contenido principal no depende de esto.
        });
    }
    consultarNotificaciones();
    setInterval(consultarNotificaciones, 30000);
  }

  /* ------------------------------------------------------------
     12. Autoenvío del buscador de disponibilidad de reservas
        Al cambiar la fecha, la hora o la duración se vuelve a pedir
        al servidor la lista de portátiles libres para esa franja.
     ------------------------------------------------------------ */
  document.querySelectorAll("form[data-buscar-disponibilidad]").forEach(function (formulario) {
    formulario.querySelectorAll("select, input[type='date']").forEach(function (campo) {
      campo.addEventListener("change", function () { formulario.submit(); });
    });
  });
})();
