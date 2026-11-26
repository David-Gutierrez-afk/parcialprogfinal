export const API_URLS = Object.freeze({
    AUTH: "http://127.0.0.1:8001",
    EMPLEADOS: "http://127.0.0.1:8002",
    INCAPACIDADES: "http://127.0.0.1:8003",
    SEGUIMIENTO: "http://127.0.0.1:8004",
});

export const ESTADO_LABELS = Object.freeze({
    activo: "Activo",
    inactivo: "Inactivo",
    registrada: "Registrada",
    en_revision: "En revisión",
    aprobada: "Aprobada",
    rechazada: "Rechazada",
    finalizada: "Finalizada",
});

export const TIPO_INCAPACIDAD_LABELS = Object.freeze({
    enfermedad_general: "Enfermedad general",
    accidente_laboral: "Accidente laboral",
    licencia_medica: "Licencia médica",
    incapacidad_temporal: "Incapacidad temporal",
});

export const ROL_LABELS = Object.freeze({
    administrador: "Administrador",
    gestion_humana: "Gestión Humana",
    empleado: "Empleado",
});
