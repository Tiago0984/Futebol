<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GradeTreino extends Model
{
    protected $table      = 'tbl_grade_treino';
    protected $primaryKey = 'id_grade_treino';
    public    $timestamps = false;

    public const TIPOS = ['TREINO', 'JOGO', 'LIVRE'];

    protected $fillable = [
        'dia_semana_grade_treino',
        'id_categoria',
        'categoria_grade_treino',
        'tipo_grade_treino',
        'horario_inicio_grade_treino',
        'horario_fim_grade_treino',
        'horario_obs_grade_treino',
        'local_grade_treino',
        'ordem_grade_treino',
        'status_grade_treino',
    ];

    public const DIAS_SEMANA = [
        'segunda_quarta' => 'Segunda/Quarta',
        'terca_quinta'   => 'Terça/Quinta',
        'sexta'          => 'Sexta',
        'sabado'         => 'Sábado',
    ];

    // Dias reais de cada valor do ENUM (Carbon: 0 = domingo ... 6 = sábado). segunda_quarta gera dois eventos por semana.
    public const DIAS_CARBON = [
        'segunda_quarta' => [Carbon::MONDAY, Carbon::WEDNESDAY],
        'terca_quinta'   => [Carbon::TUESDAY, Carbon::THURSDAY],
        'sexta'          => [Carbon::FRIDAY],
        'sabado'         => [Carbon::SATURDAY],
    ];

    /**
     * Ordem de exibição (site e admin): dia da semana, depois horário de início, depois o campo "ordem".
     * Sem horário (ex.: "Jogos", que usa a observação) vai para o fim do dia.
     */
    public function scopeOrdenada($query)
    {
        return $query
            ->orderByRaw("FIELD(dia_semana_grade_treino, 'segunda_quarta', 'terca_quinta', 'sexta', 'sabado')")
            ->orderByRaw('horario_inicio_grade_treino IS NULL')
            ->orderBy('horario_inicio_grade_treino')
            ->orderBy('ordem_grade_treino');
    }

    // Categoria da linha da grade; null nos itens gerais (Integrado, Treino Livre, Jogos)
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Eventos gerados a partir desta linha (Fase 7)
    public function eventos()
    {
        return $this->hasMany(EventoCalendario::class, 'id_grade_treino', 'id_grade_treino');
    }

    // ── Geração de eventos (Fase 7) ─────────────────────────────────────────

    /**
     * Datas do mês em que esta linha acontece, pelo dia da semana. Com $aPartirDe, só datas desse dia
     * em diante (ex.: gerar o mês corrente sem criar treinos que já passaram).
     */
    public function datasNoMes(int $ano, int $mes, ?Carbon $aPartirDe = null): Collection
    {
        $dias   = self::DIAS_CARBON[$this->dia_semana_grade_treino] ?? [];
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $minimo = $aPartirDe?->copy()->startOfDay();

        return collect(range(0, $inicio->daysInMonth - 1))
            ->map(fn (int $i) => $inicio->copy()->addDays($i))
            ->filter(fn (Carbon $data) => in_array($data->dayOfWeek, $dias, true))
            ->filter(fn (Carbon $data) => $minimo === null || $data->gte($minimo))
            ->values();
    }

    /**
     * Motivo para esta linha não gerar eventos, ou null se gera. Jogo nasce na tela de Jogos; sem
     * horário de início não há como montar o treino nem conferir conflito.
     */
    public function motivoQueNaoGera(): ?string
    {
        return match (true) {
            $this->tipo_grade_treino === 'JOGO'                                => 'Jogos são criados na tela de Jogos.',
            strtoupper($this->status_grade_treino) !== 'ATIVO'                 => 'Horário inativo.',
            $this->categoria && $this->categoria->status_categoria !== 'ATIVO' => "A categoria {$this->categoria->rotulo} está inativa.",
            ! $this->horario_inicio_grade_treino                               => 'Sem horário de início.',
            default                                                            => null,
        };
    }

    public function geraEventos(): bool
    {
        return $this->motivoQueNaoGera() === null;
    }

    /**
     * Dados do evento desta linha numa data (mesmas chaves do formulário de evento, para EventoCalendario::criarPor).
     * A origem (id_grade_treino e data_grade_evento_calendario) fica de fora: não é fillable e é gravada à parte.
     */
    public function dadosEventoPara(Carbon $data): array
    {
        // "Treino Sub-13 Masculino", "Treino Integrado"; rótulo que já começa com "Treino" fica como está
        $titulo = Str::startsWith(Str::lower($this->rotulo), 'treino') ? $this->rotulo : "Treino {$this->rotulo}";

        return [
            'titulo_evento_calendario'         => $titulo,
            'tipo_evento_calendario'           => 'TREINO', // LIVRE não existe no ENUM do evento: vira TREINO com subtipo
            'subtipo_evento_calendario'        => $this->tipo_grade_treino === 'LIVRE' ? 'Livre' : null,
            'id_categoria'                     => $this->id_categoria,
            'data_evento_calendario'           => $data->toDateString(),
            'horario_inicio_evento_calendario' => $this->horario_inicio_grade_treino,
            'horario_fim_evento_calendario'    => $this->horario_fim_grade_treino,
            'local_evento_calendario'          => $this->local_grade_treino,
            'status_evento_calendario'         => 'ATIVO',
        ];
    }

    // Texto exibido: "Sub-13 Masculino" quando há categoria; senão, o rótulo livre do item geral
    public function getRotuloAttribute(): string
    {
        return $this->categoria?->rotulo ?? $this->categoria_grade_treino;
    }

    // Cor do item no site: pelo nome da categoria (cat-sub13), igual para M e F
    public function getCatClassAttribute(): string
    {
        $nome = $this->categoria?->nome_categoria ?? $this->categoria_grade_treino;

        return match($this->tipo_grade_treino) {
            'JOGO'  => 'cat-jogo',
            'LIVRE' => 'cat-livre',
            default => 'cat-' . strtolower(str_replace(['-', ' '], '', $nome)),
        };
    }

    // Horário sem segundos, como na lista de eventos: "08:00 às 09:30"; sem início (ex.: "Jogos"), "—"
    public function getHorarioTextoAttribute(): string
    {
        $formatar = fn ($hora) => substr((string) $hora, 0, 5);

        if (! $this->horario_inicio_grade_treino) {
            return '—';
        }

        return $this->horario_fim_grade_treino
            ? $formatar($this->horario_inicio_grade_treino) . ' às ' . $formatar($this->horario_fim_grade_treino)
            : $formatar($this->horario_inicio_grade_treino);
    }

    public function getDiaLabelAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana_grade_treino] ?? $this->dia_semana_grade_treino;
    }
}
