<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Afastamento do atleta (dono do projeto, 16/10/2026): de uma data a outra (as duas obrigatórias; pode prorrogar),
 * o atleta não treina (doença, lesão, viagem) e continua ATIVO, com o app. O motivo fica só no admin (dado de saúde
 * de menor). Sem sobreposição entre os afastamentos do mesmo atleta. As regras do período ficam aqui
 * (errosDoPeriodo); quem registrou (id_usuario) é gravado à parte, fora do $fillable.
 */
class AtletaAfastamento extends Model
{
    protected $table      = 'tbl_atleta_afastamento';
    protected $primaryKey = 'id_atleta_afastamento';
    public    $timestamps = false;

    public const MOTIVO_MAX = 255;

    protected $fillable = [
        'id_atleta',
        'data_inicio_atleta_afastamento',
        'data_fim_atleta_afastamento',
        'motivo_atleta_afastamento',
    ];

    protected $casts = [
        'data_inicio_atleta_afastamento' => 'date',
        'data_fim_atleta_afastamento'    => 'date',
        'data_atleta_afastamento'        => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Ainda vale hoje ou vai valer (o fim é hoje ou depois)
    public function scopeVigentesOuFuturos($query)
    {
        return $query->whereDate('data_fim_atleta_afastamento', '>=', today());
    }

    // Afastamentos que cruzam o período (inclusive as pontas)
    public function scopeNoPeriodo($query, $inicio, $fim)
    {
        return $query->whereDate('data_inicio_atleta_afastamento', '<=', Carbon::parse($fim))
            ->whereDate('data_fim_atleta_afastamento', '>=', Carbon::parse($inicio));
    }

    public function jaComecou(): bool
    {
        return $this->data_inicio_atleta_afastamento->lte(today());
    }

    public function estaVigente(): bool
    {
        return $this->jaComecou() && $this->data_fim_atleta_afastamento->gte(today());
    }

    public function jaTerminou(): bool
    {
        return $this->data_fim_atleta_afastamento->lt(today());
    }

    // "Encerrar" (o atleta volta hoje: o fim vira ontem) só para quem começou antes de hoje e ainda vale;
    // o que começa hoje ou depois é excluído
    public function podeEncerrar(): bool
    {
        return $this->data_inicio_atleta_afastamento->lt(today()) && ! $this->jaTerminou();
    }

    // Selo da lista de atletas: "Afastado até 10/11" (vigente) ou "Afastado de 20/11 a 30/11" (ainda vai começar)
    public function getSeloAttribute(): string
    {
        return $this->jaComecou()
            ? 'Afastado até ' . $this->data_fim_atleta_afastamento->format('d/m')
            : 'Afastado de ' . $this->periodoTexto();
    }

    // "afastado até 10/11" (para as mensagens do admin ao deixar o atleta de fora)
    public function textoAte(): string
    {
        return 'afastado até ' . $this->data_fim_atleta_afastamento->format('d/m');
    }

    // Afastamentos que cobrem a data, um por atleta (não há sobreposição): [id_atleta => AtletaAfastamento]
    public static function naData($data, ?array $idsAtletas = null): array
    {
        return self::noPeriodo($data, $data)
            ->when($idsAtletas !== null, fn ($q) => $q->whereIn('id_atleta', $idsAtletas))
            ->get()->keyBy('id_atleta')->all();
    }

    // Afastamentos que cruzam o período, por atleta (para a geração do mês): [id_atleta => [AtletaAfastamento, ...]]
    public static function porAtletaNoPeriodo($inicio, $fim): array
    {
        return self::noPeriodo($inicio, $fim)->get()->groupBy('id_atleta')->map->all()->all();
    }

    public function cobre($data): bool
    {
        $data = Carbon::parse($data);

        return $data->betweenIncluded($this->data_inicio_atleta_afastamento, $this->data_fim_atleta_afastamento);
    }

    /**
     * Leva o afastamento à agenda (indisponível para tudo; dono do projeto, 16/10/2026), depois de registrar,
     * mudar, encerrar ou excluir. $antes = [início, fim] de antes da mudança (null no registro); $depois = o
     * período que vale agora (null na exclusão).
     *  - sai de todas as atividades (treinos, jogos e outras) ainda não concluídas do período que vale agora,
     *    de hoje em diante (o passado fica como está);
     *  - volta só para os treinos da grade dos dias que deixaram de ser afastamento, de hoje em diante, se
     *    cabe na linha (categoria e turno, como na geração); jogos não voltam sozinhos (o técnico decide pelo
     *    "Preencher pela convocação"): ficam em 'jogos_para_conferir';
     *  - uma notificação AGENDA só, sem o motivo (dado de saúde de menor).
     * Devolve ['saiu' => [títulos], 'voltou' => n, 'jogos_para_conferir' => [títulos], 'notificados' => n].
     */
    public static function aplicarNaAgenda(Atleta $atleta, ?array $antes, ?array $depois, string $acao, ?int $idUsuario): array
    {
        return DB::transaction(function () use ($atleta, $antes, $depois, $acao, $idUsuario) {
            $hoje = today();
            $saiu = [];
            $saiuPublicados = 0;

            if ($depois) {
                $eventos = EventoCalendario::whereHas('inscricoes', fn ($q) => $q->where('id_atleta', $atleta->id_atleta))
                    ->whereBetween('data_evento_calendario', [$depois[0]->copy()->max($hoje)->toDateString(), $depois[1]->toDateString()])
                    ->naoConcluidos()->orderBy('data_evento_calendario')->orderBy('horario_inicio_evento_calendario')->get();

                foreach ($eventos as $evento) {
                    $evento->inscricoes()->where('id_atleta', $atleta->id_atleta)->delete();
                    $saiu[] = $evento->titulo_evento_calendario . ' · ' . $evento->data_evento_calendario->format('d/m');
                    $saiuPublicados += $evento->estaPublicado() ? 1 : 0;
                }
            }

            // Dias que deixaram de ser afastamento (de hoje em diante): fora do período de agora
            $voltou = 0;
            $jogosParaConferir = [];
            if ($antes && $antes[1]->gte($hoje)) {
                $livres = EventoCalendario::with('grade.categoria', 'jogo')
                    ->whereBetween('data_evento_calendario', [$antes[0]->copy()->max($hoje)->toDateString(), $antes[1]->toDateString()])
                    ->whereIn('status_evento_calendario', ['ATIVO', 'CANCELADO'])
                    ->naoConcluidos()->orderBy('data_evento_calendario')->get()
                    ->reject(fn (EventoCalendario $e) => $depois && $e->data_evento_calendario->betweenIncluded($depois[0], $depois[1]))
                    ->reject(fn (EventoCalendario $e) => $e->inscricoes()->where('id_atleta', $atleta->id_atleta)->exists());

                foreach ($livres as $evento) {
                    if ($evento->grade && $evento->grade->aceitaAtleta($atleta)) {
                        $evento->inscricoes()->create([
                            'id_atleta'            => $atleta->id_atleta,
                            'origem_evento_atleta' => $evento->grade->id_categoria ? 'CATEGORIA' : 'INDIVIDUAL',
                            'id_usuario'           => $idUsuario,
                            'data_evento_atleta'   => now(),
                        ]);
                        $voltou++;
                    } elseif ($evento->jogo && array_key_exists((int) $atleta->id_atleta, $evento->jogo->setRelation('evento', $evento)->atletasPorTime())) {
                        $jogosParaConferir[] = $evento->titulo_evento_calendario . ' · ' . $evento->data_evento_calendario->format('d/m');
                    }
                }
            }

            $notificados = Notificacao::afastamento($atleta->id_atleta, $acao, $antes, $depois, $saiuPublicados, $voltou, $idUsuario);

            return ['saiu' => $saiu, 'voltou' => $voltou, 'jogos_para_conferir' => $jogosParaConferir, 'notificados' => $notificados];
        });
    }

    // "03/11 a 10/11" (com o ano quando o período não é todo no ano atual)
    public function periodoTexto(): string
    {
        return self::textoDoPeriodo($this->data_inicio_atleta_afastamento, $this->data_fim_atleta_afastamento);
    }

    public static function textoDoPeriodo(Carbon $inicio, Carbon $fim): string
    {
        $formato = $inicio->year === today()->year && $fim->year === today()->year ? 'd/m' : 'd/m/Y';

        return $inicio->format($formato) . ' a ' . $fim->format($formato);
    }

    // [início, fim] como datas (para aplicarNaAgenda)
    public function periodo(): array
    {
        return [$this->data_inicio_atleta_afastamento->copy(), $this->data_fim_atleta_afastamento->copy()];
    }

    /**
     * Por que o período não pode ser gravado (lista vazia = pode). $atual: o afastamento sendo editado.
     *  - o fim não vem antes do início e é hoje ou depois (afastamento que já acabou não serve para nada; para o
     *    atleta voltar antes, "Encerrar");
     *  - o início pode estar no passado (registrado depois de começar; os treinos passados ficam como estão), mas o
     *    de um afastamento que já começou não muda (só o fim: prorrogar ou encurtar);
     *  - não cruza outro afastamento do mesmo atleta.
     */
    public static function errosDoPeriodo(int $idAtleta, Carbon $inicio, Carbon $fim, ?self $atual = null): array
    {
        $erros = [];

        if ($fim->lt($inicio)) {
            $erros[] = 'A data de fim não pode ser antes da data de início.';
        }
        if ($fim->lt(today())) {
            $erros[] = 'A data de fim precisa ser hoje ou depois. Para o atleta voltar antes, use "Encerrar".';
        }
        if ($atual && $atual->jaComecou() && ! $inicio->isSameDay($atual->data_inicio_atleta_afastamento)) {
            $erros[] = 'Este afastamento já começou: só a data de fim pode mudar.';
        }

        $outro = self::where('id_atleta', $idAtleta)
            ->when($atual, fn ($q) => $q->whereKeyNot($atual->getKey()))
            ->noPeriodo($inicio, $fim)
            ->orderBy('data_inicio_atleta_afastamento')->first();
        if ($outro) {
            $erros[] = "Já existe um afastamento de {$outro->periodoTexto()} que cruza essas datas.";
        }

        return $erros;
    }
}
