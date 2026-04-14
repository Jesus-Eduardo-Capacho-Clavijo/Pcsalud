(() => {
    function aplicarTema(theme) {
        const body = document.body;
        if (!body) return;
        body.classList.toggle("dark-mode", theme === "dark");
        const label = document.getElementById("themeLabel");
        if (label) {
            label.textContent = theme === "dark" ? "Modo oscuro" : "Modo claro";
        }
    }

    function iniciarTema() {
        const saved = localStorage.getItem("pcsalud-theme");
        const theme = saved === "dark" ? "dark" : "light";
        aplicarTema(theme);

        const toggle = document.getElementById("themeToggle");
        if (toggle) {
            toggle.checked = theme === "dark";
            toggle.addEventListener("change", () => {
                const next = toggle.checked ? "dark" : "light";
                localStorage.setItem("pcsalud-theme", next);
                aplicarTema(next);
            });
        }
    }

    function normalizarTelefonoColombia(numeroRaw) {
        let numero = String(numeroRaw || "").replace(/\D/g, "");
        numero = numero.replace(/^0+/, "");
        if (!numero.startsWith("57")) {
            numero = "57" + numero;
        }
        return numero;
    }

    function construirMensaje(datos) {
        return [
            "Hola. Somos PC SALUD.",
            "Te compartimos el reporte de tu equipo:",
            "",
            "*Marca:* " + (datos.marca || "No registrada"),
            "*Diagnostico:* " + (datos.diagnostico || "Sin diagnostico"),
            "*Fecha estimada de entrega:* " + (datos.fechaEntrega || "No definida"),
            "*Tu Token de seguimiento web es:* " + (datos.token || "No disponible"),
            "",
            "Gracias por confiar en nosotros."
        ].join("\n");
    }

    function notificarWhatsApp(config) {
        const token = config.token || "";
        const marca = config.marca || "";
        const whatsapp = config.whatsapp || "";
        const diagnostico = config.diagnosticoSelector
            ? (document.querySelector(config.diagnosticoSelector)?.value || "").trim()
            : (config.diagnostico || "");
        const fechaEntrega = config.fechaSelector
            ? (document.querySelector(config.fechaSelector)?.value || "").trim()
            : (config.fechaEntrega || "");

        const numeroLimpio = normalizarTelefonoColombia(whatsapp);
        if (numeroLimpio.length < 10) {
            alert("El numero de WhatsApp no es valido.");
            return;
        }

        const mensaje = construirMensaje({
            token,
            marca,
            diagnostico,
            fechaEntrega
        });
        const mensajeCodificado = encodeURIComponent(mensaje);
        window.open("https://wa.me/" + numeroLimpio + "?text=" + mensajeCodificado, "_blank");
    }

    async function enviarDecisionPresupuesto(config) {
        const payload = {
            token: config.token || "",
            decision: config.decision || ""
        };

        const resp = await fetch("../backend/procesar_presupuesto.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        });

        if (!resp.ok) {
            throw new Error("No se pudo registrar la decision.");
        }

        return resp.json();
    }

    window.PcSaludUI = {
        initTheme: iniciarTema,
        notificarWhatsApp,
        enviarDecisionPresupuesto
    };
})();
