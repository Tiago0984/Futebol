<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\Responsavel;
use App\Models\Time;
use Database\Seeders\EscolinhaSeeder;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Elencos da escolinha (EscolinhaSeeder): os times AACJ Sub-13 e AACJ Sub-15 com o nome tirado da categoria e
 * 21 atletas ativos em cada categoria, coerentes com as regras do cadastro; nada gravado num banco com dados.
 */
class EscolinhaSeederTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_times_com_o_nome_da_categoria_e_21_atletas_em_cada(): void
    {
        $this->seed(EscolinhaSeeder::class);

        $times = Time::with('categoria')->orderBy('nome_time')->get();
        $this->assertSame(['AACJ Sub-13', 'AACJ Sub-15'], $times->pluck('nome_time')->all());
        foreach ($times as $time) {
            $this->assertSame('INTERNO', $time->tipo_time);
            $this->assertSame('AACJ ' . $time->categoria->nome_categoria, $time->nome_time);
            $this->assertSame('M', $time->categoria->sexo_categoria);

            $atletas = Atleta::where('status_atleta', 'ATIVO')
                ->whereHas('categoriasAtivas', fn ($q) => $q->where('tbl_categoria.id_categoria', $time->id_categoria))
                ->with('posicoes')->get();
            $this->assertCount(21, $atletas, $time->nome_time);

            // 3 goleiros e as outras posições principais do elenco de 21
            $principais = $atletas->map(fn ($a) => $a->posicoes->first()->posicao_atleta_posicao)->countBy()->all();
            $this->assertSame(3, $principais['GOLEIRO']);
            $this->assertSame(4, $principais['ZAGUEIRO']);
            $this->assertSame(2, $principais['CENTROAVANTE']);
        }
    }

    public function test_atletas_responsaveis_e_documentos(): void
    {
        $this->seed(EscolinhaSeeder::class);

        $atletas = Atleta::with(['categoriasAtivas', 'responsaveis'])->get();
        $this->assertCount(42, $atletas);
        foreach ($atletas as $atleta) {
            $this->assertSame('ATIVO', $atleta->status_atleta);
            $this->assertCount(1, $atleta->categoriasAtivas, $atleta->nome_atleta);
            $this->assertSame(
                Categoria::sugeridaPara($atleta->data_nasc_atleta, 'M')->id_categoria,
                $atleta->categoriasAtivas->first()->id_categoria,
                $atleta->nome_atleta,
            );
            $this->assertNotNull($atleta->periodo_escolar_atleta, $atleta->nome_atleta);
            $this->assertCount(1, $atleta->responsaveis, $atleta->nome_atleta);
            $this->assertMatchesRegularExpression('/^A\d{3}$/', $atleta->numero_matricula_atleta);
        }

        // Só quem estuda à noite tem o turno do treino
        $this->assertSame(0, Atleta::where('periodo_escolar_atleta', 'NOITE')->whereNull('turno_treino_atleta')->count());
        $this->assertSame(0, Atleta::where('periodo_escolar_atleta', '!=', 'NOITE')->whereNotNull('turno_treino_atleta')->count());

        // CPFs únicos; 40 responsáveis (dois pares de irmãos), que moram com os filhos
        $cpfs = Atleta::pluck('cpf_atleta')->map(fn ($cpf) => Responsavel::digitosCpf($cpf));
        $this->assertSame(42, $cpfs->unique()->count());
        $this->assertSame(40, Responsavel::count());
        $daniel = Atleta::where('nome_atleta', 'Daniel Prado')->first();
        $mateus = Atleta::where('nome_atleta', 'Mateus Prado')->first();
        $this->assertSame($daniel->responsaveis->first()->id_responsavel, $mateus->responsaveis->first()->id_responsavel);
        $this->assertSame($daniel->id_endereco, $mateus->id_endereco);
    }

    public function test_nao_grava_nada_num_banco_com_atletas_ou_times(): void
    {
        Time::create(['nome_time' => 'Time Existente', 'tipo_time' => 'EXTERNO', 'logo_time' => 'time-azul.png',
            'status_time' => 'ATIVO', 'id_categoria' => Categoria::first()->id_categoria]);

        $this->seed(EscolinhaSeeder::class);

        $this->assertSame(1, Time::count());
        $this->assertSame(0, Atleta::count());
    }
}
