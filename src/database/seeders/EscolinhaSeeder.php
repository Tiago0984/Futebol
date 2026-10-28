<?php

namespace Database\Seeders;

use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\Responsavel;
use App\Models\Time;
use Database\Seeders\Concerns\CriaAtletasFicticios;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Elencos da escolinha (todos fictícios): 21 atletas ativos no Sub-13 e 21 no Sub-15 Masculino (3 goleiros,
 * 4 zagueiros, 2 laterais de cada lado, 3 volantes, 2 meias-centrais, 1 meia-ofensivo, 1 ponta de cada lado e
 * 2 centroavantes), com período escolar e um responsável cada (dois pares de irmãos dividem o responsável).
 *
 * Times (28/10/2026): um cadastro só por time, com as categorias marcadas (tbl_time_categoria): a "AACJ Masculino"
 * e os adversários Botafogo e Palmeiras, os três no Sub-13 e no Sub-15 Masculino. O atleta é da categoria, não do
 * time: o time recebe os atletas pela convocação de cada campeonato (tela Convocados, por categoria, com a camisa).
 *
 * Fora do DatabaseSeeder de propósito. Só roda num banco sem atletas nem times:
 *   php artisan db:seed --class=EscolinhaSeeder
 */
class EscolinhaSeeder extends Seeder
{
    use CriaAtletasFicticios;

