<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * Elenco para as telas (Elenco do time e jogadores do time no campeonato): os atletas com os dados do
     * vínculo (camisa, posição, titular/reserva e contadores), titulares primeiro, e os cartões de cada um,
     * por tipo. Duas consultas. Devolve [atletas, cartoes].
     */
    public function elencoParaTela(): array
    {
        $atletas = Atleta::join('tbl_atleta_time', 'tbl_atletas.id_atleta', '=', 'tbl_atleta_time.id_atleta')
            ->where('tbl_atleta_time.id_time', $this->id_time)
            ->select(
                'tbl_atletas.id_atleta',
                'tbl_atletas.nome_atleta',
                'tbl_atletas.foto_atleta',
                'tbl_atletas.posicao_atleta',
                'tbl_atleta_time.id_atleta_time',
                'tbl_atleta_time.camisa_atleta_time',
                'tbl_atleta_time.posicao_atleta_time',
                'tbl_atleta_time.status_atleta_time',
                'tbl_atleta_time.jogos_atleta_time',
                'tbl_atleta_time.gols_atleta_time',
                'tbl_atleta_time.defesas_atleta_time',
                'tbl_atleta_time.convocacao_atleta_time'
            )
            ->orderByRaw("FIELD(tbl_atleta_time.status_atleta_time, 'TITULAR', 'RESERVA', 'ATIVO')")
            ->orderBy('tbl_atleta_time.camisa_atleta_time')
            ->get();

        $cartoes = DB::table('tbl_cartoes')
            ->whereIn('id_atleta', $atletas->pluck('id_atleta'))
            ->select('id_atleta', 'tipo_cartao', DB::raw('COUNT(*) as total'))
            ->groupBy('id_atleta', 'tipo_cartao')
            ->get()
            ->groupBy('id_atleta');

        return [$atletas, $cartoes];
    }

    /**
     * Jogos futuros (evento ativo e não concluído) deste time em que o atleta está inscrito. Quem sai do
     * elenco ou da convocação continua inscrito neles (a tela do jogo marca "Fora do elenco"/"Não
     * convocado"); a tela do Elenco e a de Convocados avisam quantos. $idCampeonato: só os jogos daquele
     * campeonato (convocação); $soAmistosos: só os amistosos (o elenco só vale neles, Fase G, Etapa 2).
     */
    public function jogosFuturosComAtleta(int $idAtleta, ?int $idCampeonato = null, bool $soAmistosos = false): int
    {
        return EventoAtleta::where('id_atleta', $idAtleta)
            ->whereHas('evento', fn ($ev) => $ev
                ->where('status_evento_calendario', 'ATIVO')
                ->naoConcluidos()
                ->whereHas('jogo', fn ($j) => $this->dosJogosDoTime($j, $idCampeonato, $soAmistosos)))
            ->count();
    }

    // Jogos futuros (evento ativo e não concluído) deste time no campeonato: a tela de Convocados lembra o
    // "Preencher pela convocação" quando alguém é convocado depois que eles existem
    public function jogosFuturosNoCampeonato(int $idCampeonato): int
    {
        return Jogo::whereHas('evento', fn ($ev) => $ev->where('status_evento_calendario', 'ATIVO')->naoConcluidos())
            ->where(fn ($j) => $this->dosJogosDoTime($j, $idCampeonato, false))
            ->count();
    }

    // Filtro dos jogos em que este time é mandante ou visitante (de um campeonato, ou só amistosos)
    private function dosJogosDoTime($query, ?int $idCampeonato, bool $soAmistosos)
    {
        return $query
            ->where(fn ($t) => $t->where('id_time_casa', $this->id_time)->orWhere('id_time_visitante', $this->id_time))
            ->when($idCampeonato, fn ($q) => $q->where('id_campeonato', $idCampeonato))
            ->when($soAmistosos, fn ($q) => $q->whereNull('id_campeonato'));
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
}
