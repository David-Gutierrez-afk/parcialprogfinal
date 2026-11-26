import { ESTADO_LABELS } from "./config.js";

export function clearAlert(container) {
    if (!container) {
        return;
    }

    container.textContent = "";
    container.className = "alert is-hidden";
}

export function showAlert(container, message, type = "info", fieldErrors = null) {
    if (!container) {
        return;
    }

    container.textContent = "";
    container.className = `alert alert-${type}`;

    const messageParagraph = document.createElement("p");
    messageParagraph.textContent = message;
    container.appendChild(messageParagraph);

    if (fieldErrors && typeof fieldErrors === "object") {
        const errorEntries = Object.values(fieldErrors).filter(Boolean);
        if (errorEntries.length > 0) {
            const listElement = document.createElement("ul");
            listElement.className = "alert-error-list";

            errorEntries.forEach((detailMessage) => {
                const listItem = document.createElement("li");
                listItem.textContent = String(detailMessage);
                listElement.appendChild(listItem);
            });

            container.appendChild(listElement);
        }
    }
}

export function createStatusBadge(statusValue) {
    const normalizedStatus = String(statusValue || "").toLowerCase();
    const badgeElement = document.createElement("span");
    badgeElement.className = `badge badge-${normalizedStatus}`;
    badgeElement.textContent = ESTADO_LABELS[normalizedStatus] || statusValue || "-";
    return badgeElement;
}

export function openModal(modalElement) {
    if (!modalElement) {
        return;
    }
    modalElement.classList.add("is-open");
    modalElement.setAttribute("aria-hidden", "false");
}

export function closeModal(modalElement) {
    if (!modalElement) {
        return;
    }
    modalElement.classList.remove("is-open");
    modalElement.setAttribute("aria-hidden", "true");
}

export function getTodayIsoDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}
