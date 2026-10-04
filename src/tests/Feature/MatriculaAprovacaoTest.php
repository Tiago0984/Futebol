<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class MatriculaAprovacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- servidor: aprovação exige autorização assinada e idade de 9 a 17 ----------

    public function test_aprovar_sem_nenhuma_autorizacao_e_recusado(): void
    {
        $idAtleta = $this->pendente(12);

        $this->aprovar($idAtleta, 'Sub-13')
            ->assertSessionHas('erro', 'Sem autorização do responsável: reative a matrícula para gerar o link de assinatura.');

        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
        $this->assertDatabaseMissing('tbl_categoria_atleta', ['id_atleta' => $idAtleta]);
    }

    public function test_aprovar_com_autorizacao_pendente_e_recusado(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'PENDENTE');

        $this->aprovar($idAtleta, 'Sub-13')
            ->assertSessionHas('erro', 'Aguardando a assinatura da autorização pelo responsável.');

        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
    }

    public function test_aprovar_com_autorizacao_assinada_funciona(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');

        $this->aprovar($idAtleta, 'Sub-13')->assertSessionHasNoErrors()->assertSessionMissing('erro');

        $this->assertSame('ATIVO', $this->statusDo($idAtleta));
    }

    public function test_assinada_antiga_vale_mesmo_com_outra_pendente(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');
        $this->criarAutorizacao($idAtleta, 'PENDENTE');

        $this->aprovar($idAtleta, 'Sub-13')->assertSessionMissing('erro');

        $this->assertSame('ATIVO', $this->statusDo($idAtleta));
    }

    public function test_aprovar_com_8_anos_no_ano_e_recusado_mesmo_com_motivo(): void
    {
        $idAtleta = $this->pendente(8);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');
        $ano = now()->year;

        // Sub-9 fica "acima da idade" para quem tem 8; com motivo passaria pela regra de categoria
        $this->aprovar($idAtleta, 'Sub-9', 'Motivo qualquer')
            ->assertSessionHas('erro', "O atleta deve ter de 9 a 17 anos em {$ano} (nascido entre " . ($ano - 17) . ' e ' . ($ano - 9) . ').');

        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
    }

    public function test_aprovar_com_18_anos_no_ano_e_recusado(): void
    {
        $idAtleta = $this->pendente(18);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');

        $this->aprovar($idAtleta, 'Sub-17')->assertSessionHas('erro');

        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
    }

    // ---------- tela: selo e botão Aprovar ----------

    public function test_lista_sem_autorizacao_mostra_selo_e_desabilita_aprovar(): void
    {
        $this->pendente(12);

        $this->comoAdmin()->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertSee('Sem autorização</span>', false)
            ->assertSee('disabled title="Sem autorização do responsável: reative a matrícula para gerar o link de assinatura."', false);
    }

    public function test_lista_com_autorizacao_pendente_mostra_pendente_e_desabilita_aprovar(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'PENDENTE');

        $this->comoAdmin()->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertSee('Pendente</span>', false)
            ->assertDontSee('Sem autorização</span>', false) // o filtro tem a opção com o mesmo texto
            ->assertSee('disabled title="Aguardando a assinatura da autorização pelo responsável."', false);
    }

    public function test_lista_com_autorizacao_assinada_habilita_aprovar(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');

        $this->comoAdmin()->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertSee('Assinada</span>', false)
            ->assertDontSee('disabled title=', false);
    }

    public function test_lista_fora_da_idade_nao_tem_aprovar_habilitado(): void
    {
        // Sem categoria sugerida (8 anos): antes virava um link "Aprovar" sempre habilitado
        $idAtleta = $this->pendente(8);
        $this->criarAutorizacao($idAtleta, 'ASSINADO');

        $this->comoAdmin()->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertSee('disabled title="O atleta deve ter de 9 a 17 anos', false)
            ->assertDontSee('title="Sem categoria sugerida', false);
    }

    public function test_detalhes_sem_autorizacao_mostra_selo_e_desabilita_aprovar(): void
    {
        $idAtleta = $this->pendente(12);

        $this->comoAdmin()->get(route('admin.matriculas.show', $idAtleta))
            ->assertOk()
            ->assertSee('Sem autorização')
            ->assertSee('disabled title="Sem autorização do responsável: reative a matrícula para gerar o link de assinatura."', false)
            ->assertSee('Aprovação bloqueada: Sem autorização do responsável', false);
    }

    public function test_detalhes_com_autorizacao_pendente_mostra_o_link(): void
    {
        $idAtleta = $this->pendente(12);
        $this->criarAutorizacao($idAtleta, 'PENDENTE', 'token-do-link');

        $this->comoAdmin()->get(route('admin.matriculas.show', $idAtleta))
            ->assertOk()
            ->assertSee('Autorização Pendente')
            ->assertSee(route('assinar.show', 'token-do-link'))
            ->assertSee('disabled title="Aguardando a assinatura da autorização pelo responsável."', false);
    }

    // ---------- reativar gera autorização pendente com token ----------

    public function test_reativar_sem_autorizacao_gera_autorizacao_pendente_com_token(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');
        $idResponsavel = $this->criarResponsavel($idAtleta);

        $this->comoAdmin()->patch(route('admin.matriculas.reativar', $idAtleta))
            ->assertSessionHas('sucesso');

        $autorizacao = DB::table('tbl_autorizacoes')->where('id_atleta', $idAtleta)->first();
        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
        $this->assertSame('PENDENTE', $autorizacao->status_autorizacao);
        $this->assertSame($idResponsavel, (int) $autorizacao->id_responsavel);
        $this->assertSame(60, strlen($autorizacao->token_assinatura));

        // O link aparece em "Ver"
        $this->comoAdmin()->get(route('admin.matriculas.show', $idAtleta))
            ->assertSee(route('assinar.show', $autorizacao->token_assinatura));
    }

    public function test_reativar_com_autorizacao_pendente_sem_token_gera_o_token(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');
        $idAutorizacao = $this->criarAutorizacao($idAtleta, 'PENDENTE');
        DB::table('tbl_autorizacoes')->where('id_autorizacao', $idAutorizacao)->update(['token_assinatura' => null]);

        $this->comoAdmin()->patch(route('admin.matriculas.reativar', $idAtleta));

        $this->assertSame(1, DB::table('tbl_autorizacoes')->where('id_atleta', $idAtleta)->count());
        $this->assertNotNull(DB::table('tbl_autorizacoes')->where('id_autorizacao', $idAutorizacao)->value('token_assinatura'));
    }

    public function test_reativar_com_autorizacao_ja_existente_nao_cria_outra(): void
    {
        foreach (['ASSINADO', 'PENDENTE'] as $status) {
            $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');
            $this->criarAutorizacao($idAtleta, $status, "token-{$status}");

            $this->comoAdmin()->patch(route('admin.matriculas.reativar', $idAtleta));

            $this->assertSame(1, DB::table('tbl_autorizacoes')->where('id_atleta', $idAtleta)->count());
            $this->assertSame("token-{$status}", DB::table('tbl_autorizacoes')->where('id_atleta', $idAtleta)->value('token_assinatura'));
        }
    }

    public function test_reativar_sem_responsavel_avisa_e_nao_cria_autorizacao(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'REJEITADO');

        $this->comoAdmin()->patch(route('admin.matriculas.reativar', $idAtleta))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, 'não tem responsável cadastrado'));

        $this->assertSame('PENDENTE', $this->statusDo($idAtleta));
        $this->assertDatabaseMissing('tbl_autorizacoes', ['id_atleta' => $idAtleta]);
    }

    // ---------- foto ----------

    public function test_foto_segue_a_pasta_de_cada_cadastro(): void
    {
        $padrao = $this->pendente(12); // cadastro pelo admin sem foto grava 'default-player.jpg'
        $site   = $this->pendente(12);
        $admin  = $this->pendente(12);
        DB::table('tbl_atletas')->where('id_atleta', $padrao)->update(['foto_atleta' => 'default-player.jpg', 'nome_atleta' => 'Padrão Zeta']);
        DB::table('tbl_atletas')->where('id_atleta', $site)->update(['foto_atleta' => 'atletas/foto-site.jpg']);
        DB::table('tbl_atletas')->where('id_atleta', $admin)->update(['foto_atleta' => 'atleta_99.jpg']);

        $this->comoAdmin()->get(route('admin.matriculas.index'))
            ->assertOk()
            ->assertDontSee('storage/default-player.jpg')
            ->assertSee('PZ')                                            // iniciais no lugar da foto padrão
            ->assertSee(asset('storage/atletas/foto-site.jpg'))          // cadastro do site: disco public
            ->assertSee(asset('futebol/images/our-teams/atleta_99.jpg')); // cadastro do admin: pasta pública
    }

    // ---------- ajudantes ----------

    private function pendente(int $idade): int
    {
        return $this->criarAtleta($this->nascidoComIdade($idade), 'M', 'PENDENTE');
    }

    private function aprovar(int $idAtleta, string $categoria, ?string $motivo = null)
    {
        return $this->comoAdmin()->patch(route('admin.matriculas.aprovar', $idAtleta), array_filter([
            'id_categoria'     => $this->idCategoria($categoria, 'M'),
            'motivo_categoria' => $motivo,
        ]));
    }

    private function statusDo(int $idAtleta): string
    {
        return DB::table('tbl_atletas')->where('id_atleta', $idAtleta)->value('status_atleta');
    }
}
