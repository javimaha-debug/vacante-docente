<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $maestros = 'Maestros';
        $secundaria = 'Profesores de Enseñanza Secundaria';
        $fp = 'Profesorado Especialista en Sectores Singulares de FP';

        $groups = [
            'maestros' => [
                'body' => $maestros,
                'items' => [
                    '120' => 'Educación Infantil',
                    '121' => 'Educación Primaria',
                    '122' => 'Lengua Extranjera: Inglés',
                    '123' => 'Educación Física',
                    '124' => 'Música',
                    '125' => 'Audición y Lenguaje',
                    '126' => 'Pedagogía Terapéutica',
                ],
            ],
            'secundaria' => [
                'body' => $secundaria,
                'items' => [
                    '101' => 'Filosofía',
                    '102' => 'Lengua Castellana y Literatura',
                    '103' => 'Geografía e Historia',
                    '104' => 'Matemáticas',
                    '105' => 'Física y Química',
                    '106' => 'Biología y Geología',
                    '107' => 'Dibujo',
                    '108' => 'Francés',
                    '109' => 'Inglés',
                    '110' => 'Alemán',
                    '111' => 'Italiano',
                    '112' => 'Latín y Griego',
                    '113' => 'Economía',
                    '114' => 'Tecnología e Ingeniería',
                    '115' => 'Música',
                    '116' => 'Educación Física',
                    '117' => 'Orientación Educativa',
                    '118' => 'Valenciano: Llengua i Literatura',
                    '119' => 'Cultura Clásica',
                    '120' => 'Administración de Empresas',
                    '121' => 'Análisis y Química Industrial',
                    '122' => 'Construcciones Civiles y Edificación',
                    '123' => 'Electricidad y Electrónica',
                    '124' => 'Formación y Orientación Laboral',
                    '125' => 'Hostelería y Turismo',
                    '126' => 'Informática',
                    '127' => 'Intervención Sociocomunitaria',
                    '128' => 'Laboratorio',
                    '129' => 'Navegación e Instalaciones Marinas',
                    '130' => 'Organización y Proyectos de Fabricación Mecánica',
                    '131' => 'Organización y Proyectos de Sistemas Energéticos',
                    '132' => 'Organización y Gestión Comercial',
                    '133' => 'Procesos Diagnósticos Clínicos y Productos Ortoprotésicos',
                    '134' => 'Procesos Sanitarios y Asistenciales',
                    '135' => 'Procesos y Medios de Comunicación',
                    '136' => 'Procesos de Producción Agraria',
                    '137' => 'Sistemas Electrónicos',
                    '138' => 'Sistemas Electrotécnicos y Automáticos',
                    '139' => 'Asesoría y Procesos de Imagen Personal',
                    '140' => 'Operaciones y Equipos de Elaboración de Productos Alimentarios',
                    '141' => 'Madera, Mueble y Corcho',
                    '142' => 'Textil, Confección y Piel',
                    '143' => 'Artes Plásticas y Diseño en Volumen',
                    '144' => 'Música y Artes Escénicas',
                    '218' => 'Orientación Educativa',
                ],
            ],
            'fp' => [
                'body' => $fp,
                'items' => [
                    '501' => 'Cocina y Pastelería',
                    '502' => 'Estética',
                    '503' => 'Mantenimiento de Vehículos',
                    '504' => 'Mecanizado y Mantenimiento de Máquinas',
                    '505' => 'Operaciones de Producción Agrícola',
                    '506' => 'Servicios de Restaurante y Bar',
                    '507' => 'Instalaciones Electrotécnicas',
                    '508' => 'Soldadura',
                    '509' => 'Fabricación e Instalación de Carpintería y Mueble',
                    '510' => 'Peluquería',
                    '511' => 'Caracterización y Maquillaje Profesional',
                    '512' => 'Operaciones de Grabación y Tratamiento de Sonido e Imagen',
                ],
            ],
        ];

        $rows = [];
        foreach ($groups as $level => $group) {
            foreach ($group['items'] as $code => $name) {
                $rows[] = [
                    'code' => (string) $code,
                    'name' => $name,
                    'body' => $group['body'],
                    'education_level' => $level,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Idempotent: upsert on (code, education_level).
        Specialty::upsert(
            $rows,
            ['code', 'education_level'],
            ['name', 'body', 'updated_at']
        );
    }
}
