<?php

namespace App\Models;

use App\Models\Concerns\SerializaDatasComFuso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Notificação para o atleta (Fase 8): título e mensagem congelados no envio, gravada na mesma
 * transação da ação (sem fila). Lida = data_leitura_notificacao preenchida.
 * SerializaDatasComFuso: datas no JSON da API (Fase 9) como 2026-12-01T09:00:00-03:00.
 */
class Notificacao extends Model
{
    use SerializaDatasComFuso;

    protected $table      = 'tbl_notificacao';
    protected $primaryKey = 'id_notificacao';
    public    $timestamps = false;

    // Valores do ENUM tipo_notificacao (sem acento) => rótulo. AGENDA = resumo de várias atividades
    // (agenda do mês, mover inscrições), sem evento (id_evento_calendario NULL).
    public const TIPOS = [
        'INSCRICAO'    => 'Inscrição',
        'REMOCAO'      => 'Remoção',
        'ALTERACAO'    => 'Alteração',
        'CANCELAMENTO' => 'Cancelamento',
        'REATIVACAO'   => 'Reativação',
        'AGENDA'       => 'Agenda',
    ];

    // data_leitura_notificacao fica de fora: só marcarComoLida() / marcarTodasComoLidas() preenchem
    protected $fillable = [
        'id_atleta',
        'id_evento_calendario',
        'tipo_notificacao',
        'titulo_notificacao',
        'mensagem_notificacao',
        'dados_notificacao',
        'id_usuario',
        'data_notificacao',
    ];

    protected $casts = [
        'dados_notificacao'        => 'array',
        'data_notificacao'         => 'datetime',
        'data_leitura_notificacao' => 'datetime',
    ];

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    // Evento da notificação; null no resumo (AGENDA)
    public function evento()
    {
        return $this->belongsTo(EventoCalendario::class, 'id_evento_calendario', 'id_evento_calendario');
    }

    // Usuário do admin que fez a ação que gerou a notificação
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function scopeNaoLidas($query)
    {
        return $query->whereNull('data_leitura_notificacao');
    }

    public function estaLida(): bool
    {
        return $this->data_leitura_notificacao !== null;
    }

    // Marca como lida; já lida, mantém a data da primeira leitura e devolve false
    public function marcarComoLida(): bool
    {
        if ($this->estaLida()) {
            return false;
        }

        $this->data_leitura_notificacao = now();

        return $this->save();
    }

