<?php

namespace Database\Seeders;

use App\Models\Atleta;
use App\Models\Campeonato;
use App\Models\CampeonatoGrupo;
use App\Models\Categoria;
use App\Models\Endereco;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use App\Models\Jogo;
use App\Models\Responsavel;
use App\Models\Time;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dados de demonstração (todos fictícios): atletas com período escolar, posições e responsáveis únicos;
 * times da AACJ por categoria e adversários; a grade real de treinos; um campeonato com duas categorias,
 * uma chave em cada, convocação e jogos; um amistoso em rascunho.
 *
 * Fora do DatabaseSeeder de propósito (a entrega começa do zero). Só roda num banco sem atletas, times,
 * campeonatos nem grade:
 *   php artisan db:seed --class=DemonstracaoSeeder
 *
 * As datas partem de hoje: as rodadas do campeonato são os dois últimos sábados (com placar) e o próximo
 * (publicado, sem placar); o amistoso fica duas semanas depois. Os treinos não são gerados aqui: usar o
 * "Gerar agenda do mês" da grade, que inscreve cada atleta pelo turno.
 * Os caminhos são os das telas (inscrição pela convocação, publicar), para valerem as mesmas regras.
 */
class DemonstracaoSeeder extends Seeder
{
    private ?int $idUsuario = null;

    private array $categorias = [];

    // Atletas ativos: nome, idade no ano, sexo, período, turno (só NOITE), posições, tem e-mail, responsável, grau
    private const ATLETAS = [
        ['Arthur Mendes',     11, 'M', 'MANHA',    null,    ['GOLEIRO'],                          true,  'Carla Mendes',      'Mãe'],
        ['Davi Rocha',        11, 'M', 'TARDE',    null,    ['ZAGUEIRO', 'VOLANTE'],              false, 'Marcos Rocha',      'Pai'],
        ['Gael Nogueira',     10, 'M', 'INTEGRAL', null,    ['MEIA_CENTRAL'],                     false, 'Patrícia Nogueira', 'Mãe'],
        ['Heitor Campos',     10, 'M', 'MANHA',    null,    ['PONTA_DIREITA', 'PONTA_ESQUERDA'],  false, 'Roberto Campos',    'Pai'],
        ['Theo Barbosa',      11, 'M', 'TARDE',    null,    ['CENTROAVANTE'],                     true,  'Juliana Barbosa',   'Mãe'],
        ['Ravi Teixeira',     10, 'M', 'MANHA',    null,    ['LATERAL_ESQUERDO'],                 false, 'Sandra Teixeira',   'Avó'],
        ['Alice Moreira',     11, 'F', 'TARDE',    null,    ['MEIA_OFENSIVO'],                    false, 'Fernanda Moreira',  'Mãe'],
        ['Bernardo Lopes',    13, 'M', 'MANHA',    null,    ['GOLEIRO'],                          true,  'André Lopes',       'Pai'],
        ['Enzo Cardoso',      13, 'M', 'TARDE',    null,    ['ZAGUEIRO'],                         true,  'Luciana Cardoso',   'Mãe'],
        ['Lorenzo Pires',     12, 'M', 'MANHA',    null,    ['ZAGUEIRO', 'LIBERO'],               false, 'Ricardo Pires',     'Pai'],
        ['Murilo Duarte',     12, 'M', 'INTEGRAL', null,    ['LATERAL_DIREITO'],                  false, 'Vanessa Duarte',    'Mãe'],
        ['Samuel Vieira',     13, 'M', 'TARDE',    null,    ['VOLANTE', 'MEIA_CENTRAL'],          true,  'Eduardo Vieira',    'Pai'],
        ['Benício Freitas',   12, 'M', 'MANHA',    null,    ['MEIA_OFENSIVO', 'SEGUNDO_ATACANTE'], false, 'Cristina Freitas', 'Mãe'],
        ['Joaquim Ramos',     13, 'M', 'TARDE',    null,    ['PONTA_ESQUERDA'],                   false, 'Paulo Ramos',       'Tio'],
        ['Otávio Correia',    12, 'M', 'TARDE',    null,    ['CENTROAVANTE'],                     false, 'Simone Correia',    'Mãe'],
        ['Helena Martins',    13, 'F', 'MANHA',    null,    ['ZAGUEIRO'],                         true,  'Renata Martins',    'Mãe'],
        ['Laura Azevedo',     12, 'F', 'INTEGRAL', null,    ['PONTA_DIREITA'],                    false, 'Gustavo Azevedo',   'Pai'],
        ['Caio Fernandes',    15, 'M', 'MANHA',    null,    ['GOLEIRO'],                          true,  'Márcia Fernandes',  'Mãe'],
        ['Felipe Monteiro',   15, 'M', 'NOITE',    'AMBOS', ['ZAGUEIRO'],                         true,  'Sérgio Monteiro',   'Pai'],
        ['Gustavo Ribeiro',   14, 'M', 'TARDE',    null,    ['LATERAL_DIREITO', 'LATERAL_ESQUERDO'], false, 'Adriana Ribeiro', 'Mãe'],
        ['Igor Rocha',        14, 'M', 'MANHA',    null,    ['VOLANTE'],                          false, 'Marcos Rocha',      'Pai'], // irmão do Davi
        ['Matheus Cunha',     15, 'M', 'NOITE',    'MANHA', ['MEIA_CENTRAL', 'MEIA_DIREITA'],     true,  'Cláudia Cunha',     'Mãe'],
        ['Nicolas Farias',    14, 'M', 'INTEGRAL', null,    ['PONTA_DIREITA'],                    false, 'Rodrigo Farias',    'Pai'],
        ['Rafael Antunes',    15, 'M', 'TARDE',    null,    ['CENTROAVANTE', 'SEGUNDO_ATACANTE'], false, 'Elaine Antunes',    'Mãe'],
        ['Sofia Carvalho',    14, 'F', 'TARDE',    null,    ['MEIA_ESQUERDA'],                    true,  'Fábio Carvalho',    'Pai'],
    ];

