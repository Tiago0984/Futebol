<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

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
     */
    private function conflitosDaEdicao(EventoCalendario $evento, array $dados): Collection
    {
        $simulado = $evento->replicate()->fill($dados);
        $simulado->id_evento_calendario = $evento->id_evento_calendario;

        $hora = fn ($valor) => substr((string) $valor, 0, 5);
        $mudouHorario = $evento->data_evento_calendario->toDateString() !== $simulado->data_evento_calendario->toDateString()
            || $hora($evento->horario_inicio_evento_calendario) !== $hora($simulado->horario_inicio_evento_calendario)
            || $hora($evento->horario_fim_evento_calendario) !== $hora($simulado->horario_fim_evento_calendario);
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

    /**
     * Depois de editar: se a categoria mudou (e o evento não está concluído), as inscrições automáticas
     * acompanham e as individuais ficam. Devolve o trecho da mensagem, ou '' se nada mudou.
     */
    private function sincronizarSeMudouCategoria(EventoCalendario $evento, $categoriaAntes): string
    {
        if ((int) $categoriaAntes === (int) $evento->id_categoria || $evento->estaConcluido()) {
            return '';
        }

        ['entraram' => $entraram, 'sairam' => $sairam] = $evento->sincronizarInscricoesPelaCategoria(auth('admin')->id());

        return " Inscrições pela categoria: {$entraram} atleta(s) inscrito(s), {$sairam} removido(s)."
            . ' As inscrições individuais foram mantidas.';
    }
}
