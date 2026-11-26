import { API_URLS } from "./config.js";
import { HttpClient } from "./httpClient.js";
import { SessionManager } from "./session.js";
import { clearAlert, showAlert } from "./uiFeedback.js";

const loginForm = document.forms.loginForm;
const loginAlert = document.getElementById("loginAlert");
const loginSubmitButton = document.getElementById("loginSubmitButton");

async function redirectIfSessionIsValid() {
    if (!SessionManager.isAuthenticated()) {
        return;
    }

    try {
        await HttpClient.get(`${API_URLS.AUTH}/auth/validate`, {
            authenticated: true,
            redirectOnUnauthorized: false,
        });
        window.location.href = "home.html";
    } catch (validationError) {
        SessionManager.clearSession();
    }
}

function validateLoginInputs(identificador, contrasena) {
    const fieldErrors = {};

    if (!identificador) {
        fieldErrors.identificador = "Debe ingresar su usuario o correo electrónico.";
    }

    if (!contrasena) {
        fieldErrors.contrasena = "Debe ingresar su contraseña.";
    }

    return fieldErrors;
}

async function handleLoginSubmit(submitEvent) {
    submitEvent.preventDefault();
    clearAlert(loginAlert);

    const identificador = loginForm.identificador.value.trim();
    const contrasena = loginForm.contrasena.value;

    const clientErrors = validateLoginInputs(identificador, contrasena);
    if (Object.keys(clientErrors).length > 0) {
        showAlert(loginAlert, "Complete los campos obligatorios para continuar.", "error", clientErrors);
        return;
    }

    loginSubmitButton.disabled = true;
    loginSubmitButton.textContent = "Validando...";

    try {
        const responseData = await HttpClient.post(
            `${API_URLS.AUTH}/auth/login`,
            { identificador, contrasena },
            { authenticated: false, redirectOnUnauthorized: false }
        );

        SessionManager.saveSession(responseData.token, responseData.usuario);
        showAlert(loginAlert, "Inicio de sesión exitoso. Redirigiendo...", "success");
        window.location.href = "home.html";
    } catch (loginError) {
        showAlert(
            loginAlert,
            loginError.message || "No fue posible iniciar sesión.",
            "error",
            loginError.fieldErrors
        );
    } finally {
        loginSubmitButton.disabled = false;
        loginSubmitButton.textContent = "Iniciar sesión";
    }
}

loginForm.addEventListener("submit", handleLoginSubmit);
redirectIfSessionIsValid();