    // Matrículas pelo site aguardando a assinatura do responsável (tela Matrículas)
    private const PENDENTES = [
        ['Pedro Henrique Alves', 9,  'M', 'MANHA', 'Tatiane Alves', 'Mãe'],
        ['Valentina Dias',       12, 'F', 'TARDE', 'Leandro Dias',  'Pai'],
    ];

    private const RUAS = ['Rua das Palmeiras', 'Rua Jacarandá', 'Avenida dos Ipês', 'Rua Sabiá', 'Rua Bem-te-vi',
        'Rua das Acácias', 'Travessa do Campo', 'Rua Manacá'];

    private const BAIRROS = ['Jardim Juscelino', 'Vila Esperança', 'Parque das Flores', 'Jardim Primavera'];

    private const ESCOLAS = ['E.E. Professora Maria Aparecida', 'E.M. Monteiro Lobato', 'Colégio Novo Horizonte',
        'E.E. Dom Pedro II'];

    // Camisas da convocação (nome => número). Mais novo sobe: Theo (Sub-11) no Sub-13 e Samuel (Sub-13) no Sub-15
    private const CONVOCACAO_SUB13 = ['Bernardo Lopes' => 1, 'Murilo Duarte' => 2, 'Enzo Cardoso' => 3, 'Lorenzo Pires' => 4,
        'Samuel Vieira' => 5, 'Joaquim Ramos' => 7, 'Otávio Correia' => 9, 'Benício Freitas' => 10, 'Theo Barbosa' => 11];

    private const CONVOCACAO_SUB15 = ['Caio Fernandes' => 1, 'Gustavo Ribeiro' => 2, 'Felipe Monteiro' => 3, 'Igor Rocha' => 5,
        'Nicolas Farias' => 7, 'Matheus Cunha' => 8, 'Rafael Antunes' => 9, 'Samuel Vieira' => 10];

    public function run(): void
    {
        if (Atleta::exists() || Time::exists() || Campeonato::exists() || GradeTreino::exists()) {
            $this->command?->error('O banco já tem atletas, times, campeonatos ou grade: a demonstração só roda num banco sem eles.');
            return;
        }

        // Responsável pelos eventos e convocações: o primeiro usuário do admin, se houver (senão, "—")
        $this->idUsuario = User::orderBy('id_usuario')->value('id_usuario');
        $this->categorias = Categoria::all()->keyBy(fn ($c) => "{$c->nome_categoria}|{$c->sexo_categoria}")->all();

        DB::transaction(function () {
            $atletas = $this->criarAtletas();
            $this->criarPendentes();
            $this->criarGrade();
            $times = $this->criarTimes();
            $this->criarCampeonato($times, $atletas);
            $this->criarAmistoso($times, $atletas);
        });

        $this->command?->info('Demonstração criada: ' . Atleta::where('status_atleta', 'ATIVO')->count() . ' atletas ativos, '
            . Atleta::where('status_atleta', 'PENDENTE')->count() . ' matrículas pendentes, ' . Time::count() . ' times, '
            . Jogo::count() . ' jogos e ' . GradeTreino::count() . ' linhas da grade.');
    }

