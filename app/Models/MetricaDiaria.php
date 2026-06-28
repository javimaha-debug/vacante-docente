<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricaDiaria extends Model
{
    protected $table = 'metricas_diarias';

    protected $fillable = [
        'fecha', 'usuarios_total', 'usuarios_nuevos', 'usuarios_activos_7d', 'usuarios_free',
        'usuarios_de_pago', 'mrr', 'arr', 'nuevos_interino', 'nuevos_opositor',
        'nuevos_docente_pro', 'nuevos_todo_en_uno', 'churn_count', 'churn_mrr',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'mrr' => 'decimal:2',
            'arr' => 'decimal:2',
            'churn_mrr' => 'decimal:2',
        ];
    }
}