    // Por categoria: nome, idade no ano, período, turno (só NOITE), posições, tem e-mail, responsável, grau
    private const ELENCOS = [
        'Sub-13' => [
            ['Guilherme Batista',   13, 'MANHA',    null,    ['GOLEIRO'],                           true,  'Rosana Batista',     'Mãe'],
            ['Vinícius Lacerda',    12, 'TARDE',    null,    ['GOLEIRO'],                           false, 'Henrique Lacerda',   'Pai'],
            ['Kauã Moura',          12, 'INTEGRAL', null,    ['GOLEIRO'],                           false, 'Débora Moura',       'Mãe'],
            ['João Pedro Siqueira', 13, 'TARDE',    null,    ['ZAGUEIRO'],                          true,  'Alexandre Siqueira', 'Pai'],
            ['Bruno Tavares',       13, 'MANHA',    null,    ['ZAGUEIRO', 'VOLANTE'],               false, 'Kátia Tavares',      'Mãe'],
            ['Daniel Prado',        12, 'TARDE',    null,    ['ZAGUEIRO'],                          false, 'Ana Prado',          'Mãe'], // irmão do Mateus (Sub-15)
            ['Emanuel Rezende',     12, 'MANHA',    null,    ['ZAGUEIRO', 'LIBERO'],                false, 'Fábio Rezende',      'Pai'],
            ['Pietro Andrade',      13, 'INTEGRAL', null,    ['LATERAL_DIREITO'],                   true,  'Mônica Andrade',     'Mãe'],
            ['Isaac Bezerra',       12, 'TARDE',    null,    ['LATERAL_DIREITO', 'MEIA_DIREITA'],   false, 'Rogério Bezerra',    'Pai'],
            ['Leonardo Pacheco',    13, 'MANHA',    null,    ['LATERAL_ESQUERDO'],                  false, 'Sílvia Pacheco',     'Mãe'],
            ['Anthony Queiroz',     12, 'TARDE',    null,    ['LATERAL_ESQUERDO', 'MEIA_ESQUERDA'], false, 'Wagner Queiroz',     'Pai'],
            ['Cauã Medeiros',       13, 'TARDE',    null,    ['VOLANTE'],                           true,  'Lúcia Medeiros',     'Avó'],
            ['Rodrigo Nascimento',  12, 'MANHA',    null,    ['VOLANTE', 'MEIA_CENTRAL'],           false, 'Tânia Nascimento',   'Mãe'],
            ['Yuri Dantas',         13, 'INTEGRAL', null,    ['VOLANTE'],                           false, 'Márcio Dantas',      'Pai'],
            ['Henrique Coelho',     12, 'MANHA',    null,    ['MEIA_CENTRAL'],                      false, 'Priscila Coelho',    'Mãe'],
            ['Miguel Brandão',      13, 'TARDE',    null,    ['MEIA_CENTRAL', 'MEIA_OFENSIVO'],     true,  'Otávio Brandão',     'Pai'],
            ['Thiago Fontes',       13, 'MANHA',    null,    ['MEIA_OFENSIVO'],                     false, 'Viviane Fontes',     'Mãe'],
            ['Kevin Assis',         12, 'TARDE',    null,    ['PONTA_DIREITA'],                     false, 'Jorge Assis',        'Pai'],
            ['Lucca Peixoto',       12, 'MANHA',    null,    ['PONTA_ESQUERDA'],                    false, 'Raquel Peixoto',     'Mãe'],
            ['Diego Brito',         13, 'TARDE',    null,    ['CENTROAVANTE'],                      true,  'Edson Brito',        'Pai'],
            ['Felipe Sales',        12, 'INTEGRAL', null,    ['CENTROAVANTE', 'SEGUNDO_ATACANTE'],  false, 'Cristiane Sales',    'Mãe'],
        ],
        'Sub-15' => [
            ['Marcelo Aguiar',      15, 'MANHA',    null,    ['GOLEIRO'],                           true,  'Sônia Aguiar',       'Mãe'],
            ['Renan Bastos',        14, 'TARDE',    null,    ['GOLEIRO'],                           false, 'Gilberto Bastos',    'Pai'],
            ['Wesley Gouveia',      14, 'NOITE',    'MANHA', ['GOLEIRO'],                           false, 'Rita Gouveia',       'Mãe'],
            ['Mateus Prado',        15, 'MANHA',    null,    ['ZAGUEIRO'],                          true,  'Ana Prado',          'Mãe'], // irmão do Daniel (Sub-13)
            ['Caio Henrique Lemos', 15, 'TARDE',    null,    ['ZAGUEIRO'],                          false, 'Valter Lemos',       'Pai'],
            ['Gabriel Fonseca',     14, 'INTEGRAL', null,    ['ZAGUEIRO', 'VOLANTE'],               false, 'Elisa Fonseca',      'Mãe'],
            ['Danilo Rangel',       15, 'NOITE',    'AMBOS', ['ZAGUEIRO'],                          false, 'Humberto Rangel',    'Pai'],
            ['Vitor Hugo Sampaio',  14, 'TARDE',    null,    ['LATERAL_DIREITO'],                   true,  'Beatriz Sampaio',    'Mãe'],
            ['Erick Magalhães',     15, 'MANHA',    null,    ['LATERAL_DIREITO'],                   false, 'Nelson Magalhães',   'Pai'],
            ['Luan Cavalcanti',     14, 'TARDE',    null,    ['LATERAL_ESQUERDO'],                  false, 'Karina Cavalcanti',  'Mãe'], // irmão do Ruan
            ['Ruan Cavalcanti',     15, 'MANHA',    null,    ['LATERAL_ESQUERDO', 'MEIA_ESQUERDA'], false, 'Karina Cavalcanti',  'Mãe'],
            ['Jonathan Prates',     15, 'TARDE',    null,    ['VOLANTE'],                           true,  'Denise Prates',      'Mãe'],
            ['Alan Torres',         14, 'MANHA',    null,    ['VOLANTE'],                           false, 'Ivan Torres',        'Pai'],
            ['Breno Viana',         15, 'INTEGRAL', null,    ['VOLANTE', 'MEIA_CENTRAL'],           false, 'Cecília Viana',      'Mãe'],
            ['Kaique Mattos',       14, 'TARDE',    null,    ['MEIA_CENTRAL'],                      false, 'Ronaldo Mattos',     'Pai'],
            ['Paulo Ricardo Godoy', 15, 'MANHA',    null,    ['MEIA_CENTRAL', 'MEIA_DIREITA'],      true,  'Aline Godoy',        'Mãe'],
            ['Bryan Esteves',       14, 'TARDE',    null,    ['MEIA_OFENSIVO', 'SEGUNDO_ATACANTE'], false, 'Fernando Esteves',   'Pai'],
            ['Hugo Maciel',         15, 'MANHA',    null,    ['PONTA_DIREITA'],                     false, 'Lívia Maciel',       'Mãe'],
            ['Ian Furtado',         14, 'TARDE',    null,    ['PONTA_ESQUERDA', 'PONTA_DIREITA'],   false, 'Rubens Furtado',     'Pai'],
            ['Samuel Pimenta',      15, 'INTEGRAL', null,    ['CENTROAVANTE'],                      true,  'Helena Pimenta',     'Mãe'],
            ['Arthur Lins',         14, 'MANHA',    null,    ['CENTROAVANTE'],                      false, 'Marta Lins',         'Mãe'],
        ],
    ];