    // ── Atletas e responsáveis ─────────────────────────────────────────────

    // Devolve [nome => Atleta]. O mesmo caminho do cadastro pelo admin: endereço, responsável (reaproveitado pelo
    // CPF, como o irmão), atleta, posições, vínculo, categoria e número de matrícula
    private function criarAtletas(): array
    {
        $atletas = [];
        $responsaveis = [];

        foreach (self::ATLETAS as $i => [$nome, $idade, $sexo, $periodo, $turno, $posicoes, $comEmail, $nomeResp, $grau]) {
            $endereco = $this->criarEndereco($i);
            $responsavel = $responsaveis[$nomeResp] ??= $this->criarResponsavel($nomeResp, $endereco, $i);

            $atleta = Atleta::create([
                ...$this->dadosDoAtleta($nome, $idade, $sexo, $periodo, $i),
                'turno_treino_atleta' => $turno,
                'email_atleta'        => $comEmail ? $this->email($nome) : null,
                'status_atleta'       => 'ATIVO',
                'id_endereco'         => $endereco->id_endereco,
            ]);

            $atleta->definirPosicoes($posicoes);
            $atleta->responsaveis()->attach($responsavel->id_responsavel, ['grau_parentesco_responsavel' => $grau]);
            $atleta->trocarCategoria(Categoria::sugeridaPara($atleta->data_nasc_atleta, $sexo)->id_categoria);
            $atleta->atribuirNumeroMatricula();

            $atletas[$nome] = $atleta;
        }

        return $atletas;
    }

    // Matrícula do site: PENDENTE, sem categoria nem número (recebem na aprovação), autorização com o link de assinatura
    private function criarPendentes(): void
    {
        foreach (self::PENDENTES as $j => [$nome, $idade, $sexo, $periodo, $nomeResp, $grau]) {
            $i = count(self::ATLETAS) + $j;
            $endereco = $this->criarEndereco($i);
            $responsavel = $this->criarResponsavel($nomeResp, $endereco, $i);

            $atleta = Atleta::create([
                ...$this->dadosDoAtleta($nome, $idade, $sexo, $periodo, $i),
                'status_atleta' => 'PENDENTE',
                'id_endereco'   => $endereco->id_endereco,
            ]);
            $atleta->responsaveis()->attach($responsavel->id_responsavel, ['grau_parentesco_responsavel' => $grau]);
            $atleta->autorizacoes()->create([
                'id_responsavel'              => $responsavel->id_responsavel,
                'data_assinatura_autorizacao' => now(),
                'token_assinatura'            => Str::random(60),
                'status_autorizacao'          => 'PENDENTE',
            ]);
        }
    }

    private function dadosDoAtleta(string $nome, int $idade, string $sexo, string $periodo, int $i): array
    {
        // Nascimento pela idade no ano (regra das categorias): ano atual − idade, num dia qualquer do ano
        $nascimento = Carbon::create(now()->year - $idade, 1 + ($i * 5) % 12, 1 + ($i * 7) % 28);

        return [
            'nome_atleta'            => $nome,
            'data_nasc_atleta'       => $nascimento->toDateString(),
            'cpf_atleta'             => $this->cpf(1000 + $i),
            'rg_atleta'              => sprintf('%02d.%03d.%03d-%d', 30 + $i, 100 + $i * 13, 200 + $i * 7, $i % 10),
            'sexo_atleta'            => $sexo,
            'escola_atleta'          => self::ESCOLAS[$i % count(self::ESCOLAS)],
            'serie_atleta'           => max(1, min(9, $idade - 5)) . 'º ano',
            'periodo_escolar_atleta' => $periodo,
            'peso_atleta'            => 25 + ($idade - 9) * 5 + $i % 4,
            'altura_atleta'          => round(1.30 + ($idade - 9) * 0.05 + ($i % 3) * 0.02, 2),
            'foto_atleta'            => 'default-player.jpg',
            'descricao_atleta'       => '',
        ];
    }

