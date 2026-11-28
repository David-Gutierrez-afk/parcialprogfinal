import { API_URLS, ESTADO_LABELS, TIPO_INCAPACIDAD_LABELS } from "./config.js";
import { HttpClient } from "./httpClient.js";
import { SessionManager } from "./session.js";
import {
    clearAlert,
    createStatusBadge,
    getTodayIsoDate,
    showAlert,
} from "./uiFeedback.js";

export function createSeguimientoModule(globalAlertContainer) {
    const refreshButton = document.getElementById("refreshSeguimientoButton");
    const seguimientoForm = document.forms.seguimientoForm;
    const seguimientoFormAlert = document.getElementById("seguimientoFormAlert");
    const seguimientoIncapacidadSelect = document.getElementById("seguimientoIncapacidadId");
    const saveSeguimientoButton = document.getElementById("saveSeguimientoButton");

    const historialIncapacidadSelect = document.getElementById("historialIncapacidadSelect");
    const historialResumenContainer = document.getElementById("historialResumenIncapacidad");
    const timelineList = document.getElementById("seguimientoTimelineList");

    const filterForm = document.forms.seguimientosFilterForm;
    const filterIncapacidadSelect = document.getElementById("filtroSeguimientoIncapacidad");
    const clearFiltersButton = document.getElementById("clearSeguimientosFiltersButton");
    const tableBody = document.getElementById("seguimientosTableBody");

    let empleadosById = new Map();
    let incapacidadesById = new Map();
    let selectedHistoryIncapacidadId = "";

    function formatEmpleadoName(empleadoId) {
        const empleado = empleadosById.get(Number(empleadoId));
        if (!empleado) {
            return `Empleado #${empleadoId}`;
        }
        return `${empleado.nombres} ${empleado.apellidos}`;
    }

    function formatIncapacidadOptionLabel(incapacidad) {
        const empleadoName = formatEmpleadoName(incapacidad.empleado_id);
        const estadoLabel = ESTADO_LABELS[incapacidad.estado] || incapacidad.estado;
        return `#${incapacidad.id} — ${empleadoName} (${incapacidad.fecha_inicio} a ${incapacidad.fecha_fin}) [${estadoLabel}]`;
    }

    function populateIncapacidadSelectors(incapacidadesList) {
        const previousFormValue = seguimientoIncapacidadSelect.value;
        const previousHistoryValue = selectedHistoryIncapacidadId || historialIncapacidadSelect.value;
        const previousFilterValue = filterIncapacidadSelect.value;

        const targets = [
            {
                select: seguimientoIncapacidadSelect,
                placeholder: "Seleccione una incapacidad...",
                previousValue: previousFormValue,
            },
            {
                select: historialIncapacidadSelect,
                placeholder: "Seleccione una incapacidad para ver su historial...",
                previousValue: previousHistoryValue,
            },
            {
                select: filterIncapacidadSelect,
                placeholder: "Todas las incapacidades",
                previousValue: previousFilterValue,
            },
        ];

        targets.forEach(({ select, placeholder, previousValue }) => {
            select.textContent = "";

            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.textContent = placeholder;
            select.appendChild(defaultOption);

            incapacidadesList.forEach((incapacidad) => {
                const optionElement = document.createElement("option");
                optionElement.value = String(incapacidad.id);
                optionElement.textContent = formatIncapacidadOptionLabel(incapacidad);
                select.appendChild(optionElement);
            });

            if (previousValue && incapacidadesById.has(Number(previousValue))) {
                select.value = String(previousValue);
            }
        });
    }

    async function loadCatalogs() {
        try {
            const [empleadosResponse, incapacidadesResponse] = await Promise.all([
                HttpClient.get(`${API_URLS.EMPLEADOS}/empleados`),
                HttpClient.get(`${API_URLS.INCAPACIDADES}/incapacidades`),
            ]);

            const empleadosList = Array.isArray(empleadosResponse.data) ? empleadosResponse.data : [];
            const incapacidadesList = Array.isArray(incapacidadesResponse.data)
                ? incapacidadesResponse.data
                : [];

            empleadosById = new Map(
                empleadosList.map((empleado) => [Number(empleado.id), empleado])
            );
            incapacidadesById = new Map(
                incapacidadesList.map((incapacidad) => [Number(incapacidad.id), incapacidad])
            );

            populateIncapacidadSelectors(incapacidadesList);
        } catch (catalogError) {
            showAlert(
                globalAlertContainer,
                catalogError.message || "No fue posible cargar el catálogo de incapacidades.",
                "error"
            );
        }
    }

    function setDefaultFormValues() {
        if (!seguimientoForm.fecha.value) {
            seguimientoForm.fecha.value = getTodayIsoDate();
        }

        const currentUser = SessionManager.getUsuario();
        if (currentUser && currentUser.usuario && !seguimientoForm.usuario_responsable.value.trim()) {
            seguimientoForm.usuario_responsable.value = currentUser.usuario;
        }
    }

    function createSummaryItem(labelText, valueContent) {
        const itemContainer = document.createElement("div");
        itemContainer.className = "detail-summary-item";

        const labelSpan = document.createElement("span");
        labelSpan.className = "detail-summary-label";
        labelSpan.textContent = labelText;
        itemContainer.appendChild(labelSpan);

        if (valueContent instanceof HTMLElement) {
            itemContainer.appendChild(valueContent);
        } else {
            const valueSpan = document.createElement("span");
            valueSpan.className = "detail-summary-value";
            valueSpan.textContent = String(valueContent ?? "-");
            itemContainer.appendChild(valueSpan);
        }

        return itemContainer;
    }

    function renderEmptyTimeline(message) {
        timelineList.textContent = "";
        const emptyItem = document.createElement("li");
        emptyItem.className = "timeline-empty";
        emptyItem.textContent = message;
        timelineList.appendChild(emptyItem);
    }

    function renderHistory(historyResponse) {
        const incapacidad = historyResponse.incapacidad;
        const historial = Array.isArray(historyResponse.historial) ? historyResponse.historial : [];

        historialResumenContainer.textContent = "";
        if (incapacidad) {
            historialResumenContainer.classList.remove("is-hidden");
            historialResumenContainer.appendChild(
                createSummaryItem("Incapacidad", `#${incapacidad.id}`)
            );
            historialResumenContainer.appendChild(
                createSummaryItem("Empleado", formatEmpleadoName(incapacidad.empleado_id))
            );
            historialResumenContainer.appendChild(
                createSummaryItem(
                    "Periodo",
                    `${incapacidad.fecha_inicio} a ${incapacidad.fecha_fin} (${incapacidad.dias_incapacidad} d)`
                )
            );
            historialResumenContainer.appendChild(
                createSummaryItem(
                    "Tipo",
                    TIPO_INCAPACIDAD_LABELS[incapacidad.tipo] || incapacidad.tipo
                )
            );
            historialResumenContainer.appendChild(
                createSummaryItem("Estado actual", createStatusBadge(incapacidad.estado))
            );
            historialResumenContainer.appendChild(
                createSummaryItem("Movimientos", String(historial.length))
            );
        } else {
            historialResumenContainer.classList.add("is-hidden");
        }

        if (historial.length === 0) {
            renderEmptyTimeline("Esta incapacidad aún no tiene movimientos de seguimiento registrados.");
            return;
        }

        timelineList.textContent = "";

        historial.forEach((movimiento) => {
            const itemElement = document.createElement("li");
            itemElement.className = "timeline-item";

            const metaHeader = document.createElement("div");
            metaHeader.className = "timeline-meta";

            const dateAndUser = document.createElement("span");
            dateAndUser.textContent = `${movimiento.fecha} — Responsable: ${movimiento.usuario_responsable}`;
            metaHeader.appendChild(dateAndUser);
            metaHeader.appendChild(createStatusBadge(movimiento.estado));

            const commentParagraph = document.createElement("p");
            commentParagraph.className = "timeline-comment";
            commentParagraph.textContent = movimiento.comentario;

            itemElement.appendChild(metaHeader);
            itemElement.appendChild(commentParagraph);
            timelineList.appendChild(itemElement);
        });
    }

    async function loadHistoryByIncapacidad(incapacidadId) {
        selectedHistoryIncapacidadId = incapacidadId ? String(incapacidadId) : "";

        if (!selectedHistoryIncapacidadId) {
            historialResumenContainer.classList.add("is-hidden");
            renderEmptyTimeline(
                "Seleccione una incapacidad para visualizar su historial de cambios y observaciones."
            );
            return;
        }

        renderEmptyTimeline("Cargando historial de seguimiento...");

        try {
            const historyResponse = await HttpClient.get(
                `${API_URLS.SEGUIMIENTO}/seguimientos/incapacidad/${selectedHistoryIncapacidadId}`
            );
            renderHistory(historyResponse);
        } catch (historyError) {
            historialResumenContainer.classList.add("is-hidden");
            renderEmptyTimeline("No fue posible cargar el historial de la incapacidad seleccionada.");
            showAlert(
                globalAlertContainer,
                historyError.message || "Error al consultar el historial de seguimiento.",
                "error",
                historyError.fieldErrors
            );
        }
    }

    function buildFilterQueryString() {
        const params = new URLSearchParams();
        const incapacidadId = filterForm.incapacidad_id.value.trim();
        const estado = filterForm.estado.value.trim();
        const usuarioResponsable = filterForm.usuario_responsable.value.trim();
        const fecha = filterForm.fecha.value.trim();

        if (incapacidadId) {
            params.set("incapacidad_id", incapacidadId);
        }
        if (estado) {
            params.set("estado", estado);
        }
        if (usuarioResponsable) {
            params.set("usuario_responsable", usuarioResponsable);
        }
        if (fecha) {
            params.set("fecha", fecha);
        }

        const queryText = params.toString();
        return queryText ? `?${queryText}` : "";
    }

    function renderEmptyTableRow(message) {
        tableBody.textContent = "";
        const emptyRow = document.createElement("tr");
        const emptyCell = document.createElement("td");
        emptyCell.colSpan = 6;
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

    function renderSeguimientosTable(seguimientosList) {
        if (seguimientosList.length === 0) {
            renderEmptyTableRow("No se encontraron seguimientos con los filtros aplicados.");
            return;
        }

        tableBody.textContent = "";

        seguimientosList.forEach((seguimiento) => {
            const tableRow = document.createElement("tr");
            const incapacidadRef = incapacidadesById.get(Number(seguimiento.incapacidad_id));
            const incapacidadLabel = incapacidadRef
                ? `#${incapacidadRef.id} — ${formatEmpleadoName(incapacidadRef.empleado_id)}`
                : `Incapacidad #${seguimiento.incapacidad_id}`;

            tableRow.appendChild(createTextCell(`#${seguimiento.id}`));
            tableRow.appendChild(createTextCell(incapacidadLabel));
            tableRow.appendChild(createTextCell(seguimiento.fecha));

            const statusCell = document.createElement("td");
            statusCell.appendChild(createStatusBadge(seguimiento.estado));
            tableRow.appendChild(statusCell);

            tableRow.appendChild(createTextCell(seguimiento.usuario_responsable));
            tableRow.appendChild(createTextCell(seguimiento.comentario));

            tableBody.appendChild(tableRow);
        });
    }

    async function loadSeguimientosList() {
        renderEmptyTableRow("Cargando seguimientos...");

        try {
            const queryString = buildFilterQueryString();
            const responseData = await HttpClient.get(
                `${API_URLS.SEGUIMIENTO}/seguimientos${queryString}`
            );
            const seguimientosList = Array.isArray(responseData.data) ? responseData.data : [];
            renderSeguimientosTable(seguimientosList);
        } catch (listError) {
            renderEmptyTableRow("Error al consultar los seguimientos.");
            showAlert(
                globalAlertContainer,
                listError.message || "No fue posible consultar el registro general de seguimientos.",
                "error",
                listError.fieldErrors
            );
        }
    }

    function validateSeguimientoPayload(payload) {
        const errors = {};

        if (!payload.incapacidad_id) {
            errors.incapacidad_id = "Debe seleccionar una incapacidad asociada.";
        }
        if (!payload.fecha) {
            errors.fecha = "La fecha del seguimiento es obligatoria.";
        }
        if (!payload.estado) {
            errors.estado = "Debe seleccionar el estado actualizado.";
        }
        if (!payload.usuario_responsable) {
            errors.usuario_responsable = "El usuario responsable es obligatorio.";
        }
        if (!payload.comentario || payload.comentario.length < 3) {
            errors.comentario = "El comentario es obligatorio y debe tener al menos 3 caracteres.";
        }

        return errors;
    }

    async function handleSeguimientoSubmit(submitEvent) {
        submitEvent.preventDefault();
        clearAlert(seguimientoFormAlert);
        clearAlert(globalAlertContainer);

        const accion = seguimientoForm.accion.value;
        const incapacidadId = Number(seguimientoForm.incapacidad_id.value) || 0;
        const payload = {
            incapacidad_id: incapacidadId,
            fecha: seguimientoForm.fecha.value.trim(),
            estado: seguimientoForm.estado.value.trim(),
            usuario_responsable: seguimientoForm.usuario_responsable.value.trim(),
            comentario: seguimientoForm.comentario.value.trim(),
        };

        const clientErrors = validateSeguimientoPayload(payload);
        if (Object.keys(clientErrors).length > 0) {
            showAlert(
                seguimientoFormAlert,
                "Complete correctamente todos los campos del seguimiento.",
                "error",
                clientErrors
            );
            return;
        }

        saveSeguimientoButton.disabled = true;

        try {
            const responseData = accion === "actualizar_estado"
                ? await HttpClient.patch(
                    `${API_URLS.SEGUIMIENTO}/seguimientos/incapacidad/${incapacidadId}/estado`,
                    {
                        estado: payload.estado,
                        comentario: payload.comentario,
                        fecha: payload.fecha,
                        usuario_responsable: payload.usuario_responsable,
                    }
                )
                : await HttpClient.post(`${API_URLS.SEGUIMIENTO}/seguimientos`, payload);

            showAlert(
                seguimientoFormAlert,
                responseData.mensaje || "Seguimiento guardado correctamente.",
                "success"
            );

            seguimientoForm.comentario.value = "";
            selectedHistoryIncapacidadId = String(incapacidadId);

            await loadCatalogs();
            seguimientoIncapacidadSelect.value = String(incapacidadId);
            historialIncapacidadSelect.value = String(incapacidadId);

            await Promise.all([
                loadHistoryByIncapacidad(incapacidadId),
                loadSeguimientosList(),
            ]);
        } catch (saveError) {
            showAlert(
                seguimientoFormAlert,
                saveError.message || "No fue posible guardar el seguimiento.",
                "error",
                saveError.fieldErrors
            );
        } finally {
            saveSeguimientoButton.disabled = false;
        }
    }

    async function loadModule(preselectedIncapacidadId = null) {
        setDefaultFormValues();
        await loadCatalogs();

        if (preselectedIncapacidadId) {
            selectedHistoryIncapacidadId = String(preselectedIncapacidadId);
            seguimientoIncapacidadSelect.value = selectedHistoryIncapacidadId;
            historialIncapacidadSelect.value = selectedHistoryIncapacidadId;
        } else if (!selectedHistoryIncapacidadId && incapacidadesById.size > 0) {
            const firstIncapacidadId = String(incapacidadesById.keys().next().value);
            selectedHistoryIncapacidadId = firstIncapacidadId;
            historialIncapacidadSelect.value = firstIncapacidadId;
        }

        await Promise.all([
            loadHistoryByIncapacidad(selectedHistoryIncapacidadId),
            loadSeguimientosList(),
        ]);
    }

    function bindEvents() {
        refreshButton.addEventListener("click", () => {
            clearAlert(globalAlertContainer);
            clearAlert(seguimientoFormAlert);
            loadModule(selectedHistoryIncapacidadId || null);
        });

        seguimientoForm.addEventListener("submit", handleSeguimientoSubmit);

        seguimientoIncapacidadSelect.addEventListener("change", () => {
            const chosenId = seguimientoIncapacidadSelect.value;
            if (chosenId) {
                historialIncapacidadSelect.value = chosenId;
                loadHistoryByIncapacidad(chosenId);
            }
        });

        historialIncapacidadSelect.addEventListener("change", () => {
            loadHistoryByIncapacidad(historialIncapacidadSelect.value);
        });

        filterForm.addEventListener("submit", (submitEvent) => {
            submitEvent.preventDefault();
            clearAlert(globalAlertContainer);
            loadSeguimientosList();
        });

        clearFiltersButton.addEventListener("click", () => {
            filterForm.reset();
            clearAlert(globalAlertContainer);
            loadSeguimientosList();
        });
    }

    bindEvents();

    return {
        load: loadModule,
        openForIncapacidad: (incapacidadId) => loadModule(incapacidadId),
    };
}
