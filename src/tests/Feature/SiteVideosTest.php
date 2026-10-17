<?php

namespace Tests\Feature;

use App\Models\Video;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Vídeos do site (home e campeonatos): o primeiro abre no player e, com mais de um, a faixa de miniaturas troca o
 * vídeo. Cada página mostra os ativos da sua seção e os marcados para as duas; o ao vivo vai para o player.
 */
class SiteVideosTest extends TestCase
{
    use RefreshBancoDeTestes;

    private function video(string $titulo, string $idYoutube, string $secao = 'ambas', array $extra = []): Video
    {
        return Video::create(array_merge([
            'titulo_video' => $titulo,
            'url_video'    => 'https://www.youtube.com/watch?v=' . $idYoutube,
            'status_video' => 'ATIVO',
            'secao_video'  => $secao,
        ], $extra));
    }

    public function test_sem_video_mostra_em_breve(): void
    {
        $this->get('/')->assertOk()->assertSee('Nenhum vídeo disponível no momento.');
        $this->get('/campeonato')->assertOk()->assertSee('Nenhum vídeo disponível no momento.');
    }

    public function test_um_video_so_fica_sem_a_faixa_de_miniaturas(): void
    {
        $this->video('Final da copa', 'aaaaaaaaaaa');

        $this->get('/')->assertOk()
            ->assertSee('FINAL DA COPA')
            ->assertSee('https://www.youtube.com/embed/aaaaaaaaaaa', false)
            ->assertDontSee('site-videos-lista', false);
    }

    public function test_varios_videos_o_mais_novo_no_player_e_todos_nas_miniaturas(): void
    {
        $this->video('Primeiro jogo', 'aaaaaaaaaaa');
        $this->video('Segundo jogo da seleção', 'bbbbbbbbbbb', 'ambas', ['descricao_video' => 'Melhores momentos']);

        $resposta = $this->get('/campeonato')->assertOk();
        $html = $resposta->getContent();

        $resposta->assertSee('site-videos-lista', false)
            ->assertSee('SEGUNDO JOGO DA SELEÇÃO')
            ->assertSee('Melhores momentos')
            ->assertSee('https://img.youtube.com/vi/aaaaaaaaaaa/hqdefault.jpg', false)
            ->assertSee('https://img.youtube.com/vi/bbbbbbbbbbb/hqdefault.jpg', false);

        // um iframe só, com o mais novo
        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertMatchesRegularExpression('#js-video-player"\s+src="https://www.youtube.com/embed/bbbbbbbbbbb"#', $html);
    }

    public function test_cada_pagina_mostra_so_a_sua_secao_e_os_de_ambas(): void
    {
        $this->video('So da home', 'hhhhhhhhhhh', 'home');
        $this->video('So dos campeonatos', 'ccccccccccc', 'campeonatos');
        $this->video('Das duas', 'ddddddddddd', 'ambas');
        $this->video('Inativo', 'iiiiiiiiiii', 'ambas', ['status_video' => 'INATIVO']);

        $this->get('/')->assertOk()
            ->assertSee('hhhhhhhhhhh')->assertSee('ddddddddddd')
            ->assertDontSee('ccccccccccc')->assertDontSee('iiiiiiiiiii');

        $this->get('/campeonato')->assertOk()
            ->assertSee('ccccccccccc')->assertSee('ddddddddddd')
            ->assertDontSee('hhhhhhhhhhh')->assertDontSee('iiiiiiiiiii');
    }

    public function test_ao_vivo_vai_para_o_player_mesmo_mais_antigo(): void
    {
        $this->video('Transmissão', 'vvvvvvvvvvv', 'home', ['ao_vivo_video' => 1]);
        $this->video('Mais novo', 'nnnnnnnnnnn', 'home');

        $html = $this->get('/')->assertOk()->assertSee('ASSISTA AO VIVO')->getContent();

        $this->assertMatchesRegularExpression('#js-video-player"\s+src="https://www.youtube.com/embed/vvvvvvvvvvv"#', $html);
    }

    public function test_mostra_no_maximo_o_limite_de_videos(): void
    {
        for ($i = 0; $i < Video::MAX_NO_SITE + 2; $i++) {
            $this->video('Video ' . $i, str_pad((string) $i, 11, 'x'));
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(Video::MAX_NO_SITE, substr_count($html, 'js-video-item'));
    }
}
