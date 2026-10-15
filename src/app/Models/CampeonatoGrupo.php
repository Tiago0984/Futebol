<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Grupo de uma categoria do campeonato (Fase H, Etapa H4): "Grupo 01", "Grupo A". Os times entram pelo
 * participante (tbl_campeonato_time.id_campeonato_grupo; um grupo por time em cada categoria). A classificação
 * é por categoria e grupo e conta só a fase de grupos (Campeonato::classificacoes).
 */
class CampeonatoGrupo extends Model
{
    protected $table      = 'tbl_campeonato_grupo';
    protected $primaryKey = 'id_campeonato_grupo';
    public    $timestamps = false;

    public const NOME_MAX = 30;

    protected $fillable = [
        'id_campeonato',
        'id_categoria',
        'nome_campeonato_grupo',
    ];

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class, 'id_campeonato', 'id_campeonato');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Quantos times estão no grupo (o grupo com times não é removido)
    public function totalDeTimes(): int
    {
        return DB::table('tbl_campeonato_time')->where('id_campeonato_grupo', $this->id_campeonato_grupo)->count();
    }
}
