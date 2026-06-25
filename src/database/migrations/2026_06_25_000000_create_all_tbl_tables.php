<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_endereco', function (Blueprint $table) {
            $table->id('id_endereco');
            $table->string('rua_endereco');
            $table->string('numero_endereco');
            $table->string('bairro_endereco');
            $table->string('complemento_endereco')->nullable();
            $table->string('cep_endereco', 9);
            $table->string('cidade_endereco');
            $table->string('estado_endereco', 2);
        });

        Schema::create('tbl_categoria', function (Blueprint $table) {
            $table->id('id_categoria');
            $table->string('nome_categoria');
            $table->integer('idade_min_categoria')->nullable();
            $table->integer('idade_max_categoria')->nullable();
            $table->string('sexo_categoria', 1)->nullable();
        });

        Schema::create('tbl_responsavel', function (Blueprint $table) {
            $table->id('id_responsavel');
            $table->string('nome_responsavel');
            $table->string('cpf_responsavel', 14)->nullable();
            $table->string('rg_responsavel', 20)->nullable();
            $table->string('telefone_responsavel', 20)->nullable();
            $table->string('whatsapp_responsavel', 20)->nullable();
            $table->text('assinatura_responsavel')->nullable();
            $table->boolean('aceite_responsavel')->default(false);
            $table->unsignedBigInteger('id_endereco')->nullable();
            $table->foreign('id_endereco')->references('id_endereco')->on('tbl_endereco')->nullOnDelete();
        });

        Schema::create('tbl_atletas', function (Blueprint $table) {
            $table->id('id_atleta');
            $table->string('nome_atleta');
            $table->date('data_nasc_atleta');
            $table->string('rg_atleta', 20)->nullable();
            $table->string('cpf_atleta', 14)->nullable();
            $table->string('numero_matricula_atleta')->nullable();
            $table->string('posicao_atleta', 50)->nullable();
            $table->string('telefone_atleta', 20)->nullable();
            $table->decimal('peso_atleta', 5, 2)->nullable();
            $table->decimal('altura_atleta', 4, 2)->nullable();
            $table->string('sexo_atleta', 1)->nullable();
            $table->string('escola_atleta')->nullable();
            $table->string('serie_atleta')->nullable();
            $table->string('sala_atleta')->nullable();
            $table->string('periodo_escolar_atleta')->nullable();
            $table->text('descricao_atleta')->nullable();
            $table->string('foto_atleta')->nullable();
            $table->string('email_atleta')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->string('status_atleta')->default('pendente');
            $table->string('token_cadastro')->nullable()->unique();
            $table->unsignedBigInteger('id_endereco')->nullable();
            $table->foreign('id_endereco')->references('id_endereco')->on('tbl_endereco')->nullOnDelete();
        });

        Schema::create('tbl_atleta_responsavel', function (Blueprint $table) {
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_responsavel');
            $table->string('grau_parentesco_responsavel')->nullable();
            $table->primary(['id_atleta', 'id_responsavel']);
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas')->cascadeOnDelete();
            $table->foreign('id_responsavel')->references('id_responsavel')->on('tbl_responsavel')->cascadeOnDelete();
        });

        Schema::create('tbl_categoria_atleta', function (Blueprint $table) {
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_categoria');
            $table->date('data_inicio_categoria_atleta')->nullable();
            $table->date('data_fim_categoria_atleta')->nullable();
            $table->date('data_atualizacao_categoria_atleta')->nullable();
            $table->string('status_categoria_atleta')->nullable();
            $table->text('observacao_categoria_atleta')->nullable();
            $table->primary(['id_atleta', 'id_categoria']);
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas')->cascadeOnDelete();
            $table->foreign('id_categoria')->references('id_categoria')->on('tbl_categoria')->cascadeOnDelete();
        });

        Schema::create('tbl_time', function (Blueprint $table) {
            $table->id('id_time');
            $table->string('logo_time')->nullable();
            $table->string('nome_time');
            $table->string('tipo_time')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->foreign('id_categoria')->references('id_categoria')->on('tbl_categoria')->nullOnDelete();
        });

        Schema::create('tbl_atleta_time', function (Blueprint $table) {
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_time');
            $table->string('status_atleta_time')->nullable();
            $table->string('posicao_atleta_time')->nullable();
            $table->string('camisa_atleta_time')->nullable();
            $table->integer('gols_atleta_time')->default(0);
            $table->integer('defesas_atleta_time')->default(0);
            $table->integer('jogos_atleta_time')->default(0);
            $table->boolean('convocacao_atleta_time')->default(false);
            $table->primary(['id_atleta', 'id_time']);
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas')->cascadeOnDelete();
            $table->foreign('id_time')->references('id_time')->on('tbl_time')->cascadeOnDelete();
        });

        Schema::create('tbl_campeonato', function (Blueprint $table) {
            $table->id('id_campeonato');
            $table->string('logo_evento')->nullable();
            $table->string('banner_evento')->nullable();
            $table->string('nome_campeonato');
            $table->string('organizador_campeonato')->nullable();
            $table->text('descricao_campeonato')->nullable();
            $table->string('tipo_campeonato')->nullable();
            $table->datetime('data_inicio_campeonato')->nullable();
            $table->datetime('data_fim_campeonato')->nullable();
            $table->string('local_evento')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->foreign('id_categoria')->references('id_categoria')->on('tbl_categoria')->nullOnDelete();
        });

        Schema::create('tbl_campeonato_time', function (Blueprint $table) {
            $table->unsignedBigInteger('id_campeonato');
            $table->unsignedBigInteger('id_time');
            $table->primary(['id_campeonato', 'id_time']);
            $table->foreign('id_campeonato')->references('id_campeonato')->on('tbl_campeonato')->cascadeOnDelete();
            $table->foreign('id_time')->references('id_time')->on('tbl_time')->cascadeOnDelete();
        });

        Schema::create('tbl_jogos', function (Blueprint $table) {
            $table->id('id_jogo');
            $table->unsignedBigInteger('id_campeonato');
            $table->unsignedBigInteger('id_time_casa');
            $table->unsignedBigInteger('id_time_visitante');
            $table->integer('placar_time_casa_jogos')->nullable();
            $table->integer('placar_time_visitante_jogos')->nullable();
            $table->datetime('data_jogo');
            $table->foreign('id_campeonato')->references('id_campeonato')->on('tbl_campeonato')->cascadeOnDelete();
            $table->foreign('id_time_casa')->references('id_time')->on('tbl_time');
            $table->foreign('id_time_visitante')->references('id_time')->on('tbl_time');
        });

        Schema::create('tbl_cartoes', function (Blueprint $table) {
            $table->id('id_cartao');
            $table->unsignedBigInteger('id_campeonato');
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_jogo');
            $table->string('tipo_cartao', 10);
            $table->datetime('data_cartao');
            $table->foreign('id_campeonato')->references('id_campeonato')->on('tbl_campeonato');
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas');
            $table->foreign('id_jogo')->references('id_jogo')->on('tbl_jogos');
        });

        Schema::create('tbl_inscricao', function (Blueprint $table) {
            $table->id('id_inscricao');
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_categoria');
            $table->datetime('data_inscricao');
            $table->string('status_inscricao');
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas');
            $table->foreign('id_categoria')->references('id_categoria')->on('tbl_categoria');
        });

        Schema::create('tbl_autorizacoes', function (Blueprint $table) {
            $table->id('id_autorizacao');
            $table->unsignedBigInteger('id_atleta');
            $table->unsignedBigInteger('id_responsavel');
            $table->datetime('data_assinatura_autorizacao')->nullable();
            $table->string('token_assinatura')->nullable();
            $table->string('status_autorizacao');
            $table->foreign('id_atleta')->references('id_atleta')->on('tbl_atletas');
            $table->foreign('id_responsavel')->references('id_responsavel')->on('tbl_responsavel');
        });

        Schema::create('tbl_noticias', function (Blueprint $table) {
            $table->id('id_noticia');
            $table->string('titulo_noticia');
            $table->text('conteudo_noticia');
            $table->string('foto_noticia')->nullable();
            $table->string('categoria_noticia')->nullable();
            $table->datetime('data_publicacao_noticia');
            $table->string('autor_noticia')->nullable();
            $table->string('status_noticia')->default('publicado');
        });

        Schema::create('tbl_banner', function (Blueprint $table) {
            $table->id('id_banner');
            $table->string('titulo_banner');
            $table->string('subtitulo_banner')->nullable();
            $table->string('foto_banner');
            $table->integer('ordem_banner')->default(0);
            $table->string('status_banner')->default('ativo');
            $table->datetime('criado_em')->nullable();
        });

        Schema::create('tbl_grade_treino', function (Blueprint $table) {
            $table->id('id_grade_treino');
            $table->string('dia_semana_grade_treino', 20);
            $table->string('categoria_grade_treino', 50)->nullable();
            $table->string('tipo_grade_treino', 30)->nullable();
            $table->time('horario_inicio_grade_treino');
            $table->time('horario_fim_grade_treino');
            $table->string('horario_obs_grade_treino')->nullable();
            $table->string('local_grade_treino')->nullable();
            $table->integer('ordem_grade_treino')->default(0);
            $table->string('status_grade_treino', 20)->default('ativo');
        });

        Schema::create('tbl_evento_calendario', function (Blueprint $table) {
            $table->id('id_evento_calendario');
            $table->string('titulo_evento_calendario');
            $table->text('descricao_evento_calendario')->nullable();
            $table->string('tipo_evento_calendario', 50);
            $table->string('subtipo_evento_calendario', 50)->nullable();
            $table->date('data_evento_calendario');
            $table->time('horario_inicio_evento_calendario')->nullable();
            $table->time('horario_fim_evento_calendario')->nullable();
            $table->string('local_evento_calendario')->nullable();
            $table->boolean('destaque_evento_calendario')->default(false);
            $table->string('status_evento_calendario', 20)->default('ativo');
        });

        Schema::create('tbl_galeria', function (Blueprint $table) {
            $table->id('id_galeria');
            $table->string('titulo_galeria');
            $table->string('foto_galeria');
            $table->string('categoria_galeria')->nullable();
            $table->integer('ordem_galeria')->default(0);
            $table->string('status_galeria', 20)->default('ativo');
            $table->datetime('criado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_galeria');
        Schema::dropIfExists('tbl_evento_calendario');
        Schema::dropIfExists('tbl_grade_treino');
        Schema::dropIfExists('tbl_banner');
        Schema::dropIfExists('tbl_noticias');
        Schema::dropIfExists('tbl_autorizacoes');
        Schema::dropIfExists('tbl_inscricao');
        Schema::dropIfExists('tbl_cartoes');
        Schema::dropIfExists('tbl_jogos');
        Schema::dropIfExists('tbl_campeonato_time');
        Schema::dropIfExists('tbl_campeonato');
        Schema::dropIfExists('tbl_atleta_time');
        Schema::dropIfExists('tbl_time');
        Schema::dropIfExists('tbl_categoria_atleta');
        Schema::dropIfExists('tbl_atleta_responsavel');
        Schema::dropIfExists('tbl_atletas');
        Schema::dropIfExists('tbl_responsavel');
        Schema::dropIfExists('tbl_categoria');
        Schema::dropIfExists('tbl_endereco');
    }
};
