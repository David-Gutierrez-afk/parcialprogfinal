const TOKEN_STORAGE_KEY = "incapacidades.auth.token";
const USER_STORAGE_KEY = "incapacidades.auth.usuario";

export const SessionManager = {
    saveSession(token, usuario) {
        sessionStorage.setItem(TOKEN_STORAGE_KEY, token);
        sessionStorage.setItem(USER_STORAGE_KEY, JSON.stringify(usuario));
    },

    getToken() {
        return sessionStorage.getItem(TOKEN_STORAGE_KEY);
    },

    getUsuario() {
        const rawUser = sessionStorage.getItem(USER_STORAGE_KEY);
        if (!rawUser) {
            return null;
        }

        try {
            return JSON.parse(rawUser);
        } catch (parseError) {
            this.clearSession();
            return null;
        }
    },

    isAuthenticated() {
        return Boolean(this.getToken() && this.getUsuario());
    },

    clearSession() {
        sessionStorage.removeItem(TOKEN_STORAGE_KEY);
        sessionStorage.removeItem(USER_STORAGE_KEY);
    },

    requireAuthOrRedirect() {
        if (!this.isAuthenticated()) {
            window.location.href = "index.html";
            return false;
        }
        return true;
    },
};
