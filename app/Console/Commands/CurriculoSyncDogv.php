<?php

namespace App\Console\Commands;

use App\Models\CurriculoContenido;
use Illuminate\Console\Command;

class CurriculoSyncDogv extends Command
{
    protected $signature = 'curriculo:sync-dogv';
    protected $description = 'Sincroniza contenidos curriculares del DOGV (decretos curriculares de la Comunitat Valenciana)';

    private array $sample = [
        ['etapa' => 'primaria', 'asignatura' => 'Matemàtiques', 'curso' => '3º Primaria', 'bloque' => 'Sentit numèric', 'contenido' => 'Nombres naturals fins a 10.000. Operacions bàsiques en context bilingüe. Resolució de problemes de la vida quotidiana.', 'competencias_clave' => ['STEM', 'CP', 'CCL'], 'criterios_evaluacion' => ['Resol operacions bàsiques', 'Planteja i resol problemes senzills'], 'real_decreto' => 'Decret 56/2022 CV'],
        ['etapa' => 'primaria', 'asignatura' => 'Valencià: Llengua i Literatura', 'curso' => '3º Primaria', 'bloque' => 'Comunicació oral', 'contenido' => 'Comprensió i expressió oral en valencià. Textos orals de la tradició valenciana.', 'competencias_clave' => ['CCL', 'CPSAA'], 'criterios_evaluacion' => ['Es comunica oralment en valencià', 'Comprèn textos orals en valencià'], 'real_decreto' => 'Decret 56/2022 CV'],
        ['etapa' => 'eso', 'asignatura' => 'Geografía e Historia', 'curso' => '1º ESO', 'bloque' => 'Territori i societat valenciana', 'contenido' => 'El territori de la Comunitat Valenciana: relleu, clima, hidrografia. Diversitat comarcal.', 'competencias_clave' => ['STEM', 'CCL', 'CC'], 'criterios_evaluacion' => ['Descriu els trets geogràfics de la CV', 'Identifica les comarques i les seues característiques'], 'real_decreto' => 'Decret 104/2022 CV'],
        ['etapa' => 'eso', 'asignatura' => 'Valencià: Llengua i Literatura', 'curso' => '3º ESO', 'bloque' => 'Literatura valenciana', 'contenido' => 'La literatura valenciana medieval: Joanot Martorell i Tirant lo Blanc. Ausiàs March.', 'competencias_clave' => ['CCL', 'CPSAA'], 'criterios_evaluacion' => ['Llegeix i analitza fragments del Tirant lo Blanc', 'Reconeix els trets de la literatura valenciana medieval'], 'real_decreto' => 'Decret 104/2022 CV'],
        ['etapa' => 'bachillerato', 'asignatura' => 'Història del País Valencià', 'curso' => '2º Bachillerato', 'bloque' => 'Època contemporània', 'contenido' => 'La Comunitat Valenciana en la Transició democràtica. L\'Estatut d\'Autonomia del 1982.', 'competencias_clave' => ['CC', 'CCL', 'CPSAA'], 'criterios_evaluacion' => ['Explica el procés autonòmic valencià', 'Valora l\'Estatut d\'Autonomia'], 'real_decreto' => 'Decret 102/2022 CV'],
    ];

    public function handle(): int
    {
        $this->info('Sincronizando currículo DOGV (Comunitat Valenciana)...');

        $inserted = 0;
        foreach ($this->sample as $item) {
            CurriculoContenido::updateOrCreate(
                ['etapa' => $item['etapa'], 'asignatura' => $item['asignatura'], 'curso' => $item['curso'], 'bloque' => $item['bloque'] ?? null, 'fuente' => 'dogv'],
                array_merge($item, ['fuente' => 'dogv', 'comunidad_autonoma' => 'valenciana'])
            );
            $inserted++;
        }

        $this->info("✓ {$inserted} contenidos curriculares sincronizados (DOGV/CV).");
        $this->comment('NOTA: Datos de muestra. Para producción, conectar al parser real del DOGV.');

        return self::SUCCESS;
    }
}
