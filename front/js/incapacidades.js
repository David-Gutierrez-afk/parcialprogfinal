import { API_URLS, TIPO_INCAPACIDAD_LABELS } from "./config.js";
import { HttpClient } from "./httpClient.js";
import {
    clearAlert,
    closeModal,
    createStatusBadge,
    openModal,
    showAlert,
} from "./uiFeedback.js";

function calculateInclusiveDays(fechaInicio, fechaFin) {
    if (!fechaInicio || !fechaFin || fechaFin < fechaInicio) {
        return null;
    }

    const startDate = new Date(`${fechaInicio}T00:00:00Z`);
    const endDate = new Date(`${fechaFin}T00:00:00Z`);
    if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
        return null;
    }

    const millisecondsPerDay = 86400000;
    const differenceDays = Math.round((endDate - startDate) / millisecondsPerDay);
    return differenceDays >= 0 ? differenceDays + 1 : null;
}

export function createIncapacidadesModule(globalAlertContainer, options = {}) {
    const onOpenSeguimiento = typeof options.onOpenSeguimiento === "function"
        ? options.onOpenSeguimiento
        : () => {};

    const filterForm = document.forms.incapacidadesFilterForm;
    const filterEmpleadoSelect = document.getElementById("filtroIncapacidadEmpleado");
    const clearFiltersButton = document.getElementById("clearIncapacidadesFiltersButton");
    const refreshButton = document.getElementById("refreshIncapacidadesButton");
    const openCreateButton = document.getElementById("openCreateIncapacidadButton");
    const tableBody = document.getElementById("incapacidadesTableBody");

    const kpiTotal = document.getElementById("kpiIncapacidadesTotal");
    const kpiRevision = document.getElementById("kpiIncapacidadesRevision");
    const kpiAprobadas = document.getElementById("kpiIncapacidadesAprobadas");
    const kpiFinalizadas = document.getElementById("kpiIncapacidadesFinalizadas");

    const incapacidadModal = document.getElementById("incapacidadModal");
    const incapacidadModalTitle = document.getElementById("incapacidadModalTitle");
    const incapacidadModalAlert = document.getElementById("incapacidadModalAlert");
    const incapacidadForm = document.forms.incapacidadForm;
    const incapacidadEmpleadoSelect = document.getElementById("incapacidadEmpleadoId");
    const incapacidadEstadoGroup = document.getElementById("incapacidadEstadoGroup");
    const incapacidadDiasPreview = document.getElementById("incapacidadDiasPreview");
    const saveIncapacidadButton = document.getElementById("saveIncapacidadButton");
    const closeIncapacidadModalButton = document.getElementById("closeIncapacidadModalButton");
    const cancelIncapacidadModalButton = document.getElementById("cancelIncapacidadModalButton");

    const finalizeModal = document.getElementById("finalizarIncapacidadModal");
    const finalizeConfirmMessage = document.getElementById("finalizarIncapacidadConfirmMessage");
    const closeFinalizeModalButton = document.getElementById("closeFinalizarIncapacidadButton");
    const cancelFinalizeModalButton = document.getElementById("cancelFinalizarIncapacidadButton");
    const confirmFinalizeModalButton = document.getElementById("confirmFinalizarIncapacidadButton");

    let empleadosById = new Map();
    let cachedEmpleados = [];
    let pendingFinalizeIncapacidad = null;

    function formatEmpleadoLabel(empleadoId) {
        const empleado = empleadosById.get(Number(empleadoId));
        if (!empleado) {
            return `Empleado #${empleadoId}`;
        }
        return `${empleado.nombres} ${empleado.apellidos} (${empleado.documento})`;
    }

    function populateEmpleadoSelectors(empleadosList) {
        const previousFilterValue = filterEmpleadoSelect.value;
        filterEmpleadoSelect.textContent = "";

        const allOption = document.createElement("option");
        allOption.value = "";
        allOption.textContent = "Todos los empleados";
        filterEmpleadoSelect.appendChild(allOption);

        empleadosList.forEach((empleado) => {
            const optionElement = document.createElement("option");
            optionElement.value = String(empleado.id);
            optionElement.textContent = `${empleado.nombres} ${empleado.apellidos} (${empleado.documento})`;
            filterEmpleadoSelect.appendChild(optionElement);
        });

        filterEmpleadoSelect.value = previousFilterValue;
    }

    function populateFormEmpleadoSelect(onlyActive, selectedId = "") {
        incapacidadEmpleadoSelect.textContent = "";

        const placeholderOption = document.createElement("option");
        placeholderOption.value = "";
        placeholderOption.textContent = "Seleccione un empleado...";
        incapacidadEmpleadoSelect.appendChild(placeholderOption);

        const sourceList = onlyActive
            ? cachedEmpleados.filter((empleado) => empleado.estado === "activo")
            : cachedEmpleados;

        sourceList.forEach((empleado) => {
            const optionElement = document.createElement("option");
            optionElement.value = String(empleado.id);
            const inactiveSuffix = empleado.estado === "inactivo" ? " [Inactivo]" : "";
            optionElement.textContent = `${empleado.nombres} ${empleado.apellidos} — Doc. ${empleado.documento}${inactiveSuffix}`;
            incapacidadEmpleadoSelect.appendChild(optionElement);
        });

        incapacidadEmpleadoSelect.value = selectedId ? String(selectedId) : "";
    }

    async function loadEmpleadosCatalog() {
        try {
            const responseData = await HttpClient.get(`${API_URLS.EMPLEADOS}/empleados`);
            cachedEmpleados = Array.isArray(responseData.data) ? responseData.data : [];
            empleadosById = new Map(
                cachedEmpleados.map((empleado) => [Number(empleado.id), empleado])
            );
            populateEmpleadoSelectors(cachedEmpleados);
        } catch (catalogError) {
            // Si falla la carga auxiliar, se muestran los IDs numéricos sin bloquear el módulo
        }
    }

    function buildQueryString() {
        const params = new URLSearchParams();
        const empleadoId = filterForm.empleado_id.value.trim();
        const fecha = filterForm.fecha.value.trim();
        const estado = filterForm.estado.value.trim();
        const tipo = filterForm.tipo.value.trim();

        if (empleadoId) {
            params.set("empleado_id", empleadoId);
        }
        if (fecha) {
            params.set("fecha", fecha);
        }
        if (estado) {
            params.set("estado", estado);
        }
        if (tipo) {
            params.set("tipo", tipo);
        }

        const queryText = params.toString();
        return queryText ? `?${queryText}` : "";
    }

    function updateKpis(incapacidadesList) {
        const totalCount = incapacidadesList.length;
        const revisionCount = incapacidadesList.filter((item) => item.estado === "en_revision").length;
        const aprobadasCount = incapacidadesList.filter((item) => item.estado === "aprobada").length;
        const finalizadasCount = incapacidadesList.filter((item) => item.estado === "finalizada").length;

        kpiTotal.textContent = String(totalCount);
        kpiRevision.textContent = String(revisionCount);
        kpiAprobadas.textContent = String(aprobadasCount);
        kpiFinalizadas.textContent = String(finalizadasCount);
    }

    function renderEmptyRow(message) {
        tableBody.textContent = "";
        const emptyRow = document.createElement("tr");
        const emptyCell = document.createElement("td");
        emptyCell.colSpan = 10;
        emptyCell.className = "table-empty-cell";
        emptyCell.textContent = message;
        emptyRow.appendChild(emptyCell);
        tableBody.appendChild(emptyRow);
    }

    function createTextCell(textValue) {
        const cell = document.createElement("td");
        cell.textContent = textValue ?? "-";
        return cell;
    }

    function renderIncapacidadesTable(incapacidadesList) {
        updateKpis(incapacidadesList);

        if (incapacidadesList.length === 0) {
            renderEmptyRow("No se encontraron incapacidades médicas con los criterios seleccionados.");
            return;
        }

        tableBody.textContent = "";

        incapacidadesList.forEach((incapacidad) => {
            const tableRow = document.createElement("tr");
            const isFinalizada = incapacidad.estado === "finalizada";
            const isRechazada = incapacidad.estado === "rechazada";

            tableRow.appendChild(createTextCell(`#${incapacidad.id}`));
            tableRow.appendChild(createTextCell(formatEmpleadoLabel(incapacidad.empleado_id)));
            tableRow.appendChild(
                createTextCell(TIPO_INCAPACIDAD_LABELS[incapacidad.tipo] || incapacidad.tipo)
            );
            tableRow.appendChild(createTextCell(incapacidad.fecha_inicio));
            tableRow.appendChild(createTextCell(incapacidad.fecha_fin));
            tableRow.appendChild(createTextCell(`${incapacidad.dias_incapacidad} d`));
            tableRow.appendChild(createTextCell(incapacidad.diagnostico_general));
            tableRow.appendChild(createTextCell(incapacidad.entidad_medica));

            const statusCell = document.createElement("td");
            statusCell.appendChild(createStatusBadge(incapacidad.estado));
            tableRow.appendChild(statusCell);

            const actionsCell = document.createElement("td");
            const actionsGroup = document.createElement("div");
            actionsGroup.className = "table-actions-group";

            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.className = "button button-secondary button-small";
            editButton.textContent = "Editar";
            editButton.disabled = isFinalizada;
            editButton.addEventListener("click", () => openEditModal(incapacidad));
            actionsGroup.appendChild(editButton);

            const finalizeButton = document.createElement("button");
            finalizeButton.type = "button";
            finalizeButton.className = "button button-warning button-small";
            finalizeButton.textContent = "Finalizar";
            finalizeButton.disabled = isFinalizada || isRechazada;
            finalizeButton.addEventListener("click", () => openFinalizeConfirmModal(incapacidad));
            actionsGroup.appendChild(finalizeButton);

            const historyButton = document.createElement("button");
            historyButton.type = "button";
            historyButton.className = "button button-primary button-small";
            historyButton.textContent = "Seguimiento";
            historyButton.addEventListener("click", () => onOpenSeguimiento(incapacidad.id));
            actionsGroup.appendChild(historyButton);

            actionsCell.appendChild(actionsGroup);
            tableRow.appendChild(actionsCell);

            tableBody.appendChild(tableRow);
        });
    }

    async function loadIncapacidades() {
        renderEmptyRow("Cargando incapacidades...");
        await loadEmpleadosCatalog();

        try {
            const queryString = buildQueryString();
            const responseData = await HttpClient.get(
                `${API_URLS.INCAPACIDADES}/incapacidades${queryString}`
            );
            const incapacidadesList = Array.isArray(responseData.data) ? responseData.data : [];
            renderIncapacidadesTable(incapacidadesList);
        } catch (loadError) {
            renderEmptyRow("Error al consultar las incapacidades.");
            showAlert(
                globalAlertContainer,
                loadError.message || "No fue posible consultar el listado de incapacidades.",
                "error",
                loadError.fieldErrors
            );
        }
    }

    function updateDaysPreview() {
        const fechaInicio = incapacidadForm.fecha_inicio.value.trim();
        const fechaFin = incapacidadForm.fecha_fin.value.trim();

        if (!fechaInicio || !fechaFin) {
            incapacidadDiasPreview.textContent = "0 días";
            return;
        }

        const totalDays = calculateInclusiveDays(fechaInicio, fechaFin);
        if (totalDays === null) {
            incapacidadDiasPreview.textContent = "Rango inválido";
            return;
        }

        incapacidadDiasPreview.textContent = totalDays === 1 ? "1 día" : `${totalDays} días`;
    }

    function openCreateModal() {
        clearAlert(incapacidadModalAlert);
        incapacidadForm.reset();
        incapacidadForm.id.value = "";
        populateFormEmpleadoSelect(true);
        incapacidadEmpleadoSelect.disabled = false;

        incapacidadModalTitle.textContent = "Registrar incapacidad médica";
        saveIncapacidadButton.textContent = "Registrar incapacidad";
        incapacidadEstadoGroup.classList.add("is-hidden");
        updateDaysPreview();
        openModal(incapacidadModal);
    }

    function openEditModal(incapacidad) {
        clearAlert(incapacidadModalAlert);
        incapacidadForm.reset();
        incapacidadForm.id.value = String(incapacidad.id);
        populateFormEmpleadoSelect(false, incapacidad.empleado_id);
        incapacidadEmpleadoSelect.disabled = true;

        incapacidadForm.fecha_inicio.value = incapacidad.fecha_inicio || "";
        incapacidadForm.fecha_fin.value = incapacidad.fecha_fin || "";
        incapacidadForm.tipo.value = incapacidad.tipo || "";
        incapacidadForm.entidad_medica.value = incapacidad.entidad_medica || "";
        incapacidadForm.diagnostico_general.value = incapacidad.diagnostico_general || "";
        incapacidadForm.observaciones.value = incapacidad.observaciones || "";
        incapacidadForm.estado.value = incapacidad.estado || "registrada";

        incapacidadModalTitle.textContent = `Editar incapacidad #${incapacidad.id}`;
        saveIncapacidadButton.textContent = "Guardar cambios";
        incapacidadEstadoGroup.classList.remove("is-hidden");
        updateDaysPreview();
        openModal(incapacidadModal);
    }

    function openFinalizeConfirmModal(incapacidad) {
        pendingFinalizeIncapacidad = incapacidad;
        const empleadoLabel = formatEmpleadoLabel(incapacidad.empleado_id);
        finalizeConfirmMessage.textContent = `¿Desea marcar como FINALIZADA la incapacidad #${incapacidad.id} de ${empleadoLabel} (${incapacidad.fecha_inicio} a ${incapacidad.fecha_fin})? Una vez finalizada no podrá editarse.`;
        openModal(finalizeModal);
    }

    function validateIncapacidadPayload(payload, isEditing) {
        const errors = {};

        if (!isEditing && !payload.empleado_id) {
            errors.empleado_id = "Debe seleccionar un empleado activo.";
        }

        if (!payload.fecha_inicio) {
            errors.fecha_inicio = "La fecha de inicio es obligatoria.";
        }

        if (!payload.fecha_fin) {
            errors.fecha_fin = "La fecha fin es obligatoria.";
        }

        if (payload.fecha_inicio && payload.fecha_fin && payload.fecha_fin < payload.fecha_inicio) {
            errors.fecha_fin = "La fecha fin no puede ser menor a la fecha de inicio.";
        }

        if (!payload.tipo) {
            errors.tipo = "Debe seleccionar un tipo de incapacidad.";
        }

        if (!payload.entidad_medica) {
            errors.entidad_medica = "La entidad médica es obligatoria.";
        }

        if (!payload.diagnostico_general) {
            errors.diagnostico_general = "El diagnóstico general es obligatorio.";
        }

        return errors;
    }

    async function handleIncapacidadSubmit(submitEvent) {
        submitEvent.preventDefault();
        clearAlert(incapacidadModalAlert);
        clearAlert(globalAlertContainer);

        const editingId = incapacidadForm.id.value.trim();
        const isEditing = editingId !== "";

        const payload = {
            fecha_inicio: incapacidadForm.fecha_inicio.value.trim(),
            fecha_fin: incapacidadForm.fecha_fin.value.trim(),
            tipo: incapacidadForm.tipo.value.trim(),
            entidad_medica: incapacidadForm.entidad_medica.value.trim(),
            diagnostico_general: incapacidadForm.diagnostico_general.value.trim(),
            observaciones: incapacidadForm.observaciones.value.trim(),
        };

        if (isEditing) {
            payload.estado = incapacidadForm.estado.value.trim();
        } else {
            payload.empleado_id = Number(incapacidadForm.empleado_id.value) || 0;
        }

        const clientErrors = validateIncapacidadPayload(payload, isEditing);
        if (Object.keys(clientErrors).length > 0) {
            showAlert(
                incapacidadModalAlert,
                "Revise los campos de la incapacidad antes de guardar.",
                "error",
                clientErrors
            );
            return;
        }

        saveIncapacidadButton.disabled = true;

        try {
            const responseData = isEditing
                ? await HttpClient.put(`${API_URLS.INCAPACIDADES}/incapacidades/${editingId}`, payload)
                : await HttpClient.post(`${API_URLS.INCAPACIDADES}/incapacidades`, payload);

            closeModal(incapacidadModal);
            showAlert(
                globalAlertContainer,
                responseData.mensaje || "Operación realizada correctamente.",
                "success"
            );
            await loadIncapacidades();
        } catch (saveError) {
            showAlert(
                incapacidadModalAlert,
                saveError.message || "No fue posible guardar la incapacidad médica.",
                "error",
                saveError.fieldErrors
            );
        } finally {
            saveIncapacidadButton.disabled = false;
        }
    }

    async function handleConfirmFinalize() {
        if (!pendingFinalizeIncapacidad) {
            return;
        }

        clearAlert(globalAlertContainer);
        const incapacidadId = pendingFinalizeIncapacidad.id;
        confirmFinalizeModalButton.disabled = true;

        try {
            const responseData = await HttpClient.patch(
                `${API_URLS.INCAPACIDADES}/incapacidades/${incapacidadId}/finalizar`,
                {}
            );
            closeModal(finalizeModal);
            pendingFinalizeIncapacidad = null;
            showAlert(
                globalAlertContainer,
                responseData.mensaje || "Incapacidad finalizada correctamente.",
                "success"
            );
            await loadIncapacidades();
        } catch (finalizeError) {
            closeModal(finalizeModal);
            showAlert(
                globalAlertContainer,
                finalizeError.message || "No fue posible finalizar la incapacidad.",
                "error",
                finalizeError.fieldErrors
            );
        } finally {
            confirmFinalizeModalButton.disabled = false;
        }
    }

    function bindEvents() {
        filterForm.addEventListener("submit", (submitEvent) => {
            submitEvent.preventDefault();
            clearAlert(globalAlertContainer);
            loadIncapacidades();
        });

        clearFiltersButton.addEventListener("click", () => {
            filterForm.reset();
            clearAlert(globalAlertContainer);
            loadIncapacidades();
        });

        refreshButton.addEventListener("click", () => {
            clearAlert(globalAlertContainer);
            loadIncapacidades();
        });

        openCreateButton.addEventListener("click", openCreateModal);
        closeIncapacidadModalButton.addEventListener("click", () => closeModal(incapacidadModal));
        cancelIncapacidadModalButton.addEventListener("click", () => closeModal(incapacidadModal));
        incapacidadForm.fecha_inicio.addEventListener("change", updateDaysPreview);
        incapacidadForm.fecha_fin.addEventListener("change", updateDaysPreview);
        incapacidadForm.addEventListener("submit", handleIncapacidadSubmit);

        closeFinalizeModalButton.addEventListener("click", () => {
            pendingFinalizeIncapacidad = null;
            closeModal(finalizeModal);
        });
        cancelFinalizeModalButton.addEventListener("click", () => {
            pendingFinalizeIncapacidad = null;
            closeModal(finalizeModal);
        });
        confirmFinalizeModalButton.addEventListener("click", handleConfirmFinalize);
    }

    bindEvents();

    return {
        load: loadIncapacidades,
    };
}