    // Marca como lidas todas as não lidas do atleta; devolve quantas mudaram
    public static function marcarTodasComoLidas(int $idAtleta): int
    {
        return self::where('id_atleta', $idAtleta)->naoLidas()->update(['data_leitura_notificacao' => now()]);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_notificacao] ?? $this->tipo_notificacao;
    }

    // ── Disparos ────────────────────────────────────────────────────────────
    // Único lugar que monta os textos e grava. Só atletas ATIVO recebem; quem chama já está dentro da
    // transação da ação. Cada método devolve quantos atletas foram notificados.

    // Inscrição num evento (EventoCalendario::inscrever). Evento concluído, cancelado ou oculto não avisa.
    public static function inscricao(EventoCalendario $evento, int $idAtleta, ?int $idUsuario): int
    {
        if (! $evento->avisaAtletas()) {
            return 0;
        }

        return self::gravarParaAtivos([$idAtleta], $idUsuario, fn () => self::linha(
            'INSCRICAO', $evento, 'Nova atividade na sua agenda', self::descreverEvento($evento),
        ));
    }

    // Saída de um evento (remoção pelo admin ou troca de categoria do evento). Mesmas regras da inscrição.
    public static function remocao(EventoCalendario $evento, array $idsAtletas, ?int $idUsuario): int
    {
        if (! $idsAtletas || ! $evento->avisaAtletas()) {
            return 0;
        }

        return self::gravarParaAtivos($idsAtletas, $idUsuario, fn () => self::linha(
            'REMOCAO', $evento, 'Atividade removida da sua agenda',
            'Você não está mais nesta atividade: ' . self::descreverEvento($evento),
        ));
    }

    /**
     * Geração do mês pela grade: uma AGENDA por atleta, com quantos treinos ele ganhou, no lugar de uma
     * INSCRICAO por evento. $treinosPorAtleta: [id_atleta => quantidade].
     */
    public static function agendaDoMes(Carbon $mes, array $treinosPorAtleta, ?int $idUsuario): int
    {
        $nomeMes = $mes->copy()->locale('pt_BR')->isoFormat('MMMM');            // "dezembro"
        $mesAno  = $mes->copy()->locale('pt_BR')->isoFormat('MMMM [de] YYYY');  // "dezembro de 2026"

        return self::gravarParaAtivos(array_keys($treinosPorAtleta), $idUsuario, function (int $idAtleta) use ($treinosPorAtleta, $nomeMes, $mesAno, $mes) {
            $quantos = $treinosPorAtleta[$idAtleta];

            return self::linha('AGENDA', null, "Agenda de {$nomeMes} disponível",
                $quantos === 1 ? "Seu treino de {$mesAno} já está na agenda." : "Seus {$quantos} treinos de {$mesAno} já estão na agenda.",
                ['mes' => $mes->format('Y-m'), 'eventos' => $quantos],
            );
        });
    }

    // "Mover inscrições" do atleta: uma AGENDA com o resumo, no lugar de uma INSCRICAO/REMOCAO por evento
    public static function inscricoesMovidas(int $idAtleta, int $idCategoriaAntiga, int $idCategoriaNova, array $idsEventosSaiu, array $idsEventosEntrou, ?int $idUsuario): int
    {
        if (! $idsEventosSaiu && ! $idsEventosEntrou) {
            return 0;
        }

        $antiga = Categoria::find($idCategoriaAntiga)?->rotulo;
        $nova   = Categoria::find($idCategoriaNova)?->rotulo;
        $atividades = fn (int $n) => $n === 1 ? '1 atividade' : "{$n} atividades";

        $partes = array_filter([
            $idsEventosSaiu ? 'saiu de ' . $atividades(count($idsEventosSaiu)) . " da {$antiga}" : null,
            $idsEventosEntrou ? 'entrou em ' . $atividades(count($idsEventosEntrou)) . " da {$nova}" : null,
        ]);

        return self::gravarParaAtivos([$idAtleta], $idUsuario, fn () => self::linha(
            'AGENDA', null, 'Sua agenda mudou',
            "Sua categoria agora é {$nova}: você " . implode(' e ', $partes) . '.',
            ['de' => $idCategoriaAntiga, 'para' => $idCategoriaNova, 'saiu_de' => array_values($idsEventosSaiu), 'entrou_em' => array_values($idsEventosEntrou)],
        ));
    }

    // "Treino Sub-15 Masculino · ter, 06/10 · 18:00 às 19:30 · Campo A" (sem local, a parte some)
    public static function descreverEvento(EventoCalendario $evento): string
    {
        return implode(' · ', array_filter([
            $evento->titulo_evento_calendario,
            $evento->data_evento_calendario->copy()->locale('pt_BR')->isoFormat('ddd, DD/MM'),
            $evento->horario_inicio_evento_calendario ? $evento->horario_texto : 'horário a definir',
            $evento->local_evento_calendario,
        ], fn ($parte) => filled($parte)));
    }

    // Trecho das mensagens de sucesso do admin: " 5 atleta(s) notificado(s)."
    public static function textoNotificados(int $quantos): string
    {
        return " {$quantos} atleta(s) notificado(s).";
    }

    // Colunas de uma notificação (as mesmas em todas as linhas, para a inserção em massa)
    private static function linha(string $tipo, ?EventoCalendario $evento, string $titulo, string $mensagem, ?array $dados = null): array
    {
        return [
            'tipo_notificacao'     => $tipo,
            'id_evento_calendario' => $evento?->id_evento_calendario,
            'titulo_notificacao'   => $titulo,
            'mensagem_notificacao' => Str::limit($mensagem, 497), // a coluna tem 500
            'dados_notificacao'    => $dados === null ? null : json_encode($dados, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Grava uma notificação para cada atleta ATIVO da lista (inativo, rejeitado ou pendente não recebe),
     * numa inserção só. $linhaPara(id_atleta) devolve as colunas de linha().
     */
    private static function gravarParaAtivos(array $idsAtletas, ?int $idUsuario, callable $linhaPara): int
    {
        $ativos = Atleta::whereIn('id_atleta', array_unique($idsAtletas))
            ->where('status_atleta', 'ATIVO')
            ->orderBy('id_atleta')
            ->pluck('id_atleta')
            ->map(fn ($id) => (int) $id)
            ->all();

        $agora  = now();
        $linhas = array_map(fn (int $idAtleta) => [
            ...$linhaPara($idAtleta),
            'id_atleta'        => $idAtleta,
            'id_usuario'       => $idUsuario,
            'data_notificacao' => $agora,
        ], $ativos);

        foreach (array_chunk($linhas, 500) as $bloco) {
            self::insert($bloco);
        }

        return count($linhas);
    }
}
