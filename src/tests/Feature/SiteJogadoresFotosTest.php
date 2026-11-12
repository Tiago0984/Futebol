<?php

namespace Tests\Feature;

use App\Models\Atleta;
use DOMDocument;
use DOMXPath;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class SiteJogadoresFotosTest extends TestCase
{
    use CriaDadosDeAtleta, RefreshBancoDeTestes;

    public function test_cartao_e_detalhe_usam_a_url_da_foto_de_cada_origem(): void
    {
        $casos = [
            ['apresentacao/joana.png', 'storage/apresentacao/joana.png'],
            ['atletas/foto-atleta-20261110-090000-abc123.jpg', 'storage/atletas/foto-atleta-20261110-090000-abc123.jpg'],
            ['foto-atleta-20261110-090000-def456.jpg', 'futebol/images/our-teams/foto-atleta-20261110-090000-def456.jpg'],
            [null, 'futebol/images/our-teams/default-player.jpg'],
            ['default-player.jpg', 'futebol/images/our-teams/default-player.jpg'],
        ];
        $esperadas = [];
        foreach ($casos as [$foto, $caminho]) {
            $id = $this->criarAtleta($this->nascidoComIdade(12), 'F', 'ATIVO');
            Atleta::findOrFail($id)->update(['foto_atleta' => $foto]);
            $esperadas[$id] = asset($caminho);
        }

        $html = $this->get(route('jogadores.vitrine'))->assertOk()->getContent();
        $dom = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xpath = new DOMXPath($dom);
        foreach ($esperadas as $id => $url) {
            $cartao = $xpath->query("//article[@data-atleta-id='{$id}']//figure/img");
            $detalhe = $xpath->query("//div[@id='modalAtleta{$id}']//img[contains(@class, 'profile-picture')]");
            $this->assertSame(1, $cartao->length);
            $this->assertSame(1, $detalhe->length);
            $this->assertSame($url, $cartao->item(0)->getAttribute('src'));
            $this->assertSame($url, $detalhe->item(0)->getAttribute('src'));
        }
    }
}