    private function criarEndereco(int $i): Endereco
    {
        return Endereco::create([
            'rua_endereco'    => self::RUAS[$i % count(self::RUAS)],
            'numero_endereco' => (string) (100 + $i * 17),
            'bairro_endereco' => self::BAIRROS[$i % count(self::BAIRROS)],
            'cep_endereco'    => sprintf('08%03d-%03d', 100 + $i, $i * 11 % 1000),
            'cidade_endereco' => 'São Paulo',
            'estado_endereco' => 'SP',
        ]);
    }

    private function criarResponsavel(string $nome, Endereco $endereco, int $i): Responsavel
    {
        $telefone = sprintf('(11) 9%04d-%04d', 8100 + $i, 2000 + $i * 37);

        return Responsavel::create([
            'nome_responsavel'       => $nome,
            'cpf_responsavel'        => $this->cpf(5000 + $i),
            'rg_responsavel'         => '',
            'telefone_responsavel'   => $telefone,
            'whatsapp_responsavel'   => $telefone,
            'email_responsavel'      => $this->email($nome),
            'assinatura_responsavel' => '',
            'aceite_responsavel'     => 'N',
            'id_endereco'            => $endereco->id_endereco,
        ]);
    }

    // "Theo Barbosa" => theo.barbosa@exemplo.com (domínio reservado para exemplos: nenhum e-mail sai de verdade)
    private function email(string $nome): string
    {
        return Str::slug($nome, '.') . '@exemplo.com';
    }

