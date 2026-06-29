<?php

namespace Database\Seeders;

use App\Models\AcademicCalendarEvent;
use App\Models\MonitoredSource;
use Illuminate\Database\Seeder;

class DocumentMonitorSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'GVA RRHH Educació', 'url' => 'https://ceice.gva.es/es/web/rrhh-educacion', 'type' => 'gva'],
            ['name' => 'GVA Adjudicaciones Inicio Curso', 'url' => 'https://ceice.gva.es/es/web/rrhh-educacion/convocatoria-y-peticion-telematica4', 'type' => 'gva'],
            ['name' => 'GVA Adjudicaciones Continuas', 'url' => 'https://ceice.gva.es/es/web/rrhh-educacion/adjudicaciones-continuas', 'type' => 'gva'],
            ['name' => 'DOGV', 'url' => 'https://dogv.gva.es/', 'type' => 'dogv'],
            ['name' => 'ANPE CV', 'url' => 'https://anpecomunidadvalenciana.es', 'type' => 'sindicato'],
            ['name' => 'CCOO Ensenyament PV', 'url' => 'https://ccoo.es/ensenyament', 'type' => 'sindicato'],
            ['name' => 'UGT FETE CV', 'url' => 'https://ensenyamentugtpv.org', 'type' => 'sindicato'],
            ['name' => 'CSIF Educación CV', 'url' => 'https://csif.es/comunitat-valenciana/educacion', 'type' => 'sindicato'],
        ];

        foreach ($sources as $source) {
            MonitoredSource::updateOrCreate(
                ['url' => $source['url']],
                $source + ['active' => true],
            );
        }

        // Known dates for curso 2026-2027. The first is official/confirmed; the
        // rest are estimates shown only to superadmins until confirmed.
        $events = [
            [
                'title' => 'Solicitud adjudicación inicio curso',
                'event_type' => 'solicitud', 'event_date' => '2026-07-08',
                'affects' => 'interinos', 'is_estimated' => false, 'is_confirmed' => true,
                'visibility' => 'public',
            ],
            [
                'title' => 'Listado provisional participantes',
                'event_type' => 'listado_provisional', 'event_date' => '2026-07-22',
                'affects' => 'interinos', 'is_estimated' => true, 'is_confirmed' => false,
                'visibility' => 'superadmin_only',
            ],
            [
                'title' => 'Listado definitivo participantes',
                'event_type' => 'listado_definitivo', 'event_date' => '2026-07-30',
                'affects' => 'interinos', 'is_estimated' => true, 'is_confirmed' => false,
                'visibility' => 'superadmin_only',
            ],
            [
                'title' => 'Adjudicación inicio curso 2026-2027',
                'event_type' => 'adjudicacion', 'event_date' => '2026-08-05',
                'affects' => 'todos', 'is_estimated' => true, 'is_confirmed' => false,
                'visibility' => 'superadmin_only',
            ],
        ];

        foreach ($events as $event) {
            AcademicCalendarEvent::updateOrCreate(
                ['title' => $event['title'], 'event_date' => $event['event_date']],
                $event,
            );
        }
    }
}
