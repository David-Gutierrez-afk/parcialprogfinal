import { SessionManager } from "./session.js";

export class ApiError extends Error {
    constructor(message, status = 0, fieldErrors = null) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.fieldErrors = fieldErrors;
    }
}

export const HttpClient = {
    async request(url, options = {}) {
        const {
            method = "GET",
            body = null,
            authenticated = true,
            redirectOnUnauthorized = true,
        } = options;

        const headers = {
            Accept: "application/json",
        };

        if (body !== null) {
            headers["Content-Type"] = "application/json";
        }

        if (authenticated) {
            const token = SessionManager.getToken();
            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }
        }

        let response;
        try {
            response = await fetch(url, {
                method,
                headers,
                body: body !== null ? JSON.stringify(body) : undefined,
            });
        } catch (networkError) {
            throw new ApiError(
                "No fue posible conectar con el servicio. Verifique que los microservicios estén en ejecución.",
                0
            );
        }

        let payload = {};
        try {
            payload = await response.json();
        } catch (jsonError) {
            payload = {};
        }

        if (!response.ok) {
            if (response.status === 401 && redirectOnUnauthorized) {
                SessionManager.clearSession();
                window.location.href = "index.html";
            }

            const message = payload.mensaje || `Error en la solicitud (HTTP ${response.status}).`;
            throw new ApiError(message, response.status, payload.errores || null);
        }

        return payload;
    },

    get(url, options = {}) {
        return this.request(url, { ...options, method: "GET" });
    },

    post(url, body, options = {}) {
        return this.request(url, { ...options, method: "POST", body });
    },

    put(url, body, options = {}) {
        return this.request(url, { ...options, method: "PUT", body });
    },

    patch(url, body, options = {}) {
        return this.request(url, { ...options, method: "PATCH", body });
    },
};
