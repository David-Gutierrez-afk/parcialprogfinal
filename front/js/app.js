import { API_URLS, ROL_LABELS } from "./config.js";
import { createEmpleadosModule } from "./empleados.js";
import { HttpClient } from "./httpClient.js";
import { SessionManager } from "./session.js";
import { clearAlert, showAlert } from "./uiFeedback.js";

const sessionUserName = document.getElementById("sessionUserName");
const sessionUserRole = document.getElementById("sessionUserRole");
const logoutButton = document.getElementById("logoutButton");
const globalAlert = document.getElementById("globalAlert");
const navigationTabs = Array.from(document.querySelectorAll("[data-module-tab]"));
const moduleSections = Array.from(document.querySelectorAll("[data-module-section]"));

function renderSessionHeader(usuario) {
    sessionUserName.textContent = usuario.nombre || usuario.usuario || "Usuario";
    sessionUserRole.textContent = ROL_LABELS[usuario.rol] || usuario.rol || "";
}

function activateModuleTab(targetModuleName) {
    clearAlert(globalAlert);

    navigationTabs.forEach((tabElement) => {
        const isCurrentTab = tabElement.dataset.moduleTab === targetModuleName;
        tabElement.classList.toggle("is-active", isCurrentTab);
    });

    moduleSections.forEach((sectionElement) => {
        const isCurrentSection = sectionElement.dataset.moduleSection === targetModuleName;
        sectionElement.classList.toggle("is-hidden", !isCurrentSection);
    });
}

async function handleLogout() {
    logoutButton.disabled = true;
    logoutButton.textContent = "Cerrando...";

    try {
        await HttpClient.post(
            `${API_URLS.AUTH}/auth/logout`,
            {},
            { authenticated: true, redirectOnUnauthorized: false }
        );
    } catch (logoutError) {
        // Aunque falle la red, se invalida la sesión local para proteger el acceso
    } finally {
        SessionManager.clearSession();
        window.location.href = "index.html";
    }
}

async function initializeApplication() {
    if (!SessionManager.requireAuthOrRedirect()) {
        return;
    }

    const storedUser = SessionManager.getUsuario();
    if (storedUser) {
        renderSessionHeader(storedUser);
    }

    try {
        const validationResponse = await HttpClient.get(`${API_URLS.AUTH}/auth/validate`);
        if (validationResponse.usuario) {
            SessionManager.saveSession(SessionManager.getToken(), validationResponse.usuario);
            renderSessionHeader(validationResponse.usuario);
        }
    } catch (sessionError) {
        showAlert(
            globalAlert,
            sessionError.message || "No fue posible verificar la sesión activa.",
            "error"
        );
        return;
    }

    const empleadosModule = createEmpleadosModule(globalAlert);

    navigationTabs.forEach((tabElement) => {
        tabElement.addEventListener("click", () => {
            const selectedModule = tabElement.dataset.moduleTab;
            activateModuleTab(selectedModule);

            if (selectedModule === "empleados") {
                empleadosModule.load();
            }
        });
    });

    logoutButton.addEventListener("click", handleLogout);

    await empleadosModule.load();
}

initializeApplication();
