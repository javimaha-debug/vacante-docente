<?php

namespace App\Console\Commands;

use App\Models\CurriculoContenido;
use Illuminate\Console\Command;

class CurriculoSyncBoe extends Command
{
    protected $signature = 'curriculo:sync-boe';
    protected $description = 'Sincroniza contenidos curriculares del BOE (RD 157/2022 Primaria, RD 217/2022 ESO, RD 243/2022 Bachillerato)';

    // Sample curriculum data — replace with real BOE parsing when available
    private array $sample = [
        ['etapa' => 'primaria', 'asignatura' => 'Matemáticas', 'curso' => '3º Primaria', 'bloque' => 'Sentido numérico', 'contenido' => 'Números naturales hasta el 10.000. Operaciones básicas: suma, resta, multiplicación y división.', 'competencias_clave' => ['STEM', 'CP'], 'criterios_evaluacion' => ['Resuelve operaciones básicas con números hasta 10.000', 'Aplica estrategias de cálculo mental'], 'real_decreto' => 'RD 157/2022'],
        ['etapa' => 'primaria', 'asignatura' => 'Lengua Castellana y Literatura', 'curso' => '3º Primaria', 'bloque' => 'Comunicación oral', 'contenido' => 'Comprensión y expresión oral en situaciones cotidianas. Escucha activa y respeto al turno de palabra.', 'competencias_clave' => ['CCL', 'CPSAA'], 'criterios_evaluacion' => ['Participa activamente en intercambios orales', 'Comprende textos orales adecuados a su nivel'], 'real_decreto' => 'RD 157/2022'],
        ['etapa' => 'eso', 'asignatura' => 'Geografía e Historia', 'curso' => '1º ESO', 'bloque' => 'El espacio geográfico', 'contenido' => 'La Tierra como planeta: movimientos, coordenadas geográficas, representación cartográfica.', 'competencias_clave' => ['STEM', 'CCL', 'CD'], 'criterios_evaluacion' => ['Localiza espacios geográficos usando coordenadas', 'Interpreta mapas y representaciones cartográficas'], 'real_decreto' => 'RD 217/2022'],
        ['etapa' => 'eso', 'asignatura' => 'Geografía e Historia', 'curso' => '3º ESO', 'bloque' => 'Sociedades contemporáneas', 'contenido' => 'La Revolución Industrial y sus consecuencias sociales, económicas y ambientales.', 'competencias_clave' => ['CPSAA', 'CC', 'CCL'], 'criterios_evaluacion' => ['Analiza causas y consecuencias de la Revolución Industrial', 'Establece conexiones con problemas actuales'], 'real_decreto' => 'RD 217/2022'],
        ['etapa' => 'eso', 'asignatura' => 'Matemáticas', 'curso' => '2º ESO', 'bloque' => 'Álgebra', 'contenido' => 'Expresiones algebraicas: monomios, polinomios, operaciones. Ecuaciones de primer y segundo grado.', 'competencias_clave' => ['STEM', 'CP'], 'criterios_evaluacion' => ['Opera con expresiones algebraicas', 'Resuelve ecuaciones de primer grado'], 'real_decreto' => 'RD 217/2022'],
        ['etapa' => 'eso', 'asignatura' => 'Lengua Castellana y Literatura', 'curso' => '4º ESO', 'bloque' => 'Literatura', 'contenido' => 'Literatura de los siglos XX y XXI: generaciones literarias, movimientos, autores representativos.', 'competencias_clave' => ['CCL', 'CPSAA', 'CE'], 'criterios_evaluacion' => ['Identifica rasgos de los movimientos literarios del s.XX', 'Analiza textos literarios aplicando recursos retóricos'], 'real_decreto' => 'RD 217/2022'],
        ['etapa' => 'bachillerato', 'asignatura' => 'Historia de España', 'curso' => '2º Bachillerato', 'bloque' => 'España contemporánea', 'contenido' => 'La Transición española: proceso político, Constitución de 1978, consolidación democrática.', 'competencias_clave' => ['CC', 'CCL', 'CPSAA'], 'criterios_evaluacion' => ['Explica el proceso de Transición democrática', 'Valora la importancia de la Constitución de 1978'], 'real_decreto' => 'RD 243/2022'],
        ['etapa' => 'bachillerato', 'asignatura' => 'Matemáticas II', 'curso' => '2º Bachillerato', 'bloque' => 'Álgebra lineal', 'contenido' => 'Matrices: operaciones, determinantes, sistemas de ecuaciones lineales. Regla de Cramer.', 'competencias_clave' => ['STEM', 'CP'], 'criterios_evaluacion' => ['Opera con matrices y calcula determinantes', 'Resuelve sistemas de ecuaciones por distintos métodos'], 'real_decreto' => 'RD 243/2022'],
        ['etapa' => 'fp', 'asignatura' => 'Formación y Orientación Laboral', 'curso' => '1º FP', 'bloque' => 'Relaciones laborales', 'contenido' => 'El contrato de trabajo: modalidades, derechos y obligaciones. Seguridad Social.', 'competencias_clave' => ['CE', 'CC', 'CPSAA'], 'criterios_evaluacion' => ['Diferencia modalidades contractuales', 'Conoce sus derechos y obligaciones laborales'], 'real_decreto' => null],
    ];

    public function handle(): int
    {
        $this->info('Sincronizando currículo BOE...');

        $inserted = 0;
        foreach ($this->sample as $item) {
            CurriculoContenido::updateOrCreate(
                ['etapa' => $item['etapa'], 'asignatura' => $item['asignatura'], 'curso' => $item['curso'], 'bloque' => $item['bloque'], 'fuente' => 'boe'],
                array_merge($item, ['fuente' => 'boe'])
            );
            $inserted++;
        }

        $this->info("✓ {$inserted} contenidos curriculares sincronizados (BOE).");
        $this->comment('NOTA: Datos de muestra. Para producción, conectar al parser real del BOE.');

        return self::SUCCESS;
    }
}