    // Times com as categorias de ELENCOS marcadas: nome, tipo e logo (pasta public/futebol/images/team)
    private const TIMES = [
        ['AACJ Masculino', 'INTERNO', 'time-azul.png'],
        ['Botafogo',       'EXTERNO', 'time-preto.jpg'],
        ['Palmeiras',      'EXTERNO', 'time-verde.png'],
    ];

    public function run(): void
    {
        if (Atleta::exists() || Time::exists()) {
            $this->command?->error('O banco já tem atletas ou times: os elencos da escolinha só rodam num banco sem eles.');
            return;
        }

        DB::transaction(function () {
            $responsaveis = [];
            $i = 0;
            $categorias = collect(array_keys(self::ELENCOS))->mapWithKeys(fn ($nome) => [
                $nome => Categoria::where('nome_categoria', $nome)->where('sexo_categoria', 'M')->firstOrFail(),
            ]);

            foreach (self::TIMES as [$nome, $tipo, $logo]) {
                Time::create(['nome_time' => $nome, 'tipo_time' => $tipo, 'logo_time' => $logo, 'status_time' => 'ATIVO'])
                    ->definirCategorias($categorias->pluck('id_categoria')->all());
            }

            foreach (self::ELENCOS as $nomeCategoria => $elenco) {
                $categoria = $categorias[$nomeCategoria];

                foreach ($elenco as [$nome, $idade, $periodo, $turno, $posicoes, $comEmail, $nomeResp, $grau]) {
                    // Irmãos: o mesmo responsável (reaproveitado, como pelo CPF no cadastro) e o mesmo endereço
                    $responsavel = $responsaveis[$nomeResp] ??= $this->criarResponsavel($nomeResp, $this->criarEndereco($i), $i);
                    $this->criarAtleta($categoria, $nome, $idade, $periodo, $turno, $posicoes, $comEmail, $responsavel, $grau, $i);
                    $i++;
                }
            }
        });

        $this->command?->info('Escolinha criada: ' . Time::pluck('nome_time')->implode(', ') . ' (Sub-13 e Sub-15 Masculino), com '
            . Atleta::where('status_atleta', 'ATIVO')->count() . ' atletas ativos e ' . Responsavel::count() . ' responsáveis.');
    }

    // O mesmo caminho do cadastro pelo admin: atleta (no endereço do responsável), posições, vínculo, categoria e
    // número de matrícula
    private function criarAtleta(Categoria $categoria, string $nome, int $idade, string $periodo, ?string $turno,
        array $posicoes, bool $comEmail, Responsavel $responsavel, string $grau, int $i): void
    {
        $atleta = Atleta::create([
            ...$this->dadosDoAtleta($nome, $idade, 'M', $periodo, $i),
            'turno_treino_atleta' => $turno,
            'email_atleta'        => $comEmail ? $this->email($nome) : null,
            'status_atleta'       => 'ATIVO',
            'id_endereco'         => $responsavel->id_endereco,
        ]);

        // A idade decide a categoria (regra do ano): erro aqui é erro nos dados acima
        if (Categoria::sugeridaPara($atleta->data_nasc_atleta, 'M')?->id_categoria !== $categoria->id_categoria) {
            throw new \RuntimeException("{$nome} ({$idade} anos) não é do {$categoria->nome_categoria}.");
        }

        $atleta->definirPosicoes($posicoes);
        $atleta->responsaveis()->attach($responsavel->id_responsavel, ['grau_parentesco_responsavel' => $grau]);
        $atleta->trocarCategoria($categoria->id_categoria);
        $atleta->atribuirNumeroMatricula();
    }
}
