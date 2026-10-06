<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Notificacao;
use App\Models\Responsavel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 9, migrations e responsável único: senha do responsável, tabelas de token dos brokers, leitura das
 * notificações pelo responsável, cadastro (site e admin) reaproveitando o responsável pelo CPF e e-mail do
 * responsável único e normalizado.
 */
class ResponsavelUnicoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- schema ----------

    public function test_senha_e_email_unico_em_tbl_responsavel(): void
    {
        $colunas = collect(Schema::getColumns('tbl_responsavel'))->keyBy('name');
        $this->assertSame('varchar(255)', $colunas['senha_responsavel']['type']);
        $this->assertTrue($colunas['senha_responsavel']['nullable']);

        $indice = collect(Schema::getIndexes('tbl_responsavel'))->firstWhere('name', 'email_responsavel_unique');
        $this->assertSame(['email_responsavel'], $indice['columns']);
        $this->assertTrue($indice['unique']);
    }

    public function test_tabelas_de_token_dos_brokers_no_formato_do_laravel(): void
    {
        foreach (['password_reset_tokens_atletas', 'password_reset_tokens_responsaveis'] as $tabela) {
            $colunas = collect(Schema::getColumns($tabela))->keyBy('name');
            $this->assertSame(['email', 'token', 'created_at'], $colunas->keys()->all(), $tabela);
            $this->assertSame(['email'], collect(Schema::getIndexes($tabela))->firstWhere('primary', true)['columns']);
            $this->assertTrue($colunas['created_at']['nullable']);
        }
    }

    public function test_tabela_de_leitura_pelo_responsavel_com_fks_e_unico_no_par(): void
    {
        $colunas = collect(Schema::getColumns('tbl_notificacao_leitura'))->keyBy('name');
        $this->assertSame('int unsigned', $colunas['id_notificacao_leitura']['type']);
        $this->assertSame('int unsigned', $colunas['id_notificacao']['type']);   // como tbl_notificacao
        $this->assertSame('int', $colunas['id_responsavel']['type']);            // como tbl_responsavel
        $this->assertSame('datetime', $colunas['data_notificacao_leitura']['type']);
        $this->assertFalse($colunas['data_notificacao_leitura']['nullable']);

        $fks = collect(Schema::getForeignKeys('tbl_notificacao_leitura'))->keyBy('name');
        $this->assertSame(['fk_notificacao_leitura_notificacao', 'fk_notificacao_leitura_responsavel'], $fks->keys()->sort()->values()->all());
        $fks->each(fn ($fk) => $this->assertSame('no action', strtolower($fk['on_delete'])));

        $unico = collect(Schema::getIndexes('tbl_notificacao_leitura'))->firstWhere('name', 'notificacao_leitura_unique');
        $this->assertSame(['id_notificacao', 'id_responsavel'], $unico['columns']);
        $this->assertTrue($unico['unique']);

        // O mesmo responsável não lê duas vezes a mesma notificação
        [$idNotificacao, $idResponsavel] = $this->notificacaoComResponsavel();
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $idNotificacao, 'id_responsavel' => $idResponsavel]);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $idNotificacao, 'id_responsavel' => $idResponsavel]);
    }

    public function test_exclusao_definitiva_apaga_as_leituras_dos_responsaveis(): void
    {
        [$idNotificacao, $idResponsavel, $idAtleta] = $this->notificacaoComResponsavel('REJEITADO');
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $idNotificacao, 'id_responsavel' => $idResponsavel]);

        $this->assertTrue(Atleta::find($idAtleta)->excluirComDependencias());

        $this->assertSame(0, DB::table('tbl_notificacao_leitura')->count());
        $this->assertSame(0, DB::table('tbl_notificacao')->count());
        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $idResponsavel]); // o responsável fica
    }

    public function test_migration_do_indice_para_com_a_lista_sem_mexer_nos_dados(): void
    {
        // Repetidos depois de normalizar (o índice atual aceita: diferem só no espaço do começo)
        $a = $this->responsavel('11122233344', 'mae@familia.com');
        $b = $this->responsavel('55566677788', ' mae@familia.com');

        $migration = require database_path('migrations/2026_10_07_000010_add_unique_email_to_tbl_responsavel.php');

        try {
            $migration->up();
            $this->fail('A migration deveria parar com a lista de repetidos.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("mae@familia.com (responsáveis {$a},{$b})", $e->getMessage());
        }

        $this->assertSame(' mae@familia.com', DB::table('tbl_responsavel')->where('id_responsavel', $b)->value('email_responsavel'));
    }

    public function test_migration_de_limpeza_funde_por_cpf_e_tira_os_emails_repetidos(): void
    {
        $atleta1 = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $atleta2 = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $atleta3 = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');

        // Mesmo CPF em três formatos; o de menor id está sem e-mail
        $fica = $this->responsavel('034.466.148-26', null);
        $repetido = $this->responsavel('03446614826', 'Mae@Familia.com ');
        $outroRepetido = $this->responsavel('034.466.148-26', 'outro@familia.com');
        $semAtleta = $this->responsavel('22222222222', 'sem.atleta@familia.com');
        // Repetido só depois de normalizar: o índice do banco de testes já existe e aceita o espaço no começo
        $mesmoEmail = $this->responsavel('33333333333', ' mae@familia.com');
        $semCpfA = $this->responsavel('', 'a@familia.com');
        $semCpfB = $this->responsavel('', 'b@familia.com');

        $vinculo = fn ($idAtleta, $idResponsavel) => DB::table('tbl_atleta_responsavel')->insert(
            ['id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel, 'grau_parentesco_responsavel' => 'Mãe']
        );
        $vinculo($atleta1, $fica);
        $vinculo($atleta1, $repetido);      // par que vai repetir
        $vinculo($atleta2, $repetido);
        $vinculo($atleta3, $mesmoEmail);
        $vinculo($atleta3, $semCpfA);
        $vinculo($atleta3, $semCpfB);

        $autorizacao = fn ($idAtleta, $idResponsavel, $status) => DB::table('tbl_autorizacoes')->insertGetId(
            ['id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel, 'status_autorizacao' => $status]
        );
        $autorizacao($atleta1, $fica, 'PENDENTE');
        $assinada = $autorizacao($atleta1, $repetido, 'ASSINADO');   // a assinada vale mais que a pendente
        $autorizacao($atleta2, $outroRepetido, 'PENDENTE');

        $notificacao = Notificacao::create([
            'id_atleta' => $atleta1, 'tipo_notificacao' => 'AGENDA', 'titulo_notificacao' => 'Agenda', 'mensagem_notificacao' => 'Texto',
        ])->id_notificacao;
        DB::table('tbl_notificacao_leitura')->insert([
            ['id_notificacao' => $notificacao, 'id_responsavel' => $fica],
            ['id_notificacao' => $notificacao, 'id_responsavel' => $repetido],
        ]);

        (require database_path('migrations/2026_10_07_000005_limpa_responsaveis_repetidos.php'))->up();

        // Regra 1: os repetidos do CPF somem e tudo passa para o de menor id, sem repetir o par
        $this->assertSame(
            [$fica, $semAtleta, $mesmoEmail, $semCpfA, $semCpfB],
            DB::table('tbl_responsavel')->orderBy('id_responsavel')->pluck('id_responsavel')->all()
        );
        $pares = fn ($tabela) => DB::table($tabela)->orderBy('id_atleta')->orderBy('id_responsavel')
            ->get(['id_atleta', 'id_responsavel'])->map(fn ($l) => [$l->id_atleta, $l->id_responsavel])->all();
        $this->assertSame([[$atleta1, $fica], [$atleta2, $fica], [$atleta3, $mesmoEmail], [$atleta3, $semCpfA], [$atleta3, $semCpfB]], $pares('tbl_atleta_responsavel'));
        $this->assertSame([[$atleta1, $fica], [$atleta2, $fica]], $pares('tbl_autorizacoes'));
        $this->assertSame('ASSINADO', DB::table('tbl_autorizacoes')->where('id_atleta', $atleta1)->sole()->status_autorizacao);
        $this->assertSame($assinada, DB::table('tbl_autorizacoes')->where('id_atleta', $atleta1)->value('id_autorizacao'));
        $this->assertSame([$fica], DB::table('tbl_notificacao_leitura')->pluck('id_responsavel')->all());

        // Herdou o e-mail do primeiro repetido; regra 3: sem atleta, fica só sem e-mail;
        // regra 2: o mesmo e-mail (normalizado) fica com o de menor id; CPF vazio não funde
        $emails = DB::table('tbl_responsavel')->pluck('email_responsavel', 'id_responsavel');
        $this->assertSame('Mae@Familia.com ', $emails[$fica]);
        $this->assertNull($emails[$semAtleta]);
        $this->assertNull($emails[$mesmoEmail]);
        $this->assertSame('a@familia.com', $emails[$semCpfA]);
        $this->assertSame('b@familia.com', $emails[$semCpfB]);

        // Depois da limpeza, a conferência da migration do índice não acha repetidos
        $this->assertSame(0, DB::table('tbl_responsavel')->selectRaw("LOWER(TRIM(email_responsavel)) e")->whereNotNull('email_responsavel')
            ->groupBy('e')->havingRaw('COUNT(*) > 1')->get()->count());
    }

    // ---------- model ----------

    public function test_cpf_por_digitos_email_normalizado_e_senha_fora_do_json(): void
    {
        $this->assertSame('03446614826', Responsavel::digitosCpf('034.466.148-26'));

        $formatado = $this->responsavel('034.466.148-26', 'a@x.com');
        $soDigitos = $this->responsavel('12345678909', 'b@x.com');
        $this->assertSame($formatado, Responsavel::porCpf('03446614826')->id_responsavel);
        $this->assertSame($soDigitos, Responsavel::porCpf('123.456.789-09')->id_responsavel);
        $this->assertNull(Responsavel::porCpf('034.466.148-26', exceto: $formatado));
        $this->assertNull(Responsavel::porCpf(''));

        $responsavel = Responsavel::find($formatado);
        $responsavel->update(['email_responsavel' => '  Mae@Familia.COM ']);
        $this->assertSame('mae@familia.com', $responsavel->fresh()->email_responsavel);
        $responsavel->update(['email_responsavel' => '']);
        $this->assertNull($responsavel->fresh()->email_responsavel);

        DB::table('tbl_responsavel')->where('id_responsavel', $formatado)->update(['senha_responsavel' => 'hash']);
        $this->assertArrayNotHasKey('senha_responsavel', Responsavel::find($formatado)->toArray());
    }

    // ---------- site ----------

    public function test_site_reaproveita_o_responsavel_do_mesmo_cpf_e_email(): void
    {
        Mail::fake();
        $cpf = $this->cpfValido('390533447');
        $existente = $this->responsavel(Responsavel::digitosCpf($cpf), 'mae@familia.com'); // gravado só com dígitos
        $responsaveisAntes = DB::table('tbl_responsavel')->count();
        $enderecosAntes = DB::table('tbl_endereco')->count();

        $this->post(route('cadastro.store'), $this->formularioSite(['cpf_responsavel' => $cpf, 'email_responsavel' => 'MAE@familia.com']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso');

        $atleta = Atleta::where('email_atleta', 'novo@atleta.com')->sole();
        $this->assertSame([$existente], $atleta->responsaveis()->pluck('tbl_responsavel.id_responsavel')->all());
        $this->assertSame($responsaveisAntes, DB::table('tbl_responsavel')->count());
        $this->assertSame($enderecosAntes + 1, DB::table('tbl_endereco')->count());       // só o do atleta
        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $existente, 'nome_responsavel' => 'Responsável Cadastrado']); // não muda
        $this->assertDatabaseHas('tbl_autorizacoes', ['id_atleta' => $atleta->id_atleta, 'id_responsavel' => $existente]);
    }

    public function test_site_recusa_cpf_cadastrado_com_outro_email(): void
    {
        $cpf = $this->cpfValido('390533447');
        $this->responsavel($cpf, 'mae@familia.com');
        $semEmail = $this->cpfValido('529982247');
        $this->responsavel($semEmail, null);

        foreach ([[$cpf, 'outro@familia.com'], [$semEmail, 'qualquer@familia.com']] as [$cpfDoForm, $email]) {
            $this->post(route('cadastro.store'), $this->formularioSite(['cpf_responsavel' => $cpfDoForm, 'email_responsavel' => $email]))
                ->assertSessionHasErrors(['cpf_responsavel' => 'CPF já cadastrado com outro e-mail; procure a secretaria.']);
        }

        $this->assertSame(0, Atleta::count());
    }

    public function test_site_recusa_email_de_outro_responsavel_e_grava_o_email_normalizado(): void
    {
        $this->responsavel($this->cpfValido('390533447'), 'mae@familia.com');

        $this->post(route('cadastro.store'), $this->formularioSite(['email_responsavel' => 'Mae@Familia.com']))
            ->assertSessionHasErrors(['email_responsavel' => 'Este e-mail já está cadastrado para outro responsável.']);

        Mail::fake();
        $this->post(route('cadastro.store'), $this->formularioSite(['email_responsavel' => 'Pai@Familia.COM']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tbl_responsavel', ['cpf_responsavel' => $this->formularioSite()['cpf_responsavel'], 'email_responsavel' => 'pai@familia.com']);
    }

    // ---------- admin ----------

    public function test_admin_vincula_ao_responsavel_do_mesmo_cpf_sem_mudar_os_dados_dele(): void
    {
        $existente = $this->responsavel('11111111111', null); // o formulário do admin manda 111.111.111-11

        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro([
            'nome_responsavel' => 'Outro Nome Digitado', 'email_responsavel' => 'Mae@Familia.com',
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, 'O responsável Responsável Cadastrado já estava cadastrado com este CPF')
                && str_contains($msg, 'só o e-mail, que estava vazio, foi preenchido'));

        $this->assertSame(1, DB::table('tbl_responsavel')->count());
        $this->assertDatabaseHas('tbl_responsavel', [
            'id_responsavel' => $existente, 'nome_responsavel' => 'Responsável Cadastrado', 'email_responsavel' => 'mae@familia.com',
        ]);
        $this->assertSame($existente, (int) DB::table('tbl_atleta_responsavel')->value('id_responsavel'));

        // Já com e-mail: o digitado diferente não troca o cadastrado
        $this->comoAdmin()->post(route('admin.atletas.store'), $this->dadosCadastro([
            'cpf_atleta' => '333.333.333-33', 'email_responsavel' => 'outro@familia.com',
        ]))->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, 'os dados do responsável não foram alterados.'));
        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $existente, 'email_responsavel' => 'mae@familia.com']);
    }

    public function test_admin_edicao_email_unico_ignorando_o_proprio(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));
        $idResponsavel = $this->criarResponsavel($idAtleta);
        $this->responsavel('99999999999', 'ocupado@familia.com');

        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['email_responsavel' => "Responsavel{$idAtleta}@teste.com"]))
            ->assertSessionHasNoErrors('edicao');

        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['email_responsavel' => 'ocupado@familia.com']))
            ->assertSessionHasErrorsIn('edicao', ['email_responsavel' => 'Este e-mail já está cadastrado para outro responsável.']);

        $this->assertDatabaseHas('tbl_responsavel', ['id_responsavel' => $idResponsavel, 'email_responsavel' => "responsavel{$idAtleta}@teste.com"]);
    }

    public function test_admin_edicao_recusa_cpf_de_outro_responsavel_e_aceita_o_cpf_de_sempre(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));
        $this->criarResponsavel($idAtleta);                              // CPF 111.111.111-11
        $this->responsavel('111.111.111-11', 'repetido@familia.com');   // repetido antigo do mesmo CPF
        $this->responsavel('99999999999', 'outro@familia.com', 'Pai de Outro Atleta');

        // CPF de sempre (mesmo com um repetido antigo): a edição segue
        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta))
            ->assertSessionHasNoErrors('edicao');

        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['cpf_responsavel' => '999.999.999-99']))
            ->assertSessionHasErrorsIn('edicao', ['cpf_responsavel' => 'Este CPF já é do responsável Pai de Outro Atleta, de outro cadastro. Confira o CPF.']);
    }

    public function test_admin_edicao_de_atleta_sem_responsavel_vincula_o_do_mesmo_cpf(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        $this->colocarNaCategoria($idAtleta, $this->idCategoria('Sub-13', 'M'));
        $existente = $this->responsavel('11111111111', 'mae@familia.com');

        $this->comoAdmin()->put(route('admin.atletas.update', $idAtleta), $this->dadosEdicao($idAtleta, ['email_responsavel' => 'mae@familia.com']))
            ->assertSessionHasNoErrors('edicao');

        $this->assertSame(1, DB::table('tbl_responsavel')->count());
        $this->assertSame([$existente], Atleta::find($idAtleta)->responsaveis()->pluck('tbl_responsavel.id_responsavel')->all());
    }

    // ---------- helpers ----------

    private function responsavel(string $cpf, ?string $email, string $nome = 'Responsável Cadastrado'): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua A', 'numero_endereco' => '1', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_responsavel')->insertGetId([
            'id_endereco' => $idEndereco, 'nome_responsavel' => $nome, 'cpf_responsavel' => $cpf,
            'rg_responsavel' => '11.111.111-1', 'whatsapp_responsavel' => '(11) 99999-9999', 'email_responsavel' => $email,
        ]);
    }

    // Notificação de um atleta que tem responsável: [id_notificacao, id_responsavel, id_atleta]
    private function notificacaoComResponsavel(string $status = 'ATIVO'): array
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(12), 'M', $status);
        $idResponsavel = $this->criarResponsavel($idAtleta);
        $notificacao = Notificacao::create([
            'id_atleta' => $idAtleta, 'tipo_notificacao' => 'AGENDA', 'titulo_notificacao' => 'Agenda', 'mensagem_notificacao' => 'Texto',
        ]);

        return [$notificacao->id_notificacao, $idResponsavel, $idAtleta];
    }

    // CPF válido a partir dos 9 primeiros dígitos (o site confere os dígitos verificadores)
    private function cpfValido(string $nove): string
    {
        $cpf = $nove;
        foreach ([10, 11] as $peso) {
            $soma = 0;
            foreach (str_split($cpf) as $i => $digito) {
                $soma += (int) $digito * ($peso - $i);
            }
            $cpf .= ((10 * $soma) % 11) % 10;
        }

        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    // Formulário completo do cadastro público (site)
    private function formularioSite(array $extra = []): array
    {
        return array_merge([
            'nome_atleta' => 'Atleta do Site', 'data_nasc_atleta' => $this->nascidoComIdade(12), 'cpf_atleta' => $this->cpfValido('111444777'),
            'rg_atleta' => '12.345.678-9', 'sexo_atleta' => 'M', 'escola_atleta' => 'Escola', 'serie_atleta' => '6º ano',
            'periodo_escolar_atleta' => 'Manhã', 'email_atleta' => 'novo@atleta.com',
            'cep_endereco' => '01000-000', 'rua_endereco' => 'Rua do Atleta', 'numero_endereco' => '10', 'bairro_endereco' => 'Centro',
            'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
            'nome_responsavel' => 'Responsável do Site', 'cpf_responsavel' => $this->cpfValido('987654321'), 'rg_responsavel' => '98.765.432-1',
            'email_responsavel' => 'resp@site.com', 'whatsapp_responsavel' => '(11) 98888-7777', 'grau_parentesco' => 'Mãe',
            'cep_resp_endereco' => '01000-000', 'rua_resp_endereco' => 'Rua do Responsável', 'numero_resp_endereco' => '20',
            'bairro_resp_endereco' => 'Centro', 'cidade_resp_endereco' => 'São Paulo', 'estado_resp_endereco' => 'SP',
        ], $extra);
    }
}
