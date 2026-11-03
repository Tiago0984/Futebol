/* ==========================================================================
   ESCALAÇÃO NO CAMPO — tela do jogo no painel (03/11/2026; partial admin/calendario/_campo_do_time)
   Clicar numa vaga do campo e depois num atleta da lista põe o atleta nela (se ele já estava em outra vaga, os
   dois trocam); arrastar faz o mesmo: da lista para uma vaga, de uma vaga para outra (trocam) ou da vaga de volta
   para a lista (vira reserva). O "×" tira do campo. Quem não está no campo é reserva ao salvar. O campo tem as 11 vagas da
   formação, então nunca passa de 11 titulares (o servidor confere de novo). Trocar de formação mantém os
   atletas na mesma ordem das vagas (gol, defesa, meio, ataque).
   ========================================================================== */
(function () {
    'use strict';

    document.querySelectorAll('.js-campo').forEach(function (raiz) {
        var dadosEl = raiz.querySelector('.js-campo-dados');
        var form    = raiz.querySelector('.js-campo-form');
        if (!dadosEl || !form) return;

        var d       = JSON.parse(dadosEl.textContent);
        var vagasEl = raiz.querySelector('.js-campo-vagas');
        var listaEl = raiz.querySelector('.js-campo-atletas');
        var dicaEl  = raiz.querySelector('.js-campo-dica');
        var listaBox = raiz.querySelector('.js-campo-lista');
        var formEl  = raiz.querySelector('.js-campo-formacao');
        var linhas  = Array.prototype.slice.call(raiz.querySelectorAll('.js-campo-atleta'));
        var ids     = linhas.map(function (l) { return Number(l.dataset.id); });

        var estado = {
            formacao: d.formacao,
            vagas: d.ocupadas.map(function (id) { return id === null ? null : Number(id); }),
            escolhida: null,
            alterado: false
        };

        function vagasDaFormacao() { return d.formacoes[estado.formacao]; }
        function vagaDoAtleta(id) { return estado.vagas.indexOf(id); }
        function primeiraVazia() {
            var i = estado.vagas.indexOf(null);
            return i === -1 ? null : i;
        }
        function setor(posicao) { return posicao ? d.setores[posicao] : null; }

        function alterar() {
            estado.alterado = true;
            desenhar();
        }

        // Põe o atleta na vaga; se ele já estava em outra, troca de lugar com quem estava nela
        function colocar(id, alvo) {
            var origem = vagaDoAtleta(id);
            if (origem !== alvo) {
                if (origem !== -1) estado.vagas[origem] = estado.vagas[alvo];
                estado.vagas[alvo] = id;
            }
            estado.escolhida = primeiraVazia();
            alterar();
        }

        function tirar(i, escolher) {
            estado.vagas[i] = null;
            estado.escolhida = escolher ? i : primeiraVazia();
            alterar();
        }

        // Quanto o atleta serve na vaga: 3 = é a principal dele; 2 = joga nela ou numa aceita; 1 = mesmo setor
        function nota(id, sigla) {
            var vaga = d.vagas[sigla];
            var posicoes = d.atletas[id].posicoes;
            if (posicoes[0] === vaga.posicao) return 3;
            if (posicoes.some(function (p) { return vaga.aceitas.indexOf(p) !== -1; })) return 2;
            if (posicoes[0] && setor(posicoes[0]) === setor(vaga.posicao)) return 1;
            return 0;
        }

        function elemento(tag, classe, texto) {
            var el = document.createElement(tag);
            if (classe) el.className = classe;
            if (texto !== undefined && texto !== null) el.textContent = texto;
            return el;
        }

        function foto(atleta) {
            var caixa = elemento('span', 'escalacao-foto');
            if (atleta && atleta.foto) {
                var img = elemento('img');
                img.src = atleta.foto;
                img.alt = '';
                img.draggable = false;
                caixa.appendChild(img);
            } else if (atleta) {
                caixa.appendChild(document.createTextNode(atleta.iniciais));
            } else {
                caixa.appendChild(elemento('i', 'bi bi-plus-lg'));
            }
            return caixa;
        }

        function desenharVagas() {
            vagasEl.innerHTML = '';
            vagasDaFormacao().forEach(function (vaga, i) {
                var sigla  = vaga[0];
                var id     = estado.vagas[i];
                var atleta = id === null ? null : d.atletas[id];

                var caixa = elemento('div', 'escalacao-jogador admin-campo-vaga' + (atleta ? '' : ' vazio') + (estado.escolhida === i ? ' escolhida' : ''));
                caixa.style.setProperty('--x', vaga[1]);
                caixa.style.setProperty('--y', vaga[2]);

                var botao = elemento('button', 'admin-campo-vaga-botao');
                botao.type = 'button';
                botao.dataset.vaga = i;
                botao.draggable = !!atleta;
                botao.title = sigla + ' · ' + d.vagas[sigla].nome + (atleta ? ': ' + atleta.nome : ' (vazia)');
                var imagem = foto(atleta);
                imagem.appendChild(elemento('span', 'escalacao-sigla', sigla));
                botao.appendChild(imagem);
                var placa = elemento('span', 'escalacao-placa');
                if (atleta && atleta.camisa) placa.appendChild(elemento('b', null, atleta.camisa));
                placa.appendChild(document.createTextNode(atleta ? atleta.curto : 'Vazia'));
                botao.appendChild(placa);
                caixa.appendChild(botao);

                if (atleta) {
                    var tirar = elemento('button', 'admin-campo-tirar');
                    tirar.type = 'button';
                    tirar.dataset.tirar = i;
                    tirar.title = 'Tirar ' + atleta.nome + ' do campo (vira reserva)';
                    tirar.setAttribute('aria-label', tirar.title);
                    tirar.appendChild(elemento('i', 'bi bi-x'));
                    caixa.appendChild(tirar);
                }
                vagasEl.appendChild(caixa);
            });
        }

        // Lista: com uma vaga escolhida, quem joga nela vem primeiro (destacado); sem vaga, a ordem da tela
        function desenharLista() {
            var sigla = estado.escolhida === null ? null : vagasDaFormacao()[estado.escolhida][0];
            var ordem = linhas.slice();
            if (sigla) {
                ordem.sort(function (a, b) { return nota(Number(b.dataset.id), sigla) - nota(Number(a.dataset.id), sigla); });
            }
            ordem.forEach(function (linha) {
                var id = Number(linha.dataset.id);
                var i  = vagaDoAtleta(id);
                var status = linha.querySelector('.js-campo-status');
                linha.classList.toggle('no-campo', i !== -1);
                linha.classList.toggle('joga-na-vaga', !!sigla && nota(id, sigla) >= 2);
                status.textContent = i === -1 ? 'Reserva' : 'Titular · ' + vagasDaFormacao()[i][0];
                status.className = 'badge admin-campo-status js-campo-status ' + (i === -1 ? 'text-bg-secondary' : 'text-bg-success');
                listaEl.appendChild(linha);
            });
        }

        function desenharResumo() {
            var titulares = estado.vagas.filter(function (id) { return id !== null; }).length;
            raiz.querySelector('.js-campo-titulares').textContent = titulares;
            raiz.querySelector('.js-campo-reservas').textContent = ids.length - titulares;
            raiz.querySelector('.js-campo-pendente').classList.toggle('d-none', !estado.alterado);

            var gol = vagasDaFormacao().findIndex(function (vaga) { return vaga[0] === 'GOL'; });
            var avisos = raiz.querySelector('.js-campo-avisos');
            avisos.textContent = titulares > 0 && gol !== -1 && estado.vagas[gol] === null ? 'Sem goleiro titular.' : '';

            if (estado.escolhida !== null) {
                var sigla = vagasDaFormacao()[estado.escolhida][0];
                dicaEl.innerHTML = '';
                dicaEl.appendChild(document.createTextNode('Escolhendo o '));
                dicaEl.appendChild(elemento('strong', null, sigla + ' · ' + d.vagas[sigla].nome));
                dicaEl.appendChild(document.createTextNode(': clique num atleta (ou arraste-o até ela). Quem joga nessa posição aparece primeiro.'));
            } else if (titulares >= d.maximo) {
                dicaEl.textContent = 'Campo completo (' + d.maximo + ' titulares). Arraste um atleta para cima de uma posição, ou clique nela, para trocar quem está lá.';
            } else {
                dicaEl.textContent = 'Arraste o atleta até a posição do campo, ou clique na posição e depois no atleta (direto no atleta: a primeira vaga livre).';
            }
        }

        function desenhar() {
            desenharVagas();
            desenharLista();
            desenharResumo();
        }

        raiz.addEventListener('click', function (e) {
            var botaoTirar = e.target.closest('[data-tirar]');
            if (botaoTirar) {
                tirar(Number(botaoTirar.dataset.tirar), true);
                return;
            }

            var vaga = e.target.closest('[data-vaga]');
            if (vaga) {
                var v = Number(vaga.dataset.vaga);
                estado.escolhida = estado.escolhida === v ? null : v;
                desenhar();
                return;
            }

            var escolher = e.target.closest('.js-campo-escolher');
            if (escolher) {
                var id = Number(escolher.closest('.js-campo-atleta').dataset.id);
                var alvo = estado.escolhida !== null ? estado.escolhida : primeiraVazia();
                if (alvo === null) {
                    // Campo cheio e nenhuma vaga escolhida: não passa de 11; pede para escolher quem sai
                    dicaEl.textContent = 'O campo já tem ' + d.maximo + ' titulares. Clique primeiro na posição que esse atleta vai ocupar.';
                    dicaEl.classList.add('admin-campo-dica-alerta');
                    setTimeout(function () { dicaEl.classList.remove('admin-campo-dica-alerta'); }, 1500);
                    return;
                }
                colocar(id, alvo);
            }
        });

        // Arrastar (mouse; no celular continua o clique). Soltar sobre uma vaga ocupada troca o atleta dela (que vira
        // reserva, ou vai para a vaga de origem de quem foi arrastado): o campo nunca passa de 11.
        var arrastado = null;   // { id, vaga }: vaga = -1 quando veio da lista sem estar no campo
        var alvoEl = null;

        function marcarAlvo(el) {
            if (alvoEl === el) return;
            if (alvoEl) alvoEl.classList.remove('alvo');
            alvoEl = el;
            if (el) el.classList.add('alvo');
        }

        function fimDoArraste() {
            marcarAlvo(null);
            raiz.querySelectorAll('.arrastando').forEach(function (el) { el.classList.remove('arrastando'); });
            arrastado = null;
        }

        // Onde dá para soltar: numa vaga do campo; na lista, só quem estava no campo (para virar reserva)
        function destino(e) {
            var caixa = e.target.closest('.admin-campo-vaga');
            if (caixa) return caixa;
            if (arrastado && arrastado.vaga !== -1 && e.target.closest('.js-campo-lista')) return listaBox;
            return null;
        }

        raiz.addEventListener('dragstart', function (e) {
            var deVaga  = e.target.closest('[data-vaga]');
            var daLista = e.target.closest('.js-campo-escolher');
            var id;
            if (deVaga) {
                var i = Number(deVaga.dataset.vaga);
                id = estado.vagas[i];
                if (id === null) { e.preventDefault(); return; }
                arrastado = { id: id, vaga: i };
                deVaga.closest('.escalacao-jogador').classList.add('arrastando');
            } else if (daLista) {
                id = Number(daLista.closest('.js-campo-atleta').dataset.id);
                arrastado = { id: id, vaga: vagaDoAtleta(id) };
                daLista.closest('.js-campo-atleta').classList.add('arrastando');
            } else {
                return;
            }
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(id)); // o Firefox só arrasta com algum dado
        });

        raiz.addEventListener('dragover', function (e) {
            if (!arrastado) return;
            var el = destino(e);
            if (el) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            }
            marcarAlvo(el);
        });

        raiz.addEventListener('dragleave', function (e) {
            if (arrastado && !raiz.contains(e.relatedTarget)) marcarAlvo(null);
        });

        raiz.addEventListener('drop', function (e) {
            if (!arrastado) return;
            var el = destino(e);
            if (!el) return;
            e.preventDefault();
            var feito = arrastado;
            fimDoArraste();
            if (el === listaBox) {
                tirar(feito.vaga, false);
            } else {
                colocar(feito.id, Number(el.querySelector('[data-vaga]').dataset.vaga));
            }
        });

        // A vaga arrastada é redesenhada no drop (sai da página e não recebe o dragend): por isso o drop também limpa
        raiz.addEventListener('dragend', fimDoArraste);

        formEl.addEventListener('change', function () {
            estado.formacao = formEl.value;
            alterar();
        });

        raiz.querySelector('.js-campo-sugerir').addEventListener('click', function () {
            var temAlguem = estado.vagas.some(function (id) { return id !== null; });
            if (temAlguem && !confirm('Trocar o campo pela sugestão das posições do cadastro? (nada é salvo até "Salvar escalação")')) return;
            estado.vagas = d.sugestoes[estado.formacao].map(function (id) { return id === null ? null : Number(id); });
            estado.escolhida = primeiraVazia();
            alterar();
        });

        raiz.querySelector('.js-campo-limpar').addEventListener('click', function () {
            if (!confirm('Tirar todos do campo? Todos ficam como reserva (nada é salvo até "Salvar escalação").')) return;
            estado.vagas = estado.vagas.map(function () { return null; });
            estado.escolhida = 0;
            alterar();
        });

        // Ao salvar: titular com a posição da vaga; quem não está no campo, reserva sem posição
        form.addEventListener('submit', function () {
            form.querySelectorAll('.js-campo-campo-oculto').forEach(function (el) { el.remove(); });
            function oculto(nome, valor) {
                var el = elemento('input', 'js-campo-campo-oculto');
                el.type = 'hidden';
                el.name = nome;
                el.value = valor;
                form.appendChild(el);
            }
            oculto('formacao', estado.formacao);
            ids.forEach(function (id) {
                var i = vagaDoAtleta(id);
                oculto('escalacao[' + id + '][titular]', i === -1 ? 'RESERVA' : 'TITULAR');
                oculto('escalacao[' + id + '][posicao]', i === -1 ? '' : d.vagas[vagasDaFormacao()[i][0]].posicao);
            });
            estado.alterado = false;
        });

        // Sair da tela (ou usar outro formulário dela) com a escalação mudada e não salva: o navegador pergunta
        window.addEventListener('beforeunload', function (e) {
            if (estado.alterado) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        desenhar();
    });
})();
