import { API_URLS } from "./config.js";
import { HttpClient } from "./httpClient.js";
import {
    clearAlert,
    closeModal,
    createStatusBadge,
    getTodayIsoDate,
    openModal,
    showAlert,
} from "./uiFeedback.js";

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const DOCUMENT_PATTERN = /^[A-Za-z0-9-]{5,30}$/;
const PHONE_PATTERN = /^[0-9+\-\s()]{7,30}$/;

export function createEmpleadosModule(globalAlertContainer) {
    const filterForm = document.forms.empleadosFilterForm;
    const clearFiltersButton = document.getElementById("clearEmpleadosFiltersButton");
    const refreshButton = document.getElementById("refreshEmpleadosButton");
    const openCreateButton = document.getElementById("openCreateEmpleadoButton");
    const tableBody = document.getElementById("empleadosTableBody");

    const kpiTotal = document.getElementById("kpiEmpleadosTotal");
    const kpiActivos = document.getElementById("kpiEmpleadosActivos");
    const kpiInactivos = document.getElementById("kpiEmpleadosInactivos");

    const empleadoModal = document.getElementById("empleadoModal");
    const empleadoModalTitle = document.getElementById("empleadoModalTitle");
    const empleadoModalAlert = document.getElementById("empleadoModalAlert");
    const empleadoForm = document.forms.empleadoForm;
    const empleadoEstadoGroup = document.getElementById("empleadoEstadoGroup");
    const saveEmpleadoButton = document.getElementById("saveEmpleadoButton");
    const closeEmpleadoModalButton = document.getElementById("closeEmpleadoModalButton");
    const cancelEmpleadoModalButton = document.getElementById("cancelEmpleadoModalButton");

    const statusModal = document.getElementById("estadoEmpleadoModal");
    const statusConfirmMessage = document.getElementById("estadoEmpleadoConfirmMessage");
    const closeStatusModalButton = document.getElementById("closeEstadoEmpleadoModalButton");
    const cancelStatusModalButton = document.getElementById("cancelEstadoEmpleadoButton");
    const confirmStatusModalButton = document.getElementById("confirmEstadoEmpleadoButton");

    let currentEmpleados = [];
    let pendingStatusChange = null;

    function buildQueryString() {
        const params = new URLSearchParams();
        const documento = filterForm.documento.value.trim();
        const area = filterForm.area.value.trim();
        const estado = filterForm.estado.value.trim();

        if (documento) {
            params.set("documento", documento);
        }
        if (area) {
            params.set("area", area);
        }
        if (estado) {
            params.set("estado", estado);
        }

        const queryText = params.toString();
        return queryText ? `?${queryText}` : "";
    }

    function updateKpis(empleadosList) {
        const totalCount = empleadosList.length;
        const activeCount = empleadosList.filter((empleado) => empleado.estado === "activo").length;
        const inactiveCount = totalCount - activeCount;

        kpiTotal.textContent = String(totalCount);
        kpiActivos.textContent = String(activeCount);
        kpiInactivos.textContent = String(inactiveCount);
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

    function renderEmpleadosTable(empleadosList) {
        updateKpis(empleadosList);

        if (empleadosList.length === 0) {
            renderEmptyRow("No se encontraron empleados con los criterios seleccionados.");
            return;
        }

        tableBody.textContent = "";

        empleadosList.forEach((empleado) => {
            const tableRow = document.createElement("tr");

            tableRow.appendChild(createTextCell(String(empleado.id)));
            tableRow.appendChild(createTextCell(`${empleado.nombres} ${empleado.apellidos}`));
            tableRow.appendChild(createTextCell(empleado.documento));
            tableRow.appendChild(createTextCell(empleado.correo));
            tableRow.appendChild(createTextCell(empleado.telefono));
            tableRow.appendChild(createTextCell(empleado.cargo));
            tableRow.appendChild(createTextCell(empleado.area));
            tableRow.appendChild(createTextCell(empleado.fecha_ingreso));

            const statusCell = document.createElement("td");
            statusCell.appendChild(createStatusBadge(empleado.estado));
            tableRow.appendChild(statusCell);

            const actionsCell = document.createElement("td");
            const actionsGroup = document.createElement("div");
            actionsGroup.className = "table-actions-group";

            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.className = "button button-secondary button-small";
            editButton.textContent = "Editar";
            editButton.addEventListener("click", () => openEditModal(empleado));
            actionsGroup.appendChild(editButton);

            const targetStatus = empleado.estado === "activo" ? "inactivo" : "activo";
            const statusButton = document.createElement("button");
            statusButton.type = "button";
            statusButton.className =
                targetStatus === "inactivo"
                    ? "button button-danger button-small"
                    : "button button-success button-small";
            statusButton.textContent = targetStatus === "inactivo" ? "Inactivar" : "Activar";
            statusButton.addEventListener("click", () => openStatusConfirmModal(empleado, targetStatus));
            actionsGroup.appendChild(statusButton);

            actionsCell.appendChild(actionsGroup);
            tableRow.appendChild(actionsCell);

            tableBody.appendChild(tableRow);
        });
    }

    async function loadEmpleados() {
        renderEmptyRow("Cargando empleados...");
        try {
            const queryString = buildQueryString();
            const responseData = await HttpClient.get(`${API_URLS.EMPLEADOS}/empleados${queryString}`);
            currentEmpleados = Array.isArray(responseData.data) ? responseData.data : [];
            renderEmpleadosTable(currentEmpleados);
        } catch (loadError) {
            renderEmptyRow("Error al consultar los empleados.");
            showAlert(
                globalAlertContainer,
                loadError.message || "No fue posible consultar el listado de empleados.",
                "error",
                loadError.fieldErrors
            );
        }
    }

    function openCreateModal() {
        clearAlert(empleadoModalAlert);
        empleadoForm.reset();
        empleadoForm.id.value = "";
        empleadoModalTitle.textContent = "Registrar empleado";
        saveEmpleadoButton.textContent = "Registrar empleado";
        empleadoEstadoGroup.classList.add("is-hidden");
        empleadoForm.fecha_ingreso.max = getTodayIsoDate();
        openModal(empleadoModal);
    }

    function openEditModal(empleado) {
        clearAlert(empleadoModalAlert);
        empleadoForm.reset();
        empleadoForm.id.value = String(empleado.id);
        empleadoForm.nombres.value = empleado.nombres || "";
        empleadoForm.apellidos.value = empleado.apellidos || "";
        empleadoForm.documento.value = empleado.documento || "";
        empleadoForm.correo.value = empleado.correo || "";
        empleadoForm.telefono.value = empleado.telefono || "";
        empleadoForm.cargo.value = empleado.cargo || "";
        empleadoForm.area.value = empleado.area || "";
        empleadoForm.fecha_ingreso.value = empleado.fecha_ingreso || "";
        empleadoForm.fecha_ingreso.max = getTodayIsoDate();
        empleadoForm.estado.value = empleado.estado || "activo";

        empleadoModalTitle.textContent = `Editar empleado #${empleado.id}`;
        saveEmpleadoButton.textContent = "Guardar cambios";
        empleadoEstadoGroup.classList.remove("is-hidden");
        openModal(empleadoModal);
    }

    function openStatusConfirmModal(empleado, targetStatus) {
        pendingStatusChange = { empleado, targetStatus };
        const actionVerb = targetStatus === "inactivo" ? "inactivar" : "activar";
        statusConfirmMessage.textContent = `¿Está seguro de ${actionVerb} al empleado ${empleado.nombres} ${empleado.apellidos} (Doc. ${empleado.documento})?`;
        openModal(statusModal);
    }

    function validateEmpleadoPayload(payload) {
        const errors = {};

        if (!payload.nombres) {
            errors.nombres = "Los nombres son obligatorios.";
        }
        if (!payload.apellidos) {
            errors.apellidos = "Los apellidos son obligatorios.";
        }
        if (!payload.documento) {
            errors.documento = "El documento de identificación es obligatorio.";
        } else if (!DOCUMENT_PATTERN.test(payload.documento)) {
            errors.documento = "El documento debe tener entre 5 y 30 caracteres alfanuméricos sin espacios.";
        }

        if (!payload.correo) {
            errors.correo = "El correo electrónico es obligatorio.";
        } else if (!EMAIL_PATTERN.test(payload.correo)) {
            errors.correo = "Ingrese un correo electrónico válido.";
        }

        if (!payload.telefono) {
            errors.telefono = "El teléfono es obligatorio.";
        } else if (!PHONE_PATTERN.test(payload.telefono)) {
            errors.telefono = "El teléfono debe tener entre 7 y 30 caracteres numéricos válidos.";
        }

        if (!payload.cargo) {
            errors.cargo = "El cargo es obligatorio.";
        }
        if (!payload.area) {
            errors.area = "El área es obligatoria.";
        }

        if (!payload.fecha_ingreso) {
            errors.fecha_ingreso = "La fecha de ingreso es obligatoria.";
        } else if (payload.fecha_ingreso > getTodayIsoDate()) {
            errors.fecha_ingreso = "La fecha de ingreso no puede ser una fecha futura.";
        }

        return errors;
    }

    async function handleEmpleadoSubmit(submitEvent) {
        submitEvent.preventDefault();
        clearAlert(empleadoModalAlert);
        clearAlert(globalAlertContainer);

        const editingId = empleadoForm.id.value.trim();
        const isEditing = editingId !== "";

        const payload = {
            nombres: empleadoForm.nombres.value.trim(),
            apellidos: empleadoForm.apellidos.value.trim(),
            documento: empleadoForm.documento.value.trim(),
            correo: empleadoForm.correo.value.trim().toLowerCase(),
            telefono: empleadoForm.telefono.value.trim(),
            cargo: empleadoForm.cargo.value.trim(),
            area: empleadoForm.area.value.trim(),
            fecha_ingreso: empleadoForm.fecha_ingreso.value.trim(),
        };

        if (isEditing) {
            payload.estado = empleadoForm.estado.value;
        }

        const clientErrors = validateEmpleadoPayload(payload);
        if (Object.keys(clientErrors).length > 0) {
            showAlert(
                empleadoModalAlert,
                "Revise los campos del formulario antes de guardar.",
                "error",
                clientErrors
            );
            return;
        }

        saveEmpleadoButton.disabled = true;

        try {
            const responseData = isEditing
                ? await HttpClient.put(`${API_URLS.EMPLEADOS}/empleados/${editingId}`, payload)
                : await HttpClient.post(`${API_URLS.EMPLEADOS}/empleados`, payload);

            closeModal(empleadoModal);
            showAlert(
                globalAlertContainer,
                responseData.mensaje || "Operación realizada correctamente.",
                "success"
            );
            await loadEmpleados();
        } catch (saveError) {
            showAlert(
                empleadoModalAlert,
                saveError.message || "No fue posible guardar el empleado.",
                "error",
                saveError.fieldErrors
            );
        } finally {
            saveEmpleadoButton.disabled = false;
        }
    }

    async function handleConfirmStatusChange() {
        if (!pendingStatusChange) {
            return;
        }

        clearAlert(globalAlertContainer);
        const { empleado, targetStatus } = pendingStatusChange;
        confirmStatusModalButton.disabled = true;

        try {
            const responseData = await HttpClient.patch(
                `${API_URLS.EMPLEADOS}/empleados/${empleado.id}/estado`,
                { estado: targetStatus }
            );
            closeModal(statusModal);
            pendingStatusChange = null;
            showAlert(
                globalAlertContainer,
                responseData.mensaje || "Estado actualizado correctamente.",
                "success"
            );
            await loadEmpleados();
        } catch (statusError) {
            closeModal(statusModal);
            showAlert(
                globalAlertContainer,
                statusError.message || "No fue posible cambiar el estado del empleado.",
                "error",
                statusError.fieldErrors
            );
        } finally {
            confirmStatusModalButton.disabled = false;
        }
    }

    function bindEvents() {
        filterForm.addEventListener("submit", (submitEvent) => {
            submitEvent.preventDefault();
            clearAlert(globalAlertContainer);
            loadEmpleados();
        });

        clearFiltersButton.addEventListener("click", () => {
            filterForm.reset();
            clearAlert(globalAlertContainer);
            loadEmpleados();
        });

        refreshButton.addEventListener("click", () => {
            clearAlert(globalAlertContainer);
            loadEmpleados();
        });

        openCreateButton.addEventListener("click", openCreateModal);
        closeEmpleadoModalButton.addEventListener("click", () => closeModal(empleadoModal));
        cancelEmpleadoModalButton.addEventListener("click", () => closeModal(empleadoModal));
        empleadoForm.addEventListener("submit", handleEmpleadoSubmit);

        closeStatusModalButton.addEventListener("click", () => {
            pendingStatusChange = null;
            closeModal(statusModal);
        });
        cancelStatusModalButton.addEventListener("click", () => {
            pendingStatusChange = null;
            closeModal(statusModal);
        });
        confirmStatusModalButton.addEventListener("click", handleConfirmStatusChange);
    }

    bindEvents();

    return {
        load: loadEmpleados,
        getCachedEmpleados: () => currentEmpleados,
    };
}
