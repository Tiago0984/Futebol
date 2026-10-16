<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Inscrição de um atleta num evento (sem status: remover apaga a linha)
class EventoAtleta extends Model
{
    protected $table      = 'tbl_evento_atleta';
    protected $primaryKey = 'id_evento_atleta';
    public    $timestamps = false;

    // Valores de origem_evento_atleta => rótulo. CATEGORIA e ELENCO são automáticas (acompanham a categoria
    // do evento ou os times do jogo); INDIVIDUAL é escolha do admin e nenhuma sincronização mexe nela.
    public const ORIGENS = [
        'CATEGORIA'  => 'Pela categoria',
        'INDIVIDUAL' => 'Individual',
        'ELENCO'     => 'Pelo elenco',
        'CONVOCACAO' => 'Pela convocação',
    ];

    // Explicação curta de cada origem (dica ao passar o mouse no selo, no admin)
    public const DICAS_ORIGEM = [
        'CATEGORIA'  => 'Entrou automaticamente por ser da categoria do evento. Sai se o evento mudar de categoria.',
        'INDIVIDUAL' => 'Escolhido à mão pelo admin. Nenhuma troca de categoria ou de time tira o atleta; só a remoção.',
        'ELENCO'     => 'Entrou automaticamente pelo elenco do time. Sai do jogo se o time dele sair do jogo.',
        'CONVOCACAO' => 'Entrou automaticamente por estar convocado pelo time neste campeonato. Sai do jogo se o time dele sair do jogo.',
    ];

    // Titular ou reserva no jogo (Fase D, Etapa D2; por jogo): valor de titular_evento_atleta => rótulo.
    // NULL = a definir
    public const TITULARIDADES = [
        'TITULAR' => 'Titular',
        'RESERVA' => 'Reserva',
    ];

    protected $fillable = [
        'id_evento_calendario',
        'id_atleta',
        'id_time',
        'posicao_evento_atleta',
        'titular_evento_atleta',
        'origem_evento_atleta',
        'id_usuario',
        'data_evento_atleta',
    ];

    protected $casts = [
        'data_evento_atleta' => 'datetime',
    ];

    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    // Quem inscreveu
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Colunas que acompanham a troca de time na escalação (Fase D, Etapa D2): a titularidade é do time, então
     * outro time limpa o titular/reserva (a posição fica); sem time, limpa os dois. Use junto do id_time em
     * todo update que muda o time (inclusive os em massa, que não passam por eventos do model).
     */
    public static function aoMudarDeTime(?int $idTime): array
    {
        return ['id_time' => $idTime, 'titular_evento_atleta' => null]
            + ($idTime === null ? ['posicao_evento_atleta' => null] : []);
    }

    // Posição no jogo para a tela: a gravada ou, sem ela, a principal do atleta (sugerida, ainda não gravada).
    // Precisa de atleta.posicoes carregado
    public function posicaoNaTela(): ?string
    {
        return $this->posicao_evento_atleta ?? $this->atleta?->posicoes->first()?->posicao_atleta_posicao;
    }

    public function posicaoSugerida(): bool
    {
        return $this->posicao_evento_atleta === null && $this->posicaoNaTela() !== null;
    }

    // Ordem na escalação: pela posição (do goleiro ao ataque, a ordem de Atleta::POSICOES), sem posição no fim
    public function ordemDaPosicao(): int
    {
        $indice = array_search($this->posicaoNaTela(), array_keys(Atleta::POSICOES), true);

        return $indice === false ? 99 : $indice;
    }

    public function getOrigemLabelAttribute(): string
    {
        return self::ORIGENS[$this->origem_evento_atleta] ?? $this->origem_evento_atleta;
    }

    // Dica do selo: o que a origem quer dizer e quem inscreveu, quando
    public function getOrigemDicaAttribute(): string
    {
        return trim((self::DICAS_ORIGEM[$this->origem_evento_atleta] ?? '')
            . ' Inscrito por ' . ($this->usuario?->nome_usuario ?? '—')
            . ($this->data_evento_atleta ? ' em ' . $this->data_evento_atleta->format('d/m/Y H:i') : '') . '.');
    }
}
