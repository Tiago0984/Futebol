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

    // Tamanho máximo da descrição do evento na mensagem de inscrição (o texto inteiro fica na agenda)
    public const RECADO_MAX = 120;

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

    // Leituras pelos responsáveis do atleta (a do atleta é data_leitura_notificacao)
    public function leiturasDosResponsaveis()
    {
        return $this->hasMany(NotificacaoLeitura::class, 'id_notificacao', 'id_notificacao');
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
            'INSCRICAO', $evento, 'Nova atividade na sua agenda', self::descreverEvento($evento) . self::recado($evento),
        ));
    }

    // Descrição do evento ("o que o atleta vai fazer") resumida no fim da inscrição: " — Venha em jejum".
    // O texto inteiro fica na agenda (campo descricao da API)
    private static function recado(EventoCalendario $evento): string
    {
        $descricao = trim(preg_replace('/\s+/', ' ', (string) $evento->descricao_evento_calendario));

        return $descricao === '' ? '' : ' — ' . Str::limit($descricao, self::RECADO_MAX, '…');
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
     * Edição que mudou data, horário ou local (EventoCalendario::CAMPOS_ALTERADO): uma ALTERACAO por inscrito,
     * listando só os campos que mudaram. $mudancas: o que atualizarComHistorico() devolve. $exceto: quem
     * acabou de entrar pela categoria nova (já recebeu a INSCRICAO com os dados novos). Mudar só título, tipo
     * ou descrição não avisa; o evento, depois da edição, precisa estar ATIVO e não concluído.
     */
    public static function alteracao(EventoCalendario $evento, array $mudancas, ?int $idUsuario, array $exceto = []): int
    {
        $mudancas = array_intersect_key($mudancas, array_flip(EventoCalendario::CAMPOS_ALTERADO));

        if (! $mudancas || ! $evento->avisaAtletas()) {
            return 0;
        }

        // "data 06/10 → 07/10; local Campo 1 → Campo 2"
        $oQueMudou = collect($mudancas)
            ->map(fn (array $valores, string $campo) => mb_strtolower(EventoCalendario::CAMPOS_HISTORICO[$campo]) . ' '
                . self::valorAlterado($campo, $valores[0]) . ' → ' . self::valorAlterado($campo, $valores[1]))
            ->implode('; ');

        return self::gravarParaAtivos(array_diff(self::idsInscritos($evento), $exceto), $idUsuario, fn () => self::linha(
            'ALTERACAO', $evento, 'Atividade alterada',
            self::descreverEvento($evento) . ". Mudanças: {$oQueMudou}.",
            ['campos' => $mudancas],
        ));
    }

    /**
     * Mudança de status (EventoCalendario::mudarStatus): sair de ATIVO avisa CANCELAMENTO (cancelar, ou ocultar
     * um evento ativo); voltar a ATIVO avisa REATIVACAO (reativar, ou mostrar de volta como ativo). Entre
     * cancelado e oculto não avisa. Evento concluído ou em rascunho não avisa. Não usa o avisaAtletas(): ele
     * exige o evento ATIVO, e o cancelamento avisa justamente quando o evento deixa de estar.
     */
    public static function mudancaDeStatus(EventoCalendario $evento, string $statusAntes, ?int $idUsuario): int
    {
        $statusAgora = $evento->status_evento_calendario;
        $tipo = match (true) {
            $statusAntes === 'ATIVO' && $statusAgora !== 'ATIVO' => 'CANCELAMENTO',
            $statusAntes !== 'ATIVO' && $statusAgora === 'ATIVO' => 'REATIVACAO',
            default                                              => null,
        };

        if ($tipo === null || ! $evento->estaPublicado() || $evento->estaConcluido()) {
            return 0;
        }

        [$titulo, $mensagem] = $tipo === 'CANCELAMENTO'
            ? ['Atividade cancelada', 'Esta atividade foi cancelada: ']
            : ['Atividade confirmada de novo', 'Esta atividade voltou para a sua agenda: '];

        return self::gravarParaAtivos(self::idsInscritos($evento), $idUsuario, fn () => self::linha(
            $tipo, $evento, $titulo, $mensagem . self::descreverEvento($evento),
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

    // Valor do histórico como o atleta lê: data "07/10", horário "18:00", vazio "a definir"
    private static function valorAlterado(string $campo, ?string $valor): string
    {
        return match (true) {
            $valor === null                     => 'a definir',
            $campo === 'data_evento_calendario' => Carbon::parse($valor)->format('d/m'),
            default                             => $valor,
        };
    }

    // Todos os inscritos do evento (gravarParaAtivos deixa só os ATIVO)
    private static function idsInscritos(EventoCalendario $evento): array
    {
        return $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
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
