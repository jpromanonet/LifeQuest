<?php

declare(strict_types=1);

/**
 * Datos de ejemplo para una instalación nueva.
 *
 * Todo lo que hay acá es inventado y sirve para que la aplicación se vea viva
 * al instalarla. Los años no están fijados: se resuelven contra el año en curso
 * mediante las claves 'previous', 'current' y 'next'.
 */
final class SeedManifest
{
    /** @return array<string, mixed> */
    public static function data(): array
    {
        return [
            'user' => [
                'key' => 'demo',
                'name' => 'Usuario demo',
                'email' => 'demo@lifequest.local',
                'password' => 'lifequest-demo',
                'locale' => 'es_AR',
                'timezone' => 'America/Argentina/Buenos_Aires',
            ],

            'life_areas' => [
                ['key' => 'annual_educacion', 'name' => 'Educación', 'color' => '#0891B2', 'icon' => 'graduation-cap', 'sort' => 1, 'scope' => 'annual'],
                ['key' => 'annual_trabajo', 'name' => 'Trabajo', 'color' => '#4F46E5', 'icon' => 'briefcase', 'sort' => 2, 'scope' => 'annual'],
                ['key' => 'annual_marca', 'name' => 'Marca personal', 'color' => '#DB2777', 'icon' => 'pen', 'sort' => 3, 'scope' => 'annual'],
                [
                    'key' => 'annual_finanzas',
                    'name' => 'Finanzas',
                    'color' => '#64748B',
                    'icon' => 'wallet',
                    'sort' => 4,
                    'scope' => 'annual',
                    'annual_goals_only' => true,
                ],
                ['key' => 'annual_salud', 'name' => 'Salud', 'color' => '#16A34A', 'icon' => 'heart-pulse', 'sort' => 5, 'scope' => 'annual'],
                ['key' => 'annual_proyectos', 'name' => 'Proyectos', 'color' => '#7C83E1', 'icon' => 'folder', 'sort' => 6, 'scope' => 'annual'],

                ['key' => 'hz_laborales', 'name' => 'Laborales', 'color' => '#4F46E5', 'icon' => 'briefcase', 'sort' => 1, 'scope' => 'horizon'],
                ['key' => 'hz_salud', 'name' => 'Salud', 'color' => '#16A34A', 'icon' => 'heart-pulse', 'sort' => 2, 'scope' => 'horizon'],
                ['key' => 'hz_financieros', 'name' => 'Financieros', 'color' => '#0891B2', 'icon' => 'wallet', 'sort' => 3, 'scope' => 'horizon'],
                ['key' => 'hz_patrimonial', 'name' => 'Patrimonial', 'color' => '#CA8A04', 'icon' => 'landmark', 'sort' => 4, 'scope' => 'horizon'],
                ['key' => 'hz_academicos', 'name' => 'Académicos', 'color' => '#7C3AED', 'icon' => 'graduation-cap', 'sort' => 5, 'scope' => 'horizon'],
                ['key' => 'hz_hobbies', 'name' => 'Hobbies', 'color' => '#EA580C', 'icon' => 'compass', 'sort' => 6, 'scope' => 'horizon'],
            ],

            'annual_template_sections' => [
                'annual_educacion',
                'annual_trabajo',
                'annual_marca',
                'annual_finanzas',
                'annual_salud',
                'annual_proyectos',
            ],

            'horizon_template_sections' => [
                'hz_laborales',
                'hz_salud',
                'hz_financieros',
                'hz_patrimonial',
                'hz_academicos',
                'hz_hobbies',
            ],

            // Los objetivos de horizonte son siempre binarios: hechos o no.
            'long_term_goals' => [
                ['key' => 'demo_hz_carrera', 'title' => 'Construir una carrera profesional sólida', 'area' => 'hz_laborales', 'status' => 'active'],
                ['key' => 'demo_hz_referente', 'title' => 'Ser un referente en mi especialidad', 'area' => 'hz_laborales', 'status' => 'planned'],
                ['key' => 'demo_hz_salud', 'title' => 'Sostener un estilo de vida saludable', 'area' => 'hz_salud', 'status' => 'active'],
                ['key' => 'demo_hz_ingresos', 'title' => 'Tener más de una fuente de ingresos', 'area' => 'hz_financieros', 'status' => 'planned'],
                ['key' => 'demo_hz_vivienda', 'title' => 'Alcanzar la meta patrimonial propuesta', 'area' => 'hz_patrimonial', 'status' => 'planned'],
                ['key' => 'demo_hz_posgrado', 'title' => 'Completar una formación de posgrado', 'area' => 'hz_academicos', 'status' => 'planned'],
                ['key' => 'demo_hz_musica', 'title' => 'Aprender a tocar un instrumento', 'area' => 'hz_hobbies', 'status' => 'completed'],
            ],

            /*
             * Objetivos anuales por posición relativa al año en curso.
             * progress_mode: months (doce casillas), quantity (unidades) o binary (sí/no).
             */
            'annual_goals' => [
                'previous' => [
                    ['key' => 'demo_prev_curso', 'title' => 'Terminar un curso de formación', 'area' => 'annual_educacion', 'status' => 'completed', 'progress_mode' => 'binary'],
                    ['key' => 'demo_prev_ascenso', 'title' => 'Asumir una nueva responsabilidad en el trabajo', 'area' => 'annual_trabajo', 'status' => 'completed', 'progress_mode' => 'binary'],
                    ['key' => 'demo_prev_ahorro', 'title' => 'Sostener el ahorro mensual', 'area' => 'annual_finanzas', 'status' => 'completed', 'progress_mode' => 'months'],
                    ['key' => 'demo_prev_gym', 'title' => 'Entrenar todos los meses', 'area' => 'annual_salud', 'status' => 'completed', 'progress_mode' => 'months'],
                ],
                'current' => [
                    ['key' => 'demo_cur_idioma', 'title' => 'Practicar un idioma todos los meses', 'area' => 'annual_educacion', 'status' => 'active', 'progress_mode' => 'months'],
                    ['key' => 'demo_cur_certificacion', 'title' => 'Aprobar una certificación técnica', 'area' => 'annual_educacion', 'status' => 'active', 'progress_mode' => 'binary'],
                    ['key' => 'demo_cur_foco', 'title' => 'Sostener bloques de trabajo profundo', 'area' => 'annual_trabajo', 'status' => 'active', 'progress_mode' => 'months'],
                    ['key' => 'demo_cur_articulos', 'title' => 'Publicar artículos en el blog', 'area' => 'annual_marca', 'status' => 'active', 'progress_mode' => 'quantity', 'target_value' => 12, 'current_value' => 4, 'unit' => 'artículos'],
                    ['key' => 'demo_cur_fondo', 'title' => 'Completar el fondo de emergencia', 'area' => 'annual_finanzas', 'status' => 'active', 'progress_mode' => 'binary'],
                    ['key' => 'demo_cur_entrenar', 'title' => 'Entrenar al menos tres veces por semana', 'area' => 'annual_salud', 'status' => 'active', 'progress_mode' => 'months'],
                    ['key' => 'demo_cur_chequeo', 'title' => 'Hacer el chequeo médico anual', 'area' => 'annual_salud', 'status' => 'planned', 'progress_mode' => 'binary'],
                    ['key' => 'demo_cur_proyecto', 'title' => 'Lanzar un proyecto personal', 'area' => 'annual_proyectos', 'status' => 'active', 'progress_mode' => 'months'],
                ],
                'next' => [
                    ['key' => 'demo_next_posgrado', 'title' => 'Empezar una formación de posgrado', 'area' => 'annual_educacion', 'status' => 'planned', 'progress_mode' => 'binary'],
                    ['key' => 'demo_next_charla', 'title' => 'Dar una charla en una conferencia', 'area' => 'annual_marca', 'status' => 'planned', 'progress_mode' => 'binary'],
                    ['key' => 'demo_next_inversion', 'title' => 'Definir un plan de inversión', 'area' => 'annual_finanzas', 'status' => 'planned', 'progress_mode' => 'binary'],
                    ['key' => 'demo_next_maraton', 'title' => 'Correr una carrera de 10 km', 'area' => 'annual_salud', 'status' => 'planned', 'progress_mode' => 'binary'],
                ],
            ],

            'habits' => [
                ['key' => 'demo_h_leer', 'number' => 1, 'name' => 'Leer 20 minutos', 'frequency' => 'daily', 'preferred_time' => 'evening'],
                ['key' => 'demo_h_idioma', 'number' => 2, 'name' => 'Practicar idioma', 'frequency' => 'weekdays', 'days' => [1, 2, 3, 4, 5]],
                ['key' => 'demo_h_foco', 'number' => 3, 'name' => 'Bloque de trabajo profundo', 'frequency' => 'weekdays', 'days' => [1, 2, 3, 4, 5], 'preferred_time' => 'morning'],
                ['key' => 'demo_h_escribir', 'number' => 4, 'name' => 'Escribir 300 palabras', 'frequency' => 'daily'],
                ['key' => 'demo_h_entrenar', 'number' => 5, 'name' => 'Entrenar', 'frequency' => 'weekdays', 'days' => [1, 3, 5], 'preferred_time' => 'morning'],
                ['key' => 'demo_h_agua', 'number' => 6, 'name' => 'Tomar 2 litros de agua', 'frequency' => 'daily'],
                ['key' => 'demo_h_gastos', 'number' => 7, 'name' => 'Registrar gastos del día', 'frequency' => 'daily', 'preferred_time' => 'evening'],
            ],
        ];
    }
}
