<?php

namespace App\Services;

use App\Models\Atleta;
use App\Models\AtletaAfastamento;
use App\Models\Banner;
use App\Models\Campeonato;
use App\Models\ComissaoTecnica;
use App\Models\EventoCalendario;
use App\Models\FaleConosco;
use App\Models\Galeria;
use App\Models\Jogo;
use App\Models\Local;
use App\Models\Noticia;
use App\Models\Time;
use App\Models\Video;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dados da página inicial do painel (09/11/2026): o que precisa de atenção, os números do dia, a agenda dos
 * próximos dias, os últimos resultados e o acesso rápido, na mesma ordem do menu. Só leitura, com consultas
 * fixas (nenhuma por item da lista).
 */
class InicioDoPainel
{
    // Quantos dias à frente a agenda e o número "Próximos 7 dias" olham
    public const DIAS_DA_AGENDA = 7;

    // Itens na agenda e nos últimos resultados (o resto pelo "Ver todos")
    public const AGENDA_MAX = 8;
    public const RESULTADOS_MAX = 5;

    // Jogos citados por nome dentro de um item de atenção (rascunhos, jogos sem placar)
    public const JOGOS_NA_ATENCAO_MAX = 5;

    // Tarefa esperando na fila há mais que isto = o agendador do Plesk não está rodando (roda a cada minuto)
    public const FILA_PARADA_MINUTOS = 10;