    // CPF fictício com os dígitos verificadores válidos, formatado (000.000.000-00), diferente para cada $semente
    private function cpf(int $semente): string
    {
        $base = str_split(sprintf('%09d', 123456789 + $semente * 7919));
        foreach ([10, 11] as $peso) {
            $soma = 0;
            foreach ($base as $k => $digito) {
                $soma += (int) $digito * ($peso - $k);
            }
            $base[] = (string) (($soma * 10) % 11 % 10);
        }
        $d = implode('', $base);

        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    // ── Grade real (professor, 09/10/2026) ─────────────────────────────────

    // Terça e quinta, 9h–11h e 14h–17h, sem categoria (todos juntos, cada um no turno oposto ao da escola);
    // sábado 13h–17h livre (treino, jogo interno ou exercício), para todos: fim de semana não tem turno. Os jogos
    // de sábado (amistosos e campeonatos) nascem na tela de Jogos
    private function criarGrade(): void
    {
        foreach (['terca', 'quinta'] as $dia) {
            GradeTreino::create(['dia_semana_grade_treino' => $dia, 'categoria_grade_treino' => 'Treino da manhã',
                'tipo_grade_treino' => 'TREINO', 'horario_inicio_grade_treino' => '09:00', 'horario_fim_grade_treino' => '11:00',
                'local_grade_treino' => 'Campo da AACJ', 'ordem_grade_treino' => 1, 'status_grade_treino' => 'ATIVO']);
            GradeTreino::create(['dia_semana_grade_treino' => $dia, 'categoria_grade_treino' => 'Treino da tarde',
                'tipo_grade_treino' => 'TREINO', 'horario_inicio_grade_treino' => '14:00', 'horario_fim_grade_treino' => '17:00',
                'local_grade_treino' => 'Campo da AACJ', 'ordem_grade_treino' => 2, 'status_grade_treino' => 'ATIVO']);
        }

        GradeTreino::create(['dia_semana_grade_treino' => 'sabado', 'categoria_grade_treino' => 'Treino livre ou jogo interno',
            'tipo_grade_treino' => 'LIVRE', 'horario_inicio_grade_treino' => '13:00', 'horario_fim_grade_treino' => '17:00',
            'horario_obs_grade_treino' => 'Amistosos e campeonatos', 'local_grade_treino' => 'Campo da AACJ',
            'ordem_grade_treino' => 1, 'status_grade_treino' => 'ATIVO']);
    }

    // ── Times, campeonato e amistoso ───────────────────────────────────────

    // Um time da AACJ por categoria; os adversários têm um cadastro só (entram em cada categoria em que jogam)
    private function criarTimes(): array
    {
        $time = fn (string $nome, string $tipo, string $categoria, string $logo) => Time::create([
            'nome_time' => $nome, 'tipo_time' => $tipo, 'logo_time' => $logo, 'status_time' => 'ATIVO',
            'id_categoria' => $this->categoria($categoria)->id_categoria,
        ]);

        return [
            'sub11'     => $time('AACJ Sub-11', 'INTERNO', 'Sub-11', 'time-azul.png'),
            'sub13'     => $time('AACJ Sub-13', 'INTERNO', 'Sub-13', 'time-verde.png'),
            'sub15'     => $time('AACJ Sub-15', 'INTERNO', 'Sub-15', 'time-preto.jpg'),
            'botafogo'  => $time('Botafogo da Vila', 'EXTERNO', 'Sub-15', 'time-visitante.png'),
            'uniao'     => $time('União Esportiva', 'EXTERNO', 'Sub-13', 'time-visitante.jpg'),
            'estrela'   => $time('Estrela FC', 'EXTERNO', 'Sub-15', 'time-visitante.png'),
        ];
    }

    // Copa com Sub-13 e Sub-15, uma chave de três em cada (passam 2), convocação com camisa e três rodadas:
    // duas já jogadas (com placar) e a próxima publicada, sem placar
    private function criarCampeonato(array $t, array $atletas): void
    {
        [$rodada1, $rodada2, $rodada3] = $this->rodadas();
        $sub13 = $this->categoria('Sub-13')->id_categoria;
        $sub15 = $this->categoria('Sub-15')->id_categoria;

        $copa = Campeonato::create([
            'nome_campeonato'        => 'Copa Regional de Base ' . $rodada1->year,
            'organizador_campeonato' => 'Liga Regional de Futebol de Base',
            'descricao_campeonato'   => 'Campeonato de base com as categorias Sub-13 e Sub-15, em chaves de três times.',
            'tipo_campeonato'        => 'COPA',
            'data_inicio_campeonato' => $rodada1->copy()->startOfDay(),
            'data_fim_campeonato'    => $rodada3->copy()->addWeeks(5)->endOfDay(),
            'local_evento'           => 'Arena Municipal',
            'logo_evento'            => 'logo.png',
            'banner_evento'          => 'banner.jpg',
            'id_categoria'           => Campeonato::categoriaDeReferencia([$sub13, $sub15]),
            'status_campeonato'      => 'ATIVO',
        ]);

        $participantes = [
            $sub13 => [$t['sub13']->id_time, $t['botafogo']->id_time, $t['uniao']->id_time],
            $sub15 => [$t['sub15']->id_time, $t['botafogo']->id_time, $t['estrela']->id_time],
        ];
        $copa->gravarCategoriasEParticipantes([$sub13, $sub15], $participantes);

        foreach ($participantes as $idCategoria => $idsTimes) {
            $chave = CampeonatoGrupo::create(['id_campeonato' => $copa->id_campeonato, 'id_categoria' => $idCategoria,
                'nome_campeonato_grupo' => 'Chave A']);
            $copa->definirGrupos($idCategoria, array_fill_keys($idsTimes, $chave->id_campeonato_grupo));
            $copa->definirClassificados($idCategoria, 2);
        }

        $this->convocar($copa, $t['sub13'], self::CONVOCACAO_SUB13, $atletas);
        $this->convocar($copa, $t['sub15'], self::CONVOCACAO_SUB15, $atletas);

        // Sub-13 às 13:00 e Sub-15 às 14:30: o Samuel, convocado nas duas, não tem conflito de horário
        $jogo = fn ($cat, $casa, $visit, $dia, $ini, $fim, $placar = null) => $this->criarJogo([
            'id_campeonato' => $copa->id_campeonato, 'fase_jogo' => 'GRUPOS',
            'id_time_casa'  => $t[$casa]->id_time, 'id_time_visitante' => $t[$visit]->id_time,
        ], $cat, $dia, $ini, $fim, 'Arena Municipal', $placar);

        $jogo($sub13, 'sub13', 'uniao', $rodada1, '13:00', '14:10', [2, 1]);
        $jogo($sub15, 'sub15', 'botafogo', $rodada1, '14:30', '15:40', [3, 1]);
        $jogo($sub13, 'botafogo', 'uniao', $rodada2, '13:00', '14:10', [1, 1]);
        $jogo($sub15, 'estrela', 'botafogo', $rodada2, '14:30', '15:40', [2, 2]);
        $jogo($sub13, 'sub13', 'botafogo', $rodada3, '13:00', '14:10');
        $jogo($sub15, 'estrela', 'sub15', $rodada3, '14:30', '15:40');
    }

    // Mesmas conferências da tela Convocados (tudo ou nada); erro aqui é erro nos dados acima
    private function convocar(Campeonato $copa, Time $time, array $camisasPorNome, array $atletas): void
    {
        $camisas = [];
        foreach ($camisasPorNome as $nome => $camisa) {
            $camisas[$atletas[$nome]->id_atleta] = $camisa;
        }

        if ($erros = $copa->errosDaConvocacao($time, $camisas)) {
            throw new \RuntimeException("Convocação do {$time->nome_time}: " . implode(' ', $erros));
        }
        $copa->convocar($time, $camisas, $this->idUsuario);
    }

    // Amistoso da AACJ Sub-11 contra um adversário, em rascunho, com os meninos do Sub-11 escolhidos no jogo
    private function criarAmistoso(array $t, array $atletas): void
    {
        $dia = $this->rodadas()[2]->copy()->addWeeks(2);
        $jogo = $this->criarJogo(['id_campeonato' => null, 'fase_jogo' => null,
            'id_time_casa' => $t['sub11']->id_time, 'id_time_visitante' => $t['uniao']->id_time],
            $this->categoria('Sub-11')->id_categoria, $dia, '13:00', '14:10', 'Campo da AACJ', publicar: false);

        foreach (['Arthur Mendes', 'Davi Rocha', 'Gael Nogueira', 'Heitor Campos', 'Theo Barbosa', 'Ravi Teixeira'] as $nome) {
            $jogo->evento->inscrever($atletas[$nome]->id_atleta, 'INDIVIDUAL', $this->idUsuario, $t['sub11']->id_time);
        }
    }

    /**
     * Cria o jogo como a tela de Jogos: evento JOGO em rascunho (o jogo sem time da AACJ nasce publicado), os
     * convocados inscritos e escalados, o placar e a publicação. Jogo que já aconteceu é publicado sem avisar;
     * o próximo avisa os convocados (como o "Publicar e avisar os atletas").
     */
    private function criarJogo(array $dadosJogo, int $idCategoria, Carbon $dia, string $inicio, string $fim, string $local,
        ?array $placar = null, bool $publicar = true): Jogo
    {
        $semAcj = ! Jogo::idsInternos([$dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']]);

        $evento = EventoCalendario::criarPor($this->idUsuario, [
            'titulo_evento_calendario'         => Jogo::tituloPara($dadosJogo['id_time_casa'], $dadosJogo['id_time_visitante']),
            'tipo_evento_calendario'           => 'JOGO',
            'id_categoria'                     => $idCategoria,
            'data_evento_calendario'           => $dia->toDateString(),
            'horario_inicio_evento_calendario' => $inicio,
            'horario_fim_evento_calendario'    => $fim,
            'local_evento_calendario'          => $local,
            'status_evento_calendario'         => 'ATIVO',
        ], inscreverCategoria: false, rascunho: ! $semAcj);

        $jogo = Jogo::create([...$dadosJogo, 'id_evento' => $evento->id_evento_calendario,
            'placar_time_casa_jogos' => $placar[0] ?? null, 'placar_time_visitante_jogos' => $placar[1] ?? null]);
        $jogo->setRelation('evento', $evento);
        $evento->setRelation('jogo', $jogo);
        $jogo->inscreverElenco($this->idUsuario);

        if ($publicar && ! $semAcj) {
            $evento->publicar($this->idUsuario);
        }

        return $jogo;
    }

    // Os dois últimos sábados antes de hoje (rodadas jogadas) e o primeiro depois de hoje (próxima rodada).
    // Hoje nunca entra: num sábado, o jogo de hoje poderia já ter acontecido (publicado sem avisar) ou não
    private function rodadas(): array
    {
        $ultimo = now()->startOfDay()->subDay();
        while (! $ultimo->isSaturday()) {
            $ultimo->subDay();
        }

        $proximo = now()->startOfDay()->addDay();
        while (! $proximo->isSaturday()) {
            $proximo->addDay();
        }

        return [$ultimo->copy()->subWeek(), $ultimo, $proximo];
    }

    private function categoria(string $nome, string $sexo = 'M'): Categoria
    {
        return $this->categorias["{$nome}|{$sexo}"] ?? throw new \RuntimeException("Categoria {$nome} {$sexo} não encontrada.");
    }
}
