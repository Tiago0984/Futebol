<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Time extends Model
{
    protected $table = 'tbl_time';
    protected $primaryKey = 'id_time';
    public $timestamps = false;

    protected $fillable = [
        'logo_time',
        'nome_time',
        'tipo_time',
        'id_categoria',
        'status_time',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function campeonatos()
    {
        return $this->belongsToMany(Campeonato::class, 'tbl_campeonato_time', 'id_time', 'id_campeonato');
    }

    // Define que um Time tem muitos Atletas através da tabela pivô
    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_atleta_time', 'id_time', 'id_atleta')
            ->withPivot([
                'status_atleta_time',
                'posicao_atleta_time',
                'camisa_atleta_time',
                'gols_atleta_time',
                'defesas_atleta_time',
                'jogos_atleta_time',
                'convocacao_atleta_time'
            ]);
    }

    /**
     * Por que não pode ser excluído (ou null). Jogo preserva o histórico (e a escalação dele aponta para o
     * time); o elenco guarda camisa e contadores, e sai pela edição de cada atleta. FKs em NO ACTION.
     */
    public function motivoParaNaoExcluir(): ?string
    {
        $jogos = Jogo::where('id_time_casa', $this->id_time)->orWhere('id_time_visitante', $this->id_time)->count();
        if ($jogos) {
            return "O time {$this->nome_time} tem {$jogos} jogo(s) e não pode ser excluído. Mantenha-o inativo.";
        }

        $elenco = $this->atletas()->count();
        if ($elenco) {
            return "O time {$this->nome_time} tem {$elenco} atleta(s) no elenco. Tire-os do time na edição de cada atleta antes de excluir.";
        }

        return null;
    }
}
