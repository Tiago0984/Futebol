<?php

namespace App\Models;

use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;

class Time extends Model
{
    use UrlDeImagem;

    // Endereço completo do escudo no JSON da API (o banco guarda só o nome do arquivo)
    protected $appends = ['logo_url'];

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

    // Sem atletas() nem tela de Elenco (Fase G, Etapa 4): quem joga pelo time é a convocação de cada
    // campeonato (convocacoes()). tbl_atleta_time foi apagada (migration 2026_10_15_000005)

    /**
     * Jogos futuros (evento ativo e não concluído) deste time em que o atleta está inscrito, só os daquele
     * campeonato quando informado. Quem sai da convocação continua inscrito neles (a tela do jogo marca "Não
     * convocado"); a tela de Convocados avisa quantos.
     */
    public function jogosFuturosComAtleta(int $idAtleta, ?int $idCampeonato = null): int
    {
        return EventoAtleta::where('id_atleta', $idAtleta)
            ->whereHas('evento', fn ($ev) => $ev
                ->where('status_evento_calendario', 'ATIVO')
                ->naoConcluidos()
                ->whereHas('jogo', fn ($j) => $this->dosJogosDoTime($j, $idCampeonato)))
            ->count();
    }

    // Jogos futuros (evento ativo e não concluído) deste time no campeonato: a tela de Convocados lembra o
    // "Preencher pela convocação" quando alguém é convocado depois que eles existem
    public function jogosFuturosNoCampeonato(int $idCampeonato): int
    {
        return Jogo::whereHas('evento', fn ($ev) => $ev->where('status_evento_calendario', 'ATIVO')->naoConcluidos())
            ->where(fn ($j) => $this->dosJogosDoTime($j, $idCampeonato))
            ->count();
    }

    // Filtro dos jogos em que este time é mandante ou visitante (de um campeonato, quando informado)
    private function dosJogosDoTime($query, ?int $idCampeonato)
    {
        return $query
            ->where(fn ($t) => $t->where('id_time_casa', $this->id_time)->orWhere('id_time_visitante', $this->id_time))
            ->when($idCampeonato, fn ($q) => $q->where('id_campeonato', $idCampeonato));
    }

    /**
     * Por que não pode ser excluído (ou null). Jogo preserva o histórico (e a escalação dele aponta para o
     * time); a convocação também. FKs em NO ACTION.
     */
    public function motivoParaNaoExcluir(): ?string
    {
        $jogos = Jogo::where('id_time_casa', $this->id_time)->orWhere('id_time_visitante', $this->id_time)->count();
        if ($jogos) {
            return "O time {$this->nome_time} tem {$jogos} jogo(s) e não pode ser excluído. Mantenha-o inativo.";
        }

        $convocados = $this->convocacoes()->count();
        if ($convocados) {
            return "O time {$this->nome_time} tem {$convocados} atleta(s) convocado(s) em campeonatos. Remova a convocação antes de excluir.";
        }

        return null;
    }

    // Convocações do time nos campeonatos (Fase G)
    public function convocacoes()
    {
        return $this->hasMany(Convocacao::class, 'id_time', 'id_time');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return static::urlDaImagem($this->attributes['logo_time'] ?? null, 'team');
    }
}
