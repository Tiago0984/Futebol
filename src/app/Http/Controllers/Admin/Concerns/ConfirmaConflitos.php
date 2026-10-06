<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alerta de conflito de horário, usado pelo Calendário e pela tela de Jogos.
 * A tela precisa incluir o partial admin.calendario._conflitos.
 */
trait ConfirmaConflitos
{
    /**
     * Alerta de conflito. Conflito real (horários sobrepostos) pede confirmação: volta para a tela com a
     * lista e um formulário que reenvia os mesmos dados com confirmar_conflito=1. Aviso fraco (algum
     * evento sem horário de início) não bloqueia: segue e mostra um aviso informativo na próxima tela.
     * Com os dois tipos, pede confirmação e lista os dois, separados. Devolve null quando pode seguir.
     */
    private function confirmarConflitos(Request $request, Collection $conflitos): ?RedirectResponse
    {
        [$fracos, $fortes] = $conflitos->partition(fn ($c) => $c['fraco']);
        $descrever = fn (Collection $lista) => $lista->map(fn ($c) => EventoCalendario::descreverConflito($c))->values()->all();

        if ($fortes->isEmpty() || $request->boolean('confirmar_conflito')) {
            if ($fortes->isEmpty() && $fracos->isNotEmpty()) {
                session()->flash('avisos_mesmo_dia', $descrever($fracos));
            }

            return null;
        }

        return back()->withInput()->with('conflitos_pendentes', [
            'url'    => $request->url(),
            'metodo' => $request->method(), // PUT na edição (o _method é refeito no formulário)
            'dados'  => Arr::except($request->all(), ['_token', '_method', 'confirmar_conflito']),
            'fortes' => $descrever($fortes),
            'fracos' => $descrever($fracos),
        ]);
    }

