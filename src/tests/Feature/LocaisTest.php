<?php

namespace Tests\Feature;

use App\Models\Local;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Cadastro de locais (Etapa L1, 30/10/2026): criar, editar, ativar e desativar, sem exclusão; nome único; endereço
 * com CEP e complemento opcionais; link só do Google Maps; endereço numa linha e o link do mapa (o cadastrado ou a
 * busca); e os textos de local já usados que ainda não têm cadastro.
 */
class LocaisTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const LOCAL = [
        'nome_local' => '  Campo AACJ ', 'rua_local' => 'Rua das Flores', 'numero_local' => '120', 'complemento_local' => 'Portão 2',
        'bairro_local' => 'Centro', 'cidade_local' => 'São Carlos', 'estado_local' => 'sp', 'cep_local' => '13560000',
        'mapa_url_local' => 'https://maps.app.goo.gl/AbCdEf123',
    ];

    public function test_cria_normaliza_e_lista_com_o_endereco_e_o_mapa(): void
    {
        $this->comoAdmin()->post(route('admin.locais.store'), self::LOCAL)
            ->assertRedirect(route('admin.locais.index'))
            ->assertSessionHas('sucesso', 'Local Campo AACJ cadastrado.');

        $local = Local::sole();
        $this->assertSame('Campo AACJ', $local->nome_local);
        $this->assertSame('SP', $local->estado_local);
        $this->assertSame('13560-000', $local->cep_local);
        $this->assertSame('ATIVO', $local->status_local);
        $this->assertSame('Rua das Flores, 120, Portão 2 – Centro, São Carlos/SP, 13560-000', $local->enderecoEmUmaLinha());
        $this->assertSame('https://maps.app.goo.gl/AbCdEf123', $local->mapaUrl());

        $this->get(route('admin.locais.index'))
            ->assertOk()
            ->assertSee('Campo AACJ')
            ->assertSee('Rua das Flores, 120, Portão 2 – Centro, São Carlos/SP, 13560-000')
            ->assertSee('https://maps.app.goo.gl/AbCdEf123', false);
    }

    public function test_sem_cep_complemento_e_link_o_mapa_e_a_busca_pelo_nome_e_endereco(): void
    {
        $this->comoAdmin()->post(route('admin.locais.store'), [
            ...self::LOCAL, 'nome_local' => 'Arena Norte', 'numero_local' => 'S/N', 'complemento_local' => '', 'cep_local' => '', 'mapa_url_local' => '',
        ])->assertSessionHas('sucesso');

        $local = Local::sole();
        $this->assertNull($local->cep_local);
        $this->assertNull($local->complemento_local);
        $this->assertNull($local->mapa_url_local);
        $this->assertSame('Rua das Flores, S/N – Centro, São Carlos/SP', $local->enderecoEmUmaLinha());
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Arena Norte, Rua das Flores, S/N – Centro, São Carlos/SP'),
            $local->mapaUrl()
        );
    }

    public function test_obrigatorios_uf_cep_e_nome_repetido_sem_diferenciar_maiusculas_e_acentos(): void
    {
        $this->comoAdmin()->post(route('admin.locais.store'), [])
            ->assertSessionHasErrors([
                'nome_local' => 'Informe o nome do local.', 'rua_local' => 'Informe a rua.',
                'numero_local' => 'Informe o número (ou S/N, sem número).', 'bairro_local' => 'Informe o bairro.',
                'cidade_local' => 'Informe a cidade.', 'estado_local' => 'Informe a UF.',
            ]);

        $this->post(route('admin.locais.store'), [...self::LOCAL, 'estado_local' => 'São Paulo', 'cep_local' => '1356-000'])
            ->assertSessionHasErrors([
                'estado_local' => 'Informe a UF com 2 letras (ex.: SP).',
                'cep_local' => 'Informe um CEP com 8 dígitos (ex.: 13560-000).',
            ]);

        $this->post(route('admin.locais.store'), self::LOCAL)->assertSessionHas('sucesso');
        $this->post(route('admin.locais.store'), [...self::LOCAL, 'nome_local' => 'CAMPO aacj'])
            ->assertSessionHasErrors(['nome_local' => 'Já existe um local com este nome.']);
        $this->assertSame(1, Local::count());
    }

    public function test_link_do_mapa_so_do_google_maps(): void
    {
        foreach ([
            'https://maps.app.goo.gl/AbCdEf123', 'https://goo.gl/maps/AbCdEf', 'https://www.google.com/maps/place/Campo+AACJ/@-23.5,-46.6,17z',
            'https://google.com.br/maps?q=Campo+AACJ', 'https://maps.google.com/?q=-23.5,-46.6',
        ] as $aceito) {
            $this->assertTrue(Local::linkDoGoogleMaps($aceito), $aceito);
        }

        foreach ([
            'http://maps.app.goo.gl/AbCdEf123', 'https://maps.app.goo.gl/', 'https://goo.gl/AbCdEf', 'https://www.google.com/search?q=campo',
            'https://exemplo.com/maps', 'https://maps.app.goo.gl.exemplo.com/x', 'https://usuario@maps.app.goo.gl/x', 'javascript:alert(1)', 'Campo AACJ',
        ] as $recusado) {
            $this->assertFalse(Local::linkDoGoogleMaps($recusado), $recusado);
        }

        $this->comoAdmin()->post(route('admin.locais.store'), [...self::LOCAL, 'mapa_url_local' => 'https://exemplo.com/maps'])
            ->assertSessionHasErrors(['mapa_url_local' => 'Cole um link do Google Maps (ex.: https://maps.app.goo.gl/…).']);
        $this->assertSame(0, Local::count());
    }

    public function test_edita_mantendo_o_proprio_nome_e_desativa_e_reativa_sem_exclusao(): void
    {
        $this->comoAdmin()->post(route('admin.locais.store'), self::LOCAL);
        $local = Local::sole();

        $this->get(route('admin.locais.edit', $local->id_local))->assertOk()->assertSee('Rua das Flores');
        $this->put(route('admin.locais.update', $local->id_local), [...self::LOCAL, 'rua_local' => 'Rua Nova', 'mapa_url_local' => ''])
            ->assertRedirect(route('admin.locais.index'))
            ->assertSessionHas('sucesso', 'Dados do local Campo AACJ atualizados.');
        $this->assertSame('Rua Nova', $local->fresh()->rua_local);
        $this->assertNull($local->fresh()->mapa_url_local);

        $this->patch(route('admin.locais.toggleStatus', $local->id_local))
            ->assertSessionHas('sucesso', 'O local Campo AACJ foi desativado: não aparece mais para novas escolhas.');
        $this->assertFalse($local->fresh()->estaAtivo());
        $this->assertSame(0, Local::ativos()->count());

        $this->patch(route('admin.locais.toggleStatus', $local->id_local))->assertSessionHas('sucesso', 'O local Campo AACJ foi reativado.');
        $this->assertTrue($local->fresh()->estaAtivo());

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.locais.destroy'));
    }

    public function test_lista_os_locais_usados_que_ainda_nao_estao_cadastrados(): void
    {
        DB::table('tbl_grade_treino')->insert([
            ['categoria_grade_treino' => 'Treino A', 'dia_semana_grade_treino' => 'terca', 'tipo_grade_treino' => 'TREINO', 'local_grade_treino' => 'Quadra A', 'status_grade_treino' => 'ATIVO'],
            ['categoria_grade_treino' => 'Treino B', 'dia_semana_grade_treino' => 'quinta', 'tipo_grade_treino' => 'TREINO', 'local_grade_treino' => ' Quadra A ', 'status_grade_treino' => 'ATIVO'],
            ['categoria_grade_treino' => 'Treino C', 'dia_semana_grade_treino' => 'sabado', 'tipo_grade_treino' => 'TREINO', 'local_grade_treino' => 'CAMPO AACJ', 'status_grade_treino' => 'ATIVO'],
        ]);

        $this->assertEqualsCanonicalizing(['Quadra A' => 2, 'CAMPO AACJ' => 1],
            Local::textosSemCadastro()->mapWithKeys(fn ($u) => [$u->texto => $u->usos])->all());

        // "Campo AACJ" cadastrado sai da lista (mesmo nome, outra caixa)
        $this->comoAdmin()->post(route('admin.locais.store'), self::LOCAL);
        $this->get(route('admin.locais.index'))
            ->assertOk()
            ->assertSee('Locais usados que ainda não estão cadastrados')
            ->assertSee(route('admin.locais.create', ['nome' => 'Quadra A']), false)
            ->assertDontSee(route('admin.locais.create', ['nome' => 'CAMPO AACJ']), false);

        // "Cadastrar" abre o formulário com o nome preenchido
        $this->get(route('admin.locais.create', ['nome' => 'Quadra A']))->assertOk()->assertSee('value="Quadra A"', false);
    }
}
