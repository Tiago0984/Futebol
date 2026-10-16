<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Modal "Novo Atleta" da lista de Atletas: o que foi digitado volta só quando o erro foi do próprio cadastro.
 * Depois de um erro na edição, ele abria com os dados do atleta editado (o old() é o mesmo para os dois modais).
 */
class AtletaModalCadastroTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private function atletaAtivo(): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));
        $this->criarResponsavel($id);

        return $id;
    }

    public function test_erro_na_edicao_nao_preenche_o_novo_atleta(): void
    {
        $id = $this->atletaAtivo();
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => 'Nome Editado']);

        $html = $this->comoAdmin()->from(route('admin.atletas.index'))->followingRedirects()
            ->put(route('admin.atletas.update', $id), $this->dadosEdicao($id, [
                '_editar_id' => $id, 'posicoes' => ['VOLANTE', 'VOLANTE'],
            ]))
            ->assertOk()
            ->assertSee('A mesma posição foi escolhida mais de uma vez.')
            ->getContent();

        // O "Novo Atleta" abre vazio (antes, com os dados e as posições do atleta editado)
        $this->assertStringContainsString('placeholder="Ex: Gabriel Silva" required value=""', $html);
        $this->assertMatchesRegularExpression('#id="cad_posicao_1"#', $html);
        $this->assertDoesNotMatchRegularExpression('#id="cad_posicao_1"(?:(?!</select>).)*value="VOLANTE" selected#s', $html);
    }

    public function test_erro_no_cadastro_devolve_o_que_foi_digitado(): void
    {
        $this->comoAdmin()->from(route('admin.atletas.index'))->followingRedirects()
            ->post(route('admin.atletas.store'), $this->dadosCadastro(['nome_atleta' => 'Gabriel Digitado', 'posicoes' => ['VOLANTE', 'VOLANTE']]))
            ->assertOk()
            ->assertSee('placeholder="Ex: Gabriel Silva" required value="Gabriel Digitado"', false);
    }
}
