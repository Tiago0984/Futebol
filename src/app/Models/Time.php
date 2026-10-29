<?php

namespace App\Models;

use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Time extends Model
{
    use UrlDeImagem;

    // Endereço completo do escudo no JSON da API (o banco guarda só o nome do arquivo) e se o time é da AACJ
    protected $appends = ['logo_url', 'da_aacj'];

    protected $table = 'tbl_time';
    protected $primaryKey = 'id_time';
    public $timestamps = false;

    // tbl_time.id_categoria (a categoria única do cadastro antigo) ficou sem uso em 28/10/2026: aceita NULL, nada lê
    // nem grava nela; a remoção está nos débitos do CLAUDE.md
    protected $fillable = [
        'logo_time',
        'nome_time',
        'tipo_time',
        'status_time',
    ];

    // Time da AACJ (INTERNO) ou adversário (EXTERNO), para o app destacar a escolinha (29/10/2026). null quando a
    // consulta não trouxe o tipo_time (select de poucas colunas), para nunca dizer "adversário" por engano
    public function getDaAacjAttribute(): ?bool
    {
        return array_key_exists('tipo_time', $this->attributes) ? $this->attributes['tipo_time'] === 'INTERNO' : null;
    }

    // Categorias de que o time participa (tbl_time_categoria, 27/10/2026): um cadastro só para a AACJ e para cada
    // adversário, com as categorias marcadas, no lugar de um time por categoria
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_time_categoria', 'id_time', 'id_categoria')
            ->orderByDesc('tbl_categoria.sexo_categoria')->orderBy('tbl_categoria.idade_min_categoria');
    }

    /**
     * Grava as categorias marcadas no cadastro (tbl_time_categoria).
     *
     * @param int[] $ids
     */
    public function definirCategorias(array $ids): void
    {
        $this->categorias()->sync(array_values(array_unique(array_map('intval', $ids))));
        $this->unsetRelation('categorias');
    }

    /**
     * Por que as categorias desmarcadas não podem sair (uma mensagem por categoria, vazio = podem): o time é
     * participante de algum campeonato nela (tbl_campeonato_time). Tirar do campeonato antes (o que também confere
     * jogos e convocados).
     *
     * @param int[] $idsNovos as categorias que ficam marcadas
     */
    public function errosDasCategoriasQueSaem(array $idsNovos): array
    {
        $saem = $this->categorias()->whereNotIn('tbl_categoria.id_categoria', array_map('intval', $idsNovos))->get();
        if ($saem->isEmpty()) {
            return [];
        }

        $participacoes = DB::table('tbl_campeonato_time as ct')
            ->join('tbl_campeonato as c', 'c.id_campeonato', '=', 'ct.id_campeonato')
            ->where('ct.id_time', $this->id_time)->whereIn('ct.id_categoria', $saem->pluck('id_categoria'))
            ->orderBy('c.nome_campeonato')->get(['ct.id_categoria', 'c.nome_campeonato'])->groupBy('id_categoria');

        return $saem->filter(fn (Categoria $categoria) => $participacoes->has($categoria->id_categoria))
            ->map(fn (Categoria $categoria) => "O {$this->nome_time} joga no {$categoria->rotulo} do campeonato "
                . $participacoes[$categoria->id_categoria]->pluck('nome_campeonato')->implode(', ')
                . '. Tire-o do campeonato nessa categoria antes de desmarcá-la.')
            ->values()->all();
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
