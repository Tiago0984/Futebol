<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Limpeza dos responsáveis repetidos antes do índice único do e-mail (000010), aprovada pelo dono do
     * projeto (Fase 9). Regras gerais, sem ids fixos, nesta ordem (a fusão primeiro, porque muda quem tem atleta):
     *
     * 1. Mesmo CPF (só dígitos): fica o de menor id; vínculos com atletas, autorizações e leituras de
     *    notificação passam para ele sem repetir o par; sem e-mail, ele herda o do outro; o outro é apagado.
     * 3. Responsável sem atleta vinculado e sem autorização: não é apagado, só fica sem e-mail.
     * 2. E-mail ainda repetido (minúsculas, sem espaços, como na 000010): fica no de menor id; os demais ficam
     *    sem e-mail.
     *
     * O endereço do responsável apagado fica em tbl_endereco (débito "Responsável e endereço", seção 7).
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->fundirPorCpf();
            $this->tirarEmailDeQuemNaoTemAtleta();
            $this->tirarEmailRepetido();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Sem volta: os responsáveis apagados e os e-mails retirados não ficam guardados (o backup tem o estado anterior).
     */
    public function down(): void
    {
        //
    }

    // Regra 1
    private function fundirPorCpf(): void
    {
        $grupos = DB::table('tbl_responsavel')->orderBy('id_responsavel')
            ->get(['id_responsavel', 'cpf_responsavel', 'email_responsavel'])
            ->groupBy(fn ($r) => preg_replace('/\D/', '', (string) $r->cpf_responsavel))
            ->filter(fn ($grupo, $digitos) => $digitos !== '' && $grupo->count() > 1);

        foreach ($grupos as $grupo) {
            $fica = $grupo->first();
            $saem = $grupo->slice(1);
            $idsQueSaem = $saem->pluck('id_responsavel')->all();

            $this->moverSemRepetir('tbl_atleta_responsavel', 'id_atleta_responsavel', 'id_atleta', $fica->id_responsavel, $idsQueSaem);
            $this->moverSemRepetir('tbl_autorizacoes', 'id_autorizacao', 'id_atleta', $fica->id_responsavel, $idsQueSaem);
            $this->moverSemRepetir('tbl_notificacao_leitura', 'id_notificacao_leitura', 'id_notificacao', $fica->id_responsavel, $idsQueSaem);

            DB::table('tbl_responsavel')->whereIn('id_responsavel', $idsQueSaem)->delete();

            // Herda o e-mail só depois de apagar os outros: com o índice único, o mesmo e-mail não fica em dois
            if ($this->vazio($fica->email_responsavel)) {
                $email = $saem->first(fn ($r) => ! $this->vazio($r->email_responsavel))?->email_responsavel;
                if ($email !== null) {
                    DB::table('tbl_responsavel')->where('id_responsavel', $fica->id_responsavel)
                        ->update(['email_responsavel' => $email]);
                }
            }
        }
    }

    /**
     * Passa as linhas dos responsáveis que saem para o que fica, sem repetir o par (outra coluna, responsável):
     * em cada par vale a linha do que fica (ou a de menor id); nas autorizações, a assinada vale mais que a
     * pendente. As outras são apagadas ANTES de mover, porque tbl_notificacao_leitura tem único no par.
     */
    private function moverSemRepetir(string $tabela, string $pk, string $par, int $idFica, array $idsQueSaem): void
    {
        $linhas = DB::table($tabela)->whereIn('id_responsavel', [$idFica, ...$idsQueSaem])
            ->orderByRaw('id_responsavel = ? DESC', [$idFica])->orderBy($pk)->get();

        foreach ($linhas->groupBy($par) as $mesmoPar) {
            $vale = $tabela === 'tbl_autorizacoes'
                ? ($mesmoPar->firstWhere('status_autorizacao', 'ASSINADO') ?? $mesmoPar->first())
                : $mesmoPar->first();

            DB::table($tabela)->whereIn($pk, $mesmoPar->pluck($pk)->reject(fn ($id) => $id === $vale->{$pk})->all())->delete();
            DB::table($tabela)->where($pk, $vale->{$pk})->update(['id_responsavel' => $idFica]);
        }
    }

    // Regra 3
    private function tirarEmailDeQuemNaoTemAtleta(): void
    {
        DB::table('tbl_responsavel as r')
            ->whereNotNull('r.email_responsavel')
            ->whereNotExists(fn ($q) => $q->from('tbl_atleta_responsavel as ar')->whereColumn('ar.id_responsavel', 'r.id_responsavel'))
            ->whereNotExists(fn ($q) => $q->from('tbl_autorizacoes as a')->whereColumn('a.id_responsavel', 'r.id_responsavel'))
            ->update(['r.email_responsavel' => null]);
    }

    // Regra 2
    private function tirarEmailRepetido(): void
    {
        $donos = [];

        foreach (DB::table('tbl_responsavel')->orderBy('id_responsavel')->get(['id_responsavel', 'email_responsavel']) as $r) {
            if ($this->vazio($r->email_responsavel)) {
                continue;
            }

            $email = mb_strtolower(trim($r->email_responsavel));
            if (isset($donos[$email])) {
                DB::table('tbl_responsavel')->where('id_responsavel', $r->id_responsavel)->update(['email_responsavel' => null]);
            } else {
                $donos[$email] = $r->id_responsavel;
            }
        }
    }

    private function vazio(?string $email): bool
    {
        return trim((string) $email) === '';
    }
};
