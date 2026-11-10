<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Ícone do painel (10/11/2026): o escudo da AACJ com uma engrenagem, no login e nas telas do painel, separado do ícone
 * do site.
 */
class PainelIconeTest extends TestCase
{
    use RefreshBancoDeTestes;

    public function test_login_e_painel_tem_o_icone_do_painel(): void
    {
        foreach (['favicon-32.png', 'favicon-192.png', 'apple-touch-icon.png'] as $arquivo) {
            $this->assertFileExists(public_path("dash/favicon/{$arquivo}"));
        }

        $icone = '<link rel="icon" type="image/png" sizes="32x32" href="' . asset('dash/favicon/favicon-32.png') . '" />';

        $this->get(route('admin.login'))->assertOk()->assertSee($icone, false);
        $this->actingAs(User::factory()->admin()->create(), 'admin')
            ->get(route('admin.dashboard'))->assertOk()
            ->assertSee($icone, false)
            ->assertSee('<meta name="theme-color" content="#e31c1c"', false)
            ->assertDontSee('AdminLTE v4 | Dashboard');
    }
}
