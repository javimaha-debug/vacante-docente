<?php

namespace Database\Seeders;

use App\Models\ResourceLink;
use Illuminate\Database\Seeder;

class ResourceLinksSeeder extends Seeder
{
    public function run(): void
    {
        ResourceLink::truncate();

        $links = [
            // Oficial
            [
                'title' => "Conselleria d'Educació GVA",
                'description' => "Portal oficial de la Conselleria d'Educació, Cultura i Esport",
                'url' => 'https://www.gva.es/es/inicio/procedimientos?id_proc=15571',
                'category' => 'oficial',
                'icon' => '🏛️',
                'position' => 1,
            ],
            [
                'title' => 'DOCV / DOGV',
                'description' => 'Diari Oficial de la Generalitat Valenciana',
                'url' => 'https://dogv.gva.es',
                'category' => 'oficial',
                'icon' => '📰',
                'position' => 2,
            ],
            [
                'title' => 'Bolsa de Empleo Docente GVA',
                'description' => 'Acceso directo a la bolsa de trabajo docente',
                'url' => 'https://www.gva.es/es/inicio/procedimientos?id_proc=18763',
                'category' => 'oficial',
                'icon' => '💼',
                'position' => 3,
            ],
            [
                'title' => 'Oposiciones Docentes',
                'description' => 'Convocatoria y proceso selectivo de ingreso al cuerpo docente',
                'url' => 'https://www.gva.es/es/inicio/procedimientos?id_proc=19740',
                'category' => 'oficial',
                'icon' => '🎓',
                'position' => 4,
            ],
            [
                'title' => 'ÍTACA / WebÀgora',
                'description' => 'Plataforma educativa de la Conselleria',
                'url' => 'https://www.gva.es/es/inicio/ov_docents',
                'category' => 'oficial',
                'icon' => '🖥️',
                'position' => 5,
            ],
            [
                'title' => 'BOE - Boletín Oficial del Estado',
                'description' => 'Legislación y normativa estatal',
                'url' => 'https://www.boe.es',
                'category' => 'oficial',
                'icon' => '📋',
                'position' => 6,
            ],
            // Sindicatos
            [
                'title' => 'ANPE',
                'description' => 'Sindicato Independiente de Profesores',
                'url' => 'https://www.anpe.es/valenciana',
                'category' => 'sindicato',
                'icon' => '👥',
                'position' => 7,
            ],
            [
                'title' => 'CCOO Enseñanza',
                'description' => 'Federación de Enseñanza de CCOO PV',
                'url' => 'https://feccoo-pv.ccoo.es',
                'category' => 'sindicato',
                'icon' => '✊',
                'position' => 8,
            ],
            [
                'title' => 'STEPV',
                'description' => "Sindicat de Treballadores i Treballadors de l'Ensenyament del País Valencià",
                'url' => 'https://www.stepv.intersindical.org',
                'category' => 'sindicato',
                'icon' => '📢',
                'position' => 9,
            ],
            [
                'title' => 'UGT Ensenyament',
                'description' => "Federació d'Ensenyament de la UGT-PV",
                'url' => 'https://www.ensenyament.ugt-pv.com',
                'category' => 'sindicato',
                'icon' => '🤝',
                'position' => 10,
            ],
            [
                'title' => 'CSIF Educación',
                'description' => 'Central Sindical Independiente y de Funcionarios',
                'url' => 'https://www.csif.es/comunidad-valenciana',
                'category' => 'sindicato',
                'icon' => '🏢',
                'position' => 11,
            ],
        ];

        foreach ($links as $link) {
            ResourceLink::create(array_merge($link, ['active' => true]));
        }
    }
}
