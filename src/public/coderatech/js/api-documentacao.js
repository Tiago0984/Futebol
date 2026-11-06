/* ==========================================================================
   DOCUMENTAÇÃO DA API (/api/documentacao, 06/11/2026)
   Ambiente do topo (troca a URL em todos os exemplos), cores do código, abas, copiar, coleção do Postman, menu com
   a seção atual, grupos recolhíveis, busca e filtro por método. Sem JavaScript, a página continua legível com a URL do servidor aberto.
   ========================================================================== */
(function () {
    'use strict';

    var CHAVE_DO_AMBIENTE = 'aacj-api-servidor';
    var baseInicial = document.body.dataset.base;
    var base = baseInicial;

    // ---------- Cores do código ----------
    var PADROES = {
        json: /("(?:\\.|[^"\\])*")(\s*:)?|\b(true|false|null)\b|(-?\d+(?:\.\d+)?)/g,
        js: /(\/\/[^\n]*)|("(?:\\.|[^"\\])*"|`(?:\\.|[^`\\])*`)|\b(const|await|new|import|from|method|headers|body)\b/g,
        bash: /(#[^\n]*)|("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|(^curl\b|\s-[A-Za-z]\b)/gm
    };

    function escapar(texto) {
        return texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function trecho(classe, texto) {
        return '<span class="' + classe + '">' + escapar(texto) + '</span>';
    }

    function colorir(texto, linguagem) {
        var padrao = PADROES[linguagem];
        var html = '';
        var fim = 0;

        texto.replace(padrao, function (achado, a, b, c, d) {
            // Os padrões têm 3 ou 4 grupos: a posição é sempre o penúltimo argumento
            var posicao = arguments[arguments.length - 2];
            html += escapar(texto.slice(fim, posicao));
            fim = posicao + achado.length;

            if (linguagem === 'json') {
                if (a) html += trecho(b ? 'tk-chave' : 'tk-texto', a) + (b ? escapar(b) : '');
                else if (c) html += trecho('tk-palavra', c);
                else html += trecho('tk-numero', d);
            } else if (a) {
                html += trecho('tk-comentario', a);
            } else if (b) {
                html += trecho('tk-texto', b);
            } else {
                html += trecho('tk-palavra', c);
            }
            return achado;
        });

        return html + escapar(texto.slice(fim));
    }

    // ---------- Ambiente ----------
    var codigos = Array.prototype.slice.call(document.querySelectorAll('.js-codigo'));
    codigos.forEach(function (codigo) { codigo.dataset.raw = codigo.textContent; });

    function textoDoCodigo(codigo) {
        return codigo.dataset.raw.split(baseInicial).join(base);
    }

    function desenhar() {
        codigos.forEach(function (codigo) {
            codigo.innerHTML = colorir(textoDoCodigo(codigo), codigo.dataset.lang);
        });
        document.querySelectorAll('.js-base').forEach(function (el) { el.textContent = base; });
    }

    var ambientes = Array.prototype.slice.call(document.querySelectorAll('.ambiente'));
    var salvo = null;
    try { salvo = localStorage.getItem(CHAVE_DO_AMBIENTE); } catch (e) { /* navegador sem armazenamento */ }

    function usarAmbiente(url) {
        base = url;
        ambientes.forEach(function (botao) { botao.classList.toggle('ativo', botao.dataset.url === url); });
        desenhar();
    }

    ambientes.forEach(function (botao) {
        botao.addEventListener('click', function () {
            try { localStorage.setItem(CHAVE_DO_AMBIENTE, botao.dataset.url); } catch (e) { /* navegador sem armazenamento */ }
            usarAmbiente(botao.dataset.url);
        });
    });

    usarAmbiente(ambientes.some(function (botao) { return botao.dataset.url === salvo; }) ? salvo : base);

    // ---------- Abas e copiar ----------
    document.querySelectorAll('.js-bloco').forEach(function (bloco) {
        var abas = bloco.querySelectorAll('.tab');
        var paineis = bloco.querySelectorAll('.panel');

        abas.forEach(function (aba) {
            aba.addEventListener('click', function () {
                abas.forEach(function (a) { a.classList.remove('active'); });
                paineis.forEach(function (p) { p.classList.remove('active'); });
                aba.classList.add('active');
                paineis[Number(aba.dataset.panel)].classList.add('active');
            });
        });

        var copiar = bloco.querySelector('.copy');
        copiar.addEventListener('click', function () {
            var codigo = bloco.querySelector('.panel.active .js-codigo');
            var aviso = function (texto) {
                copiar.textContent = texto;
                setTimeout(function () { copiar.textContent = 'Copiar'; }, 1500);
            };

            if (!navigator.clipboard) {
                aviso('Selecione e copie');
                return;
            }
            navigator.clipboard.writeText(textoDoCodigo(codigo)).then(function () { aviso('Copiado!'); }, function () { aviso('Erro'); });
        });
    });

    // ---------- Coleção do Postman ----------
    document.getElementById('postman').addEventListener('click', function () {
        var colecao = JSON.parse(document.getElementById('colecaoPostman').textContent);
        colecao.variable[0].value = base;

        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([JSON.stringify(colecao, null, 2)], { type: 'application/json' }));
        link.download = 'AACJ-Futebol.postman_collection.json';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    });

    // ---------- Menu com a seção atual ----------
    var sidebar = document.getElementById('sidebar');
    var links = Array.prototype.slice.call(sidebar.querySelectorAll('.nav-link'));

    var grupos = Array.prototype.slice.call(sidebar.querySelectorAll('.nav-group'));
    var alternar = document.getElementById('alternarGrupos');

    function abrirGrupo(grupo, aberto) {
        grupo.classList.toggle('fechado', !aberto);
        grupo.querySelector('.nav-title').setAttribute('aria-expanded', aberto ? 'true' : 'false');
    }

    function atualizarAlternar() {
        var algumAberto = grupos.some(function (grupo) { return !grupo.classList.contains('fechado'); });
        alternar.textContent = algumAberto ? 'Recolher tudo' : 'Expandir tudo';
    }

    // Os grupos começam recolhidos e abrem um de cada vez
    function abrirSo(grupo) {
        grupos.forEach(function (outro) { abrirGrupo(outro, outro === grupo); });
        atualizarAlternar();
    }

    grupos.forEach(function (grupo) {
        grupo.querySelector('.nav-title').addEventListener('click', function () {
            if (grupo.classList.contains('fechado')) {
                abrirSo(grupo);
            } else {
                abrirGrupo(grupo, false);
                atualizarAlternar();
            }
        });
    });

    // Link direto para uma rota (endereço com #, ou link do texto): abre o grupo dela
    function abrirPeloEndereco() {
        var link = links.filter(function (l) { return l.getAttribute('href') === location.hash; })[0];
        if (link) abrirSo(link.closest('.nav-group'));
    }

    window.addEventListener('hashchange', abrirPeloEndereco);
    abrirPeloEndereco();

    alternar.addEventListener('click', function () {
        var abrir = alternar.textContent === 'Expandir tudo';
        grupos.forEach(function (grupo) { abrirGrupo(grupo, abrir); });
        atualizarAlternar();
    });

    // A rolagem só marca o grupo da seção atual: abrir aqui faria o menu pular enquanto a página rola até uma rota
    function marcar(id) {
        grupos.forEach(function (grupo) {
            grupo.classList.toggle('contem-ativo', !!grupo.querySelector('.nav-link[href="#' + id + '"]'));
        });

        links.forEach(function (link) {
            var atual = link.getAttribute('href') === '#' + id;
            link.classList.toggle('active', atual);
            if (atual && link.offsetParent) {
                var caixa = link.getBoundingClientRect();
                var menu = sidebar.getBoundingClientRect();
                if (caixa.top < menu.top + 8 || caixa.bottom > menu.bottom - 8) {
                    sidebar.scrollTop += caixa.top - menu.top - menu.height / 2;
                }
            }
        });
    }

    if ('IntersectionObserver' in window) {
        var observador = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (entrada) {
                if (entrada.isIntersecting) marcar(entrada.target.id);
            });
        }, { rootMargin: '-80px 0px -75% 0px' });

        document.querySelectorAll('.section').forEach(function (secao) { observador.observe(secao); });
    }

    // ---------- Busca e filtro por método ----------
    var busca = document.getElementById('search');
    var vazio = document.getElementById('navEmpty');
    var contador = document.getElementById('navContador');
    var filtros = sidebar.querySelectorAll('.filtro');
    var metodo = '';

    function filtrar() {
        var termo = busca.value.trim().toLowerCase();
        var total = 0;

        sidebar.querySelectorAll('.nav-group').forEach(function (grupo) {
            var visiveis = 0;

            grupo.querySelectorAll('.nav-link').forEach(function (link) {
                var doMetodo = !metodo || link.dataset.metodos.split(' ').indexOf(metodo) !== -1;
                var mostrar = doMetodo && (!termo || link.dataset.busca.indexOf(termo) !== -1);
                link.hidden = !mostrar;
                link.style.display = mostrar ? '' : 'none';
                if (mostrar) visiveis++;
            });

            grupo.style.display = visiveis ? '' : 'none';
            total += visiveis;
        });

        // Com busca ou filtro, os grupos recolhidos mostram o que encontraram
        sidebar.classList.toggle('filtrando', !!termo || !!metodo);
        vazio.hidden = total > 0;
        contador.textContent = total + (total === 1 ? ' item' : ' itens');
    }

    busca.addEventListener('input', filtrar);
    filtrar();

    filtros.forEach(function (filtro) {
        filtro.addEventListener('click', function () {
            metodo = filtro.dataset.metodo;
            filtros.forEach(function (f) { f.classList.toggle('ativo', f === filtro); });
            filtrar();
        });
    });

    // ---------- Menu no celular ----------
    document.getElementById('menuToggle').addEventListener('click', function () { sidebar.classList.toggle('open'); });
    links.forEach(function (link) {
        link.addEventListener('click', function () { sidebar.classList.remove('open'); });
    });
})();
