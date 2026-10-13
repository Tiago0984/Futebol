<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    use SerializaDatasComFuso; // datas no JSON da API: 2025-01-01T00:00:00-03:00

    protected $table = 'tbl_campeonato';
    protected $primaryKey = 'id_campeonato';
    public $timestamps = false;

    // Valores de tipo_campeonato (gravados em maiúsculas, como os que já estão no banco) => rótulo
    public const TIPOS = [
        'LIGA'            => 'Liga',
        'COPA'            => 'Copa',
        'TORNEIO'         => 'Torneio',
        'MATA-MATA'       => 'Mata-mata',
        'PONTOS CORRIDOS' => 'Pontos Corridos',
        'AMISTOSO'        => 'Amistoso',
    ];

    protected $fillable = [
        'logo_evento',
        'banner_evento',
        'nome_campeonato',
        'organizador_campeonato',
        'descricao_campeonato',
        'tipo_campeonato',
        'data_inicio_campeonato',
        'data_fim_campeonato',
        'local_evento',
        'id_categoria',
        'status_campeonato',
    ];

    protected $casts = [
        'data_inicio_campeonato' => 'datetime',
        'data_fim_campeonato'    => 'datetime',
    ];

    // Tipo sempre em maiúsculas (o select do formulário usa as chaves de TIPOS)
    public function setTipoCampeonatoAttribute($valor): void
    {
        $this->attributes['tipo_campeonato'] = $valor === null ? null : mb_strtoupper(trim($valor));
    }

    // Rótulo do tipo; valor antigo fora da lista aparece como está
    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_campeonato] ?? (string) $this->tipo_campeonato;
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_campeonato_time', 'id_campeonato', 'id_time');
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class, 'id_campeonato', 'id_campeonato');
    }

    // "Em andamento" (menu do admin): status ATIVO e hoje dentro do período (CLAUDE.md, seção 5)
    public function scopeEmAndamento($query)
    {
        $hoje = now()->toDateString();

        return $query->where('status_campeonato', 'ATIVO')
            ->whereDate('data_inicio_campeonato', '<=', $hoje)
            ->whereDate('data_fim_campeonato', '>=', $hoje);
    }

    /**
     * O evento de cada jogo guarda uma cópia da categoria do campeonato (JogosController), que é só exibição
     * desde a Fase 8. Quando a categoria do campeonato muda, a cópia acompanha em todos os jogos dele (inclusive
     * concluídos, cancelados e ocultos), com histórico e sem avisar nem mexer nas inscrições. Devolve quantos
     * jogos mudaram.
     */
    public function atualizarCategoriaDosJogos(?int $idUsuario): int
    {
        $eventos = EventoCalendario::whereIn('id_evento_calendario', $this->jogos()->select('id_evento'))
            ->where(fn ($q) => $this->id_categoria === null
                ? $q->whereNotNull('id_categoria')
                : $q->whereNull('id_categoria')->orWhere('id_categoria', '<>', $this->id_categoria))
            ->get();

        foreach ($eventos as $evento) {
            $evento->atualizarComHistorico(['id_categoria' => $this->id_categoria], $idUsuario);
        }

        return $eventos->count();
    }

    // Por que não pode ser excluído (ou null): jogo preserva o histórico e a FK de tbl_jogos é NO ACTION
    public function motivoParaNaoExcluir(): ?string
    {
        $jogos = $this->jogos()->count();

        return $jogos
            ? "O campeonato {$this->nome_campeonato} tem {$jogos} jogo(s) e não pode ser excluído. Inative-o para tirá-lo do site."
            : null;
    }
}
