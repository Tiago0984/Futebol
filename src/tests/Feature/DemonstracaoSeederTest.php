<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\EventoAtleta;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\Responsavel;
use App\Models\Time;
use App\Models\User;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Dados de demonstração (DemonstracaoSeeder): rodam num banco montado pelas migrations (ensaio do "começar do
 * zero") e saem coerentes com as regras atuais: atleta ativo com categoria, período e posição; responsáveis
 * únicos; convocação dentro da idade; jogos passados com placar e publicados, o próximo publicado com os
 * convocados avisados; amistoso em rascunho; nada gravado num banco que já tem dados.
 */
class DemonstracaoSeederTest extends TestCase
{
    use RefreshBancoDeTestes;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create();
        $this->seed(DemonstracaoSeeder::class);
    }

    public function test_atletas_responsaveis_e_matriculas(): void
    {
        $ativos = Atleta::where('status_atleta', 'ATIVO')->with(['categoriasAtivas', 'posicoes', 'responsaveis'])->get();

        $this->assertCount(25, $ativos);
        foreach ($ativos as $atleta) {
            $this->assertCount(1, $atleta->categoriasAtivas, $atleta->nome_atleta);
            $this->assertNotNull($atleta->periodo_escolar_atleta, $atleta->nome_atleta);
            $this->assertNotEmpty($atleta->posicoes, $atleta->nome_atleta);
            $this->assertCount(1, $atleta->responsaveis, $atleta->nome_atleta);
            $this->assertMatchesRegularExpression('/^A\d{3}$/', $atleta->numero_matricula_atleta);
        }

        // Quem estuda à noite escolheu o turno do treino; os outros não têm turno
        $this->assertSame(0, Atleta::where('periodo_escolar_atleta', 'NOITE')->whereNull('turno_treino_atleta')->count());
        $this->assertSame(0, Atleta::where('periodo_escolar_atleta', '!=', 'NOITE')->whereNotNull('turno_treino_atleta')->count());

        // CPFs únicos (só dígitos) e responsáveis únicos: os irmãos Davi e Igor têm o mesmo responsável
        $cpfs = Atleta::pluck('cpf_atleta')->map(fn ($cpf) => Responsavel::digitosCpf($cpf));
        $this->assertSame($cpfs->count(), $cpfs->unique()->count());
        $this->assertSame(26, Responsavel::count());
        $this->assertSame(26, Responsavel::pluck('cpf_responsavel')->unique()->count());
        $this->assertSame(
            Atleta::where('nome_atleta', 'Davi Rocha')->first()->responsaveis->first()->id_responsavel,
            Atleta::where('nome_atleta', 'Igor Rocha')->first()->responsaveis->first()->id_responsavel,
        );

        // Matrículas pelo site aguardando a assinatura
        $pendentes = Atleta::where('status_atleta', 'PENDENTE')->get();
        $this->assertCount(2, $pendentes);
        foreach ($pendentes as $atleta) {
            $this->assertSame('PENDENTE', $atleta->situacaoAutorizacao());
            $this->assertNull($atleta->numero_matricula_atleta);
        }
    }

    public function test_grade_real_sem_categoria(): void
    {
        $this->assertSame(5, GradeTreino::count());
        $this->assertSame(4, GradeTreino::where('tipo_grade_treino', 'TREINO')->whereNull('id_categoria')->count());
        $this->assertSame(['09:00:00', '14:00:00'], GradeTreino::where('dia_semana_grade_treino', 'terca')
            ->orderBy('horario_inicio_grade_treino')->pluck('horario_inicio_grade_treino')->all());

        // Sábado livre para todos: gera eventos e não tem turno
        $sabado = GradeTreino::where('dia_semana_grade_treino', 'sabado')->first();
        $this->assertSame('LIVRE', $sabado->tipo_grade_treino);
        $this->assertTrue($sabado->geraEventos());
        $this->assertNull($sabado->turno());
        $this->assertSame(0, GradeTreino::where('tipo_grade_treino', 'JOGO')->count());
        $this->assertSame(0, EventoCalendario::where('tipo_evento_calendario', 'TREINO')->count()); // gerar pela tela
    }

    public function test_campeonato_convocacao_e_classificacao(): void
    {
        $copa = Campeonato::firstOrFail();
        $this->assertSame(['Sub-13', 'Sub-15'], $copa->categorias()->orderBy('nome_categoria')->pluck('nome_categoria')->all());
        $this->assertSame(3, Time::where('tipo_time', 'INTERNO')->count());

        // Convocação: 9 no Sub-13 (o Theo, do Sub-11, sobe) e 8 no Sub-15 (o Samuel, do Sub-13, joga nos dois)
        $this->assertSame(17, $copa->convocacoes()->count());
        $samuel = Atleta::where('nome_atleta', 'Samuel Vieira')->first();
        $this->assertSame(2, $samuel->convocacoes()->count());

        // Duas rodadas jogadas: AACJ na frente nas duas chaves; o 2º pelo saldo de gols
        $tabelas = collect($copa->classificacoes())->mapWithKeys(fn ($c) => [$c['categoria']->nome_categoria => $c]);
        foreach (['Sub-13' => ['AACJ Sub-13', 'Botafogo da Vila', 'União Esportiva'],
                  'Sub-15' => ['AACJ Sub-15', 'Estrela FC', 'Botafogo da Vila']] as $categoria => $ordem) {
            $tabela = $tabelas[$categoria]['tabelas'][0];
            $this->assertSame('Chave A', $tabela['grupo']);
            $this->assertSame(2, $tabela['classificados']);
            $this->assertSame($ordem, array_column($tabela['tabela'], 'nome'));
        }
    }

    public function test_jogos_publicados_inscritos_e_avisos(): void
    {
        $this->assertSame(7, Jogo::count());
        $this->assertSame(0, Jogo::whereNotNull('id_campeonato')->whereHas('evento',
            fn ($q) => $q->whereNull('data_publicacao_evento_calendario'))->count());

        // Jogo da AACJ: os convocados inscritos e escalados no time (origem CONVOCACAO)
        $internos = Time::where('tipo_time', 'INTERNO')->pluck('id_time');
        $jogosAcj = Jogo::whereNotNull('id_campeonato')
            ->where(fn ($q) => $q->whereIn('id_time_casa', $internos)->orWhereIn('id_time_visitante', $internos))->get();
        $this->assertCount(4, $jogosAcj);
        foreach ($jogosAcj as $jogo) {
            $inscricoes = EventoAtleta::where('id_evento_calendario', $jogo->id_evento)->get();
            $this->assertNotEmpty($inscricoes);
            $this->assertSame(['CONVOCACAO'], $inscricoes->pluck('origem_evento_atleta')->unique()->values()->all());
            $this->assertSame(0, $inscricoes->whereNull('id_time')->count());
        }

        // Só a próxima rodada avisou (9 + 8 convocados); as jogadas foram publicadas sem avisar
        $this->assertSame(17, Notificacao::count());
        $this->assertSame(['INSCRICAO'], Notificacao::distinct()->pluck('tipo_notificacao')->all());

        // Amistoso em rascunho, com os 6 meninos do Sub-11 escolhidos e escalados na AACJ Sub-11
        $amistoso = Jogo::whereNull('id_campeonato')->with('evento')->firstOrFail();
        $this->assertFalse($amistoso->evento->estaPublicado());
        $this->assertSame(6, EventoAtleta::where('id_evento_calendario', $amistoso->id_evento)
            ->where('origem_evento_atleta', 'INDIVIDUAL')->where('id_time', $amistoso->id_time_casa)->count());

        // Nenhum conflito de horário entre os jogos de quem joga em duas categorias
        foreach (EventoCalendario::where('tipo_evento_calendario', 'JOGO')->get() as $evento) {
            $ids = $evento->inscricoes()->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
            $this->assertCount(0, $evento->conflitosPara($ids), $evento->titulo_evento_calendario);
        }
    }

    public function test_nao_roda_num_banco_com_dados(): void
    {
        $atletas = Atleta::count();
        $jogos = DB::table('tbl_jogos')->count();

        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame($atletas, Atleta::count());
        $this->assertSame($jogos, DB::table('tbl_jogos')->count());
    }
}