    /**
     * Conflitos que a edição criaria: só quando muda data, horário ou categoria. Confere os atletas que
     * ficarão inscritos (com troca de categoria: individuais atuais + atletas ativos da categoria nova).
     * Jogo ($timesDoJogo = mandante e visitante novos): a categoria não muda quem joga; a troca de time,
     * sim (sai o elenco do time que saiu, entra o do novo; Jogo::idsInscritosDepoisDaTroca).
     */
    private function conflitosDaEdicao(EventoCalendario $evento, array $dados, ?array $timesDoJogo = null): Collection
    {
        $simulado = $evento->replicate()->fill($dados);
        $simulado->id_evento_calendario = $evento->id_evento_calendario;

        $hora = fn ($valor) => substr((string) $valor, 0, 5);
        $mudouHorario = $evento->data_evento_calendario->toDateString() !== $simulado->data_evento_calendario->toDateString()
            || $hora($evento->horario_inicio_evento_calendario) !== $hora($simulado->horario_inicio_evento_calendario)
            || $hora($evento->horario_fim_evento_calendario) !== $hora($simulado->horario_fim_evento_calendario);

        // Jogo editado pela tela do calendário: os times continuam os mesmos
        if ($timesDoJogo === null && $evento->ehJogo()) {
            $timesDoJogo = [$evento->jogo->id_time_casa, $evento->jogo->id_time_visitante];
        }

        if ($timesDoJogo !== null) {
            $jogo = $evento->jogo;
            $mudouTimes = $this->timesMudaram([$jogo->id_time_casa, $jogo->id_time_visitante], $timesDoJogo) && ! $simulado->estaConcluido();

            if (! $mudouHorario && ! $mudouTimes) {
                return collect();
            }

            return $simulado->conflitosPara($mudouTimes
                ? $jogo->idsInscritosDepoisDaTroca($timesDoJogo)
                : $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        }

        $mudouCategoria = (int) $evento->id_categoria !== (int) $simulado->id_categoria && ! $simulado->estaConcluido();

        if (! $mudouHorario && ! $mudouCategoria) {
            return collect();
        }

        $inscricoes = $evento->inscricoes()->get(['id_atleta', 'origem_evento_atleta']);
        $ids = $inscricoes->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();

        if ($mudouCategoria) {
            $individuais = $inscricoes->where('origem_evento_atleta', 'INDIVIDUAL')->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
            $daNova      = $simulado->id_categoria ? Atleta::idsAtivosNaCategoria((int) $simulado->id_categoria) : [];
            $ids         = array_values(array_unique([...$individuais, ...$daNova]));
        }

        return $simulado->conflitosPara($ids);
    }

    // Evento novo com categoria: os atletas ativos dela serão inscritos; confere conflito antes de criar
    private function conflitosDaCriacao(array $dados): Collection
    {
        if (empty($dados['id_categoria'])) {
            return collect();
        }

        return (new EventoCalendario($dados))->conflitosPara(Atleta::idsAtivosNaCategoria((int) $dados['id_categoria']));
    }

    // Jogo novo: o elenco ativo dos times internos será inscrito; confere conflito antes de criar
    private function conflitosDaCriacaoDoJogo(array $dados, array $idsTimes): Collection
    {
        return (new EventoCalendario($dados))->conflitosPara(array_keys(Jogo::elencoDosTimes(Jogo::idsInternos($idsTimes))));
    }

    // Mandante e visitante mudaram (em qualquer ordem: inverter o mando não troca ninguém)
    private function timesMudaram(array $antes, array $depois): bool
    {
        $normalizar = fn (array $times) => collect($times)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $normalizar($antes) !== $normalizar($depois);
    }

    /**
     * Grava a edição do evento (com histórico) e o que vem junto, numa transação:
     *  - $tambem (tela de Jogos): grava o jogo e, se os times mudaram, sincroniza pelo elenco; devolve
     *    ['mensagem' => ..., 'ids_entraram' => [...], 'mexeu' => bool] ou null;
     *  - categoria mudou (evento comum, não concluído): as inscrições automáticas acompanham e as
     *    individuais ficam. No jogo, a categoria é só exibição e não mexe nas inscrições;
     *  - quem entra recebe só a INSCRICAO (já com os dados novos), quem sai só a REMOCAO;
     *  - data, horário ou local mudaram: quem ficou inscrito recebe a ALTERACAO (depois das sincronizações,
     *    para ninguém receber duas).
     * Devolve o trecho da mensagem de sucesso ('' se nada disso mudou).
     */
    private function salvarEdicaoDoEvento(EventoCalendario $evento, array $dados, ?callable $tambem = null): string
    {
        $idUsuario = auth('admin')->id();

        return DB::transaction(function () use ($evento, $dados, $tambem, $idUsuario) {
            $categoriaAntes = $evento->id_categoria;
            $mudancas = $evento->atualizarComHistorico($dados, $idUsuario);

            $doJogo = $tambem ? $tambem() : null;

            $mensagem = $doJogo['mensagem'] ?? '';
            $mexeuNasInscricoes = $doJogo['mexeu'] ?? false;
            $entraram = $doJogo['ids_entraram'] ?? [];
            if ((int) $categoriaAntes !== (int) $evento->id_categoria && ! $evento->estaConcluido() && ! $evento->ehJogo()) {
                $sincronizacao = $evento->sincronizarInscricoesPelaCategoria($idUsuario);
                $entraram = $sincronizacao['ids_entraram'];
                $mexeuNasInscricoes = $sincronizacao['entraram'] + $sincronizacao['sairam'] > 0;
                $mensagem = " Inscrições pela categoria: {$sincronizacao['entraram']} atleta(s) inscrito(s), {$sincronizacao['sairam']} removido(s)."
                    . ' As inscrições individuais foram mantidas.';
            }

            $evento->atletasNotificados += Notificacao::alteracao($evento, $mudancas, $idUsuario, $entraram);
            $mudouDataHorarioOuLocal = array_intersect_key($mudancas, array_flip(EventoCalendario::CAMPOS_ALTERADO)) !== [];

            return $mensagem . ($mexeuNasInscricoes || $mudouDataHorarioOuLocal ? Notificacao::textoNotificados($evento->atletasNotificados) : '');
        });
    }
}