    /**
     * Pendências, cada uma com o que fazer e onde: só as que têm algo (nada = "Tudo em dia" na tela).
     * Cada item: nivel (danger, warning, info), icone, texto, detalhe (opcional), url e rotulo do botão
     * (opcionais) e jogos (opcional: [[texto, url], ...]).
     */
    public function pendencias(): array
    {
        $itens = [];

        // Fila parada ou com falhas: e-mails do Fale conosco e assinaturas do site não saem
        $maisAntigo = DB::table('jobs')->min('available_at');
        if ($maisAntigo !== null && $maisAntigo < now()->subMinutes(self::FILA_PARADA_MINUTOS)->timestamp) {
            $itens[] = [
                'nivel'   => 'danger',
                'icone'   => 'hourglass-split',
                'texto'   => 'A fila de e-mails está parada há ' . Carbon::createFromTimestamp($maisAntigo)->locale('pt_BR')
                    ->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]) . '.',
                'detalhe' => 'Confira a tarefa agendada "Agendador Laravel" no Plesk (docs/agendador.md). Até ela rodar, os e-mails do Fale conosco e as assinaturas do site não saem.',
            ];
        }
        if ($falhas = DB::table('failed_jobs')->count()) {
            $itens[] = [
                'nivel'   => 'danger',
                'icone'   => 'x-octagon',
                'texto'   => "{$falhas} tarefa(s) da fila falharam depois de todas as tentativas.",
                'detalhe' => 'Geralmente é e-mail que não saiu (SMTP). O motivo fica no storage/logs/laravel.log do servidor.',
            ];
        }

        $matriculas = Atleta::where('status_atleta', 'PENDENTE')->count();
        if ($matriculas) {
            $itens[] = [
                'nivel' => 'warning', 'icone' => 'clipboard-check',
                'texto' => "{$matriculas} matrícula(s) aguardando aprovação.",
                'url'   => route('admin.matriculas.index'), 'rotulo' => 'Ver matrículas',
            ];
        }

        $fotos = Atleta::whereNotNull('foto_pendente_atleta')->count();
        if ($fotos) {
            $itens[] = [
                'nivel' => 'warning', 'icone' => 'person-bounding-box',
                'texto' => "{$fotos} foto(s) enviada(s) pelo app para aprovar.",
                'detalhe' => 'A foto nova só aparece no app e no site depois de aprovada.',
                'url'   => route('admin.atletas.index', ['status' => 'FOTO']), 'rotulo' => 'Aprovar fotos',
            ];
        }

        $naoLidas = FaleConosco::whereNull('data_leitura_fale_conosco')->count();
        if ($naoLidas) {
            $itens[] = [
                'nivel' => 'info', 'icone' => 'envelope',
                'texto' => "{$naoLidas} mensagem(ns) do Fale conosco não lida(s).",
                'url'   => route('admin.fale-conosco.index', ['leitura' => 'nao_lidas']), 'rotulo' => 'Ler mensagens',
            ];
        }
        $semEmail = FaleConosco::where('status_email_fale_conosco', 'FALHOU')->count();
        if ($semEmail) {
            $itens[] = [
                'nivel' => 'warning', 'icone' => 'envelope-exclamation',
                'texto' => "{$semEmail} mensagem(ns) do Fale conosco sem o e-mail de aviso (falha no envio).",
                'detalhe' => 'As mensagens estão salvas no painel: abra e responda pelo e-mail de quem escreveu.',
                'url'   => route('admin.fale-conosco.index'), 'rotulo' => 'Ver mensagens',
            ];
        }

        // Jogos em rascunho (não cancelados nem ocultos): ninguém foi avisado e ficam fora do app e do site
        $rascunhos = $this->jogos()->whereHas('evento', fn ($q) => $q
            ->whereNull('data_publicacao_evento_calendario')->where('status_evento_calendario', 'ATIVO'));
        $totalRascunhos = (clone $rascunhos)->count();
        if ($totalRascunhos) {
            $itens[] = [
                'nivel'   => 'warning', 'icone' => 'pencil-square',
                'texto'   => "{$totalRascunhos} jogo(s) em rascunho, ainda sem aviso aos atletas.",
                'detalhe' => 'Rascunho fica fora do app e do site. Revise a escalação e use "Publicar e avisar os atletas" na tela do jogo.',
                'jogos'   => $this->linhasDosJogos($rascunhos->limit(self::JOGOS_NA_ATENCAO_MAX)->get()),
                'mais'    => max(0, $totalRascunhos - self::JOGOS_NA_ATENCAO_MAX),
            ];
        }

        // Jogos que já aconteceram e estão sem placar (não entram na classificação nem nas estatísticas)
        $semPlacar = $this->jogos()->whereNull('placar_time_casa_jogos')
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', 'ATIVO')->concluidos());
        $totalSemPlacar = (clone $semPlacar)->count();
        if ($totalSemPlacar) {
            $itens[] = [
                'nivel'   => 'warning', 'icone' => '123',
                'texto'   => "{$totalSemPlacar} jogo(s) já realizado(s) sem placar.",
                'detalhe' => 'Sem o placar, o jogo não conta na classificação nem nas estatísticas do atleta.',
                'jogos'   => $this->linhasDosJogos($semPlacar->limit(self::JOGOS_NA_ATENCAO_MAX)->get()),
                'mais'    => max(0, $totalSemPlacar - self::JOGOS_NA_ATENCAO_MAX),
            ];
        }

        $semPeriodo = Atleta::where('status_atleta', 'ATIVO')->whereNull('periodo_escolar_atleta')->count();
        if ($semPeriodo) {
            $itens[] = [
                'nivel' => 'warning', 'icone' => 'clock',
                'texto' => "{$semPeriodo} atleta(s) ativo(s) sem período escolar.",
                'detalhe' => 'Sem o período, o atleta não entra em nenhum treino gerado pela grade.',
                'url'   => route('admin.atletas.index', ['status' => 'SEM_PERIODO']), 'rotulo' => 'Ver atletas',
            ];
        }

        $locaisSoltos = Local::textosSoltos()->count();
        if ($locaisSoltos) {
            $itens[] = [
                'nivel' => 'info', 'icone' => 'geo-alt',
                'texto' => "{$locaisSoltos} local(is) escrito(s) à mão, ainda sem cadastro ligado.",
                'detalhe' => 'Sem o cadastro, o app não mostra o endereço nem o "Abrir mapa".',
                'url'   => route('admin.locais.index'), 'rotulo' => 'Ver locais',
            ];
        }

        return $itens;
    }

    // Os quatro números do topo (Matrículas pendentes repete a pendência de propósito: é o número mais olhado)
    public function numeros(): array
    {
        $hoje = today()->toDateString();

        return [
            'atletas_ativos'       => Atleta::where('status_atleta', 'ATIVO')->count(),
            'afastados_hoje'       => AtletaAfastamento::whereDate('data_inicio_atleta_afastamento', '<=', $hoje)
                ->whereDate('data_fim_atleta_afastamento', '>=', $hoje)
                ->whereHas('atleta', fn ($q) => $q->where('status_atleta', 'ATIVO'))
                ->distinct()->count('id_atleta'),
            'atividades_semana'    => $this->daAgenda()->where('status_evento_calendario', 'ATIVO')->count(),
            'campeonatos'          => Campeonato::ativos()->count(),
            'matriculas_pendentes' => Atleta::where('status_atleta', 'PENDENTE')->count(),
        ];
    }

    // Próximas atividades (de agora até daqui a 7 dias, sem as ocultas e as que já terminaram), com os inscritos ativos
    public function agenda(): Collection
    {
        return $this->daAgenda()
            ->with(['jogo.timeCasa', 'jogo.timeVisitante', 'jogo.campeonato', 'categoria'])
            ->withCount(['inscricoes as inscritos_ativos' => fn ($q) => $q
                ->whereHas('atleta', fn ($a) => $a->where('status_atleta', 'ATIVO'))])
            ->orderBy('data_evento_calendario')->orderBy('horario_inicio_evento_calendario')->orderBy('id_evento_calendario')
            ->limit(self::AGENDA_MAX)
            ->get();
    }

    // Últimos jogos que já aconteceram (sem os ocultos), do mais recente ao mais antigo, com ou sem placar
    public function resultados(): Collection
    {
        return $this->jogos('desc')
            ->whereHas('evento', fn ($q) => $q->where('status_evento_calendario', '<>', 'INATIVO')->concluidos())
            ->limit(self::RESULTADOS_MAX)
            ->get();
    }

    /**
     * Acesso rápido, nos mesmos grupos e na mesma ordem do menu. Cada atalho: rótulo, ícone, url,
     * contador (opcional) e alerta (contador em destaque, opcional).
     */
    public function atalhos(array $numeros): array
    {
        $naoLidas = FaleConosco::whereNull('data_leitura_fale_conosco')->count();

        return [
            'Conteúdo do site' => [
                'cor' => 'site',
                'itens' => [
                    ['Notícias', 'newspaper', route('admin.noticias.index'), Noticia::count()],
                    ['Banners', 'image', route('admin.banners.index'), Banner::count()],
                    ['Galeria', 'images', route('admin.galeria.index'), Galeria::count()],
                    ['Vídeos', 'camera-video', route('admin.videos.index'), Video::count()],
                ],
            ],
            'Esporte' => [
                'cor' => 'esporte',
                'itens' => [
                    ['Calendário', 'calendar3', route('admin.calendario.index')],
                    ['Campeonatos', 'trophy', route('admin.campeonatos.index'), $numeros['campeonatos']],
                    ['Amistosos', 'hand-thumbs-up', EventoCalendario::urlDoRamo('amistosos')],
                    ['Individuais', 'person-check', EventoCalendario::urlDoRamo('individuais')],
                    ['Jogos', 'flag-fill', route('admin.jogos.index')],
                    ['Grade de treino', 'clock', route('admin.calendario.index', ['tab' => 'grade'])],
                    ['Notificações', 'bell', route('admin.notificacoes.index')],
                ],
            ],
            'Cadastros' => [
                'cor' => 'cadastros',
                'itens' => [
                    ['Categorias', 'tags', route('admin.categorias.index')],
                    ['Times', 'shield-fill', route('admin.times.index'), Time::count()],
                    ['Comissão técnica', 'person-vcard', route('admin.comissao.index'), ComissaoTecnica::ativos()->count()],
                    ['Locais', 'geo-alt', route('admin.locais.index'), Local::ativos()->count()],
                ],
            ],
            'Pessoas' => [
                'cor' => 'pessoas',
                'itens' => [
                    ['Atletas', 'person-badge', route('admin.atletas.index'), $numeros['atletas_ativos']],
                    ['Matrículas', 'clipboard-check', route('admin.matriculas.index'), null, $numeros['matriculas_pendentes']],
                    ['Fale conosco', 'envelope', route('admin.fale-conosco.index'), null, $naoLidas],
                ],
            ],
        ];
    }

    // Eventos da agenda do dashboard: não ocultos, ainda não terminados, até daqui a DIAS_DA_AGENDA dias
    private function daAgenda()
    {
        return EventoCalendario::query()
            ->where('status_evento_calendario', '<>', 'INATIVO')
            ->naoConcluidos()
            ->whereDate('data_evento_calendario', '<=', today()->addDays(self::DIAS_DA_AGENDA)->toDateString());
    }

    // Jogos com o evento, os times e o campeonato, na ordem da data e do horário do evento (a data fica no evento)
    private function jogos(string $direcao = 'asc')
    {
        $doEvento = fn (string $coluna) => EventoCalendario::select($coluna)
            ->whereColumn('id_evento_calendario', 'tbl_jogos.id_evento');

        return Jogo::with(['evento', 'timeCasa', 'timeVisitante', 'campeonato'])
            ->orderBy($doEvento('data_evento_calendario'), $direcao)
            ->orderBy($doEvento('horario_inicio_evento_calendario'), $direcao)
            ->orderBy('id_jogo', $direcao);
    }

    // "Botafogo x AACJ Masculino · sáb, 14/11" com o link da tela do jogo
    private function linhasDosJogos(Collection $jogos): array
    {
        return $jogos->map(fn ($j) => [
                ($j->timeCasa?->nome_time ?? '?') . ' x ' . ($j->timeVisitante?->nome_time ?? '?')
                    . ($j->evento ? ' · ' . $j->evento->data_evento_calendario->locale('pt_BR')->isoFormat('ddd, DD/MM') : ''),
                route('admin.calendario.eventos.show', $j->id_evento),
            ])->values()->all();
    }
}
