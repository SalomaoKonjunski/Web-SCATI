/**
 * Web SCATI - Scripts gerais da interface
 */
document.addEventListener('DOMContentLoaded', function () {
    // Alterna a sidebar em telas pequenas
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('scatiSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // Torna linhas de tabela com data-href clicáveis (navegação para a ficha)
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Ignora o clique se foi em um botão/link/campo dentro da linha
            if (e.target.closest('a, button, input, select, label')) return;
            window.location = row.dataset.href;
        });
    });

    // Confirmação padrão para ações de exclusão
    document.querySelectorAll('.js-confirm-delete').forEach(function (link) {
        link.addEventListener('click', function (e) {
            const msg = link.dataset.confirmMsg || 'Tem certeza que deseja excluir este registro?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Envia automaticamente formulários marcados com .js-auto-submit assim que
    // um campo (select/input) dentro deles muda de valor (ex: trocar o status
    // de um chamado direto na listagem, sem precisar de um botão "Salvar").
    document.querySelectorAll('.js-auto-submit').forEach(function (form) {
        form.querySelectorAll('select, input').forEach(function (field) {
            field.addEventListener('change', function () {
                form.submit();
            });
        });
    });

    // Ativa a aba correspondente ao hash da URL (ex: #observacoes após adicionar uma observação)
    if (window.location.hash) {
        const tabTrigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
        if (tabTrigger) {
            new bootstrap.Tab(tabTrigger).show();
        }
    }

    // Mostra/oculta, no formulário de equipamentos, o card de nota da aba
    // Toner (só existe para o tipo "Impressora" — recurso próprio, não faz
    // parte dos cards de campos extras configuráveis por categoria abaixo).
    const tipoSelect = document.getElementById('tipo');
    const tonerNotaNovoField = document.getElementById('tonerNotaNovoField');
    function toggleTonerNota() {
        if (!tipoSelect || !tonerNotaNovoField) return;
        tonerNotaNovoField.style.display = tipoSelect.value === 'Impressora' ? '' : 'none';
    }
    if (tipoSelect) {
        tipoSelect.addEventListener('change', toggleTonerNota);
        toggleTonerNota();
    }

    // Mostra/oculta os cards de campos extras (Hardware, Dados da
    // Impressora, Rede do Computador, Informações do Servidor) no
    // formulário de equipamentos, conforme os grupos habilitados na
    // categoria selecionada (data-grupos em cada <option> do #tipo, ex.:
    // "hardware,rede_computador").
    function toggleGruposCategoriaEquipamento() {
        if (!tipoSelect) return;
        const opcaoSelecionada = tipoSelect.options[tipoSelect.selectedIndex];
        const grupos = (opcaoSelecionada && opcaoSelecionada.dataset.grupos) ? opcaoSelecionada.dataset.grupos.split(',') : [];
        document.querySelectorAll('[id^="grupoCampos_"]').forEach(function (card) {
            const chave = card.id.replace('grupoCampos_', '');
            card.style.display = grupos.includes(chave) ? '' : 'none';
        });
    }
    if (tipoSelect) {
        tipoSelect.addEventListener('change', toggleGruposCategoriaEquipamento);
        toggleGruposCategoriaEquipamento();
    }

    // Construtor de Relatório (passo 3, Filtros): monta dinamicamente o
    // select de Operador e o campo de Valor de cada linha, de acordo com o
    // tipo da coluna escolhida em "Campo" — dados injetados pelo PHP em
    // window.SCATI_COLUNAS_ORIGEM (colunas da origem atual) e
    // window.SCATI_OPERADORES_POR_TIPO (operadores por tipo de coluna).
    // Linhas já preenchidas na carga da página vêm prontas do servidor;
    // este script só entra em ação ao adicionar uma linha nova ou trocar o
    // "Campo" de uma linha existente.
    const filtrosContainer = document.getElementById('filtrosContainer');
    if (filtrosContainer && window.SCATI_COLUNAS_ORIGEM && window.SCATI_OPERADORES_POR_TIPO) {
        const colunasOrigem = window.SCATI_COLUNAS_ORIGEM;
        const operadoresPorTipo = window.SCATI_OPERADORES_POR_TIPO;
        let proximoIdDatalist = 0;

        function tipoParaInputHtml(tipo) {
            if (tipo === 'numero' || tipo === 'dinheiro') return 'number';
            if (tipo === 'data' || tipo === 'datahora') return 'date';
            return 'text';
        }

        function atualizarLinhaFiltro(linha) {
            const campoSelect = linha.querySelector('.filtro-campo-select');
            const operadorSelect = linha.querySelector('.filtro-operador-select');
            const valorInput = linha.querySelector('.filtro-valor-input');
            const datalist = linha.querySelector('datalist');
            const def = colunasOrigem[campoSelect.value];

            operadorSelect.innerHTML = '';
            valorInput.value = '';
            datalist.innerHTML = '';

            if (!def) {
                return;
            }

            const operadores = operadoresPorTipo[def.tipo] || {};
            Object.keys(operadores).forEach(function (chaveOp) {
                const opcao = document.createElement('option');
                opcao.value = chaveOp;
                opcao.textContent = operadores[chaveOp];
                operadorSelect.appendChild(opcao);
            });

            valorInput.type = tipoParaInputHtml(def.tipo);

            if (def.opcoes) {
                def.opcoes.forEach(function (valorOpcao) {
                    const opcao = document.createElement('option');
                    opcao.value = valorOpcao;
                    datalist.appendChild(opcao);
                });
            }

            toggleValorInput(linha);
        }

        function toggleValorInput(linha) {
            const operadorSelect = linha.querySelector('.filtro-operador-select');
            const valorInput = linha.querySelector('.filtro-valor-input');
            const semValor = operadorSelect.value === 'vazio' || operadorSelect.value === 'nao_vazio';
            valorInput.style.display = semValor ? 'none' : '';
        }

        function novaLinhaFiltro() {
            const template = document.getElementById('filtroLinhaTemplate');
            const fragmento = template.content.cloneNode(true);
            const linha = fragmento.querySelector('.filtro-linha');

            // Cada linha precisa de um id de <datalist> próprio (o clonado
            // do template pode colidir com o de outras linhas na página).
            proximoIdDatalist++;
            const novoId = 'filtroDatalistNovo_' + proximoIdDatalist;
            linha.querySelector('datalist').id = novoId;
            linha.querySelector('.filtro-valor-input').setAttribute('list', novoId);

            return linha;
        }

        const btnAdicionar = document.getElementById('btnAdicionarFiltro');
        if (btnAdicionar) {
            btnAdicionar.addEventListener('click', function () {
                filtrosContainer.appendChild(novaLinhaFiltro());
            });
        }

        filtrosContainer.addEventListener('click', function (e) {
            const btnRemover = e.target.closest('.btn-remover-filtro');
            if (btnRemover) {
                btnRemover.closest('.filtro-linha').remove();
            }
        });

        filtrosContainer.addEventListener('change', function (e) {
            if (e.target.classList.contains('filtro-campo-select')) {
                atualizarLinhaFiltro(e.target.closest('.filtro-linha'));
            }
            if (e.target.classList.contains('filtro-operador-select')) {
                toggleValorInput(e.target.closest('.filtro-linha'));
            }
        });
    }

    // Botão de mostrar/ocultar em campos de senha (cadastro de Usuários:
    // Senha de login e Senha do Email Corporativo). Sempre começa oculto.
    document.querySelectorAll('.senha-toggle-btn').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const input = botao.closest('.input-group').querySelector('.senha-toggle-input');
            const icone = botao.querySelector('i');
            const vaiMostrar = input.type === 'password';
            input.type = vaiMostrar ? 'text' : 'password';
            icone.classList.toggle('bi-eye', !vaiMostrar);
            icone.classList.toggle('bi-eye-slash', vaiMostrar);
        });
    });

    // Gráfico "Itens de Estoque por Categoria": tooltip ao passar o mouse/focar
    const donutSegs = document.querySelectorAll('.scati-donut-seg');
    const donutTooltip = document.getElementById('estoqueDonutTooltip');
    if (donutSegs.length && donutTooltip) {
        const showDonutTooltip = function (seg, x, y) {
            donutTooltip.innerHTML = '';
            const strong = document.createElement('strong');
            strong.textContent = seg.dataset.total + ' item(ns)';
            const detalhe = document.createElement('div');
            detalhe.textContent = seg.dataset.categoria + ' · ' + seg.dataset.percentual + '%';
            donutTooltip.appendChild(strong);
            donutTooltip.appendChild(detalhe);
            donutTooltip.style.left = x + 'px';
            donutTooltip.style.top = y + 'px';
            donutTooltip.hidden = false;
        };
        const hideDonutTooltip = function () {
            donutTooltip.hidden = true;
        };
        donutSegs.forEach(function (seg) {
            seg.addEventListener('pointermove', function (e) {
                showDonutTooltip(seg, e.clientX + 14, e.clientY + 14);
            });
            seg.addEventListener('pointerenter', function (e) {
                showDonutTooltip(seg, e.clientX + 14, e.clientY + 14);
            });
            seg.addEventListener('pointerleave', hideDonutTooltip);
            seg.addEventListener('focus', function () {
                const rect = seg.getBoundingClientRect();
                showDonutTooltip(seg, rect.left + rect.width / 2, rect.top - 10);
            });
            seg.addEventListener('blur', hideDonutTooltip);
        });
    }

    // Alterna entre gráfico e tabela no card "Itens de Estoque por Categoria"
    const estoqueChartToggle = document.getElementById('estoqueChartTableToggle');
    const estoqueChartWrap = document.getElementById('estoqueChartWrap');
    const estoqueChartTable = document.getElementById('estoqueChartTable');
    if (estoqueChartToggle && estoqueChartWrap && estoqueChartTable) {
        estoqueChartToggle.addEventListener('click', function () {
            const vaiMostrarTabela = estoqueChartTable.classList.contains('d-none');
            estoqueChartTable.classList.toggle('d-none', !vaiMostrarTabela);
            estoqueChartWrap.classList.toggle('d-none', vaiMostrarTabela);
            estoqueChartToggle.innerHTML = vaiMostrarTabela
                ? '<i class="bi bi-pie-chart"></i> Ver como gráfico'
                : '<i class="bi bi-table"></i> Ver como tabela';
        });
    }

    // Botão "Tirar foto" (só aparece no celular): copia a foto capturada
    // pela câmera para o campo de arquivo do formulário de anexos.
    const anexoCamera = document.getElementById('anexoCamera');
    const anexoArquivo = document.getElementById('anexoArquivo');
    if (anexoCamera && anexoArquivo) {
        anexoCamera.addEventListener('change', function () {
            if (anexoCamera.files.length > 0) {
                const dt = new DataTransfer();
                dt.items.add(anexoCamera.files[0]);
                anexoArquivo.files = dt.files;
            }
        });
    }

    // Notificações de chamados: sininho no topo que verifica periodicamente
    // se chegou resposta nova em algum chamado do usuário e toca um bipe
    // curto (enquanto a aba do sistema estiver aberta no navegador).
    const notifBtn = document.getElementById('scatiNotificacaoBtn');
    const notifBadge = document.getElementById('scatiNotificacaoBadge');
    if (notifBtn && notifBadge) {
        let audioCtx = null;
        document.addEventListener('click', function desbloquearAudio() {
            if (!audioCtx) {
                try {
                    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                } catch (e) {
                    // navegador sem suporte a Web Audio - notificação sonora fica desativada
                }
            }
        }, { once: true });

        function tocarBip() {
            if (!audioCtx) return;
            [880, 1108].forEach(function (freq, i) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const inicio = audioCtx.currentTime + i * 0.18;
                gain.gain.setValueAtTime(0.0001, inicio);
                gain.gain.exponentialRampToValueAtTime(0.25, inicio + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.3);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start(inicio);
                osc.stop(inicio + 0.3);
            });
        }

        function atualizarBadgeNotificacao(qtd) {
            if (qtd > 0) {
                notifBadge.textContent = qtd > 9 ? '9+' : String(qtd);
                notifBadge.classList.remove('d-none');
            } else {
                notifBadge.classList.add('d-none');
            }
        }

        // Preenche o menu suspenso com um item por chamado (título + prévia
        // da última mensagem + quem escreveu), cada um linkando para a ficha.
        const notifMenu = document.getElementById('scatiNotificacaoMenu');
        function renderizarMenuNotificacao(itens) {
            if (!notifMenu) return;
            notifMenu.querySelectorAll('.scati-notif-item, .scati-notif-vazio').forEach(function (el) {
                el.remove();
            });

            if (!itens || itens.length === 0) {
                const vazio = document.createElement('div');
                vazio.className = 'px-3 py-4 text-muted small text-center scati-notif-vazio';
                vazio.textContent = 'Nenhuma notificação.';
                notifMenu.appendChild(vazio);
                return;
            }

            itens.forEach(function (item) {
                const link = document.createElement('a');
                // Solicitação nova: a caixa inteira do item fica pintada de
                // azul (classe scati-notif-solicitacao). Mensagem nova:
                // mostra um contador com a quantidade de respostas não lidas.
                link.className = 'dropdown-item scati-notif-item py-2 border-bottom d-flex justify-content-between align-items-start gap-2'
                    + (item.tipo === 'solicitacao' ? ' scati-notif-solicitacao' : '');
                link.style.whiteSpace = 'normal';
                link.href = notifBtn.dataset.formUrl + '?id=' + item.chamado_id;

                const corpo = document.createElement('div');
                // min-width: 0 é necessário para o text-truncate funcionar
                // dentro de um item flex — sem isso o texto empurra o
                // contador para fora da caixa em vez de truncar.
                corpo.className = 'flex-grow-1';
                corpo.style.minWidth = '0';

                const titulo = document.createElement('div');
                titulo.className = 'fw-semibold small';
                titulo.textContent = item.titulo;

                const mensagem = document.createElement('div');
                mensagem.className = 'small text-muted text-truncate';
                mensagem.textContent = (item.usuario_nome ? item.usuario_nome + ': ' : '') + item.mensagem;

                corpo.appendChild(titulo);
                corpo.appendChild(mensagem);
                link.appendChild(corpo);

                // Mostra o contador mesmo quando o chamado em si já está
                // pintado como solicitação nova — ele pode já ter respostas
                // não vistas antes mesmo de eu ter aberto pela primeira vez.
                if (item.qtd_mensagens_novas > 0) {
                    const contador = document.createElement('span');
                    contador.className = 'badge rounded-pill bg-primary flex-shrink-0';
                    contador.textContent = item.qtd_mensagens_novas > 9 ? '9+' : String(item.qtd_mensagens_novas);
                    link.appendChild(contador);
                }

                notifMenu.appendChild(link);
            });
        }

        let ultimaContagem = parseInt(notifBtn.dataset.naoLidas || '0', 10);
        atualizarBadgeNotificacao(ultimaContagem);

        function verificarNotificacoes() {
            fetch(notifBtn.dataset.url, { credentials: 'same-origin' })
                .then(function (resp) { return resp.ok ? resp.json() : null; })
                .then(function (dados) {
                    if (!dados) return;
                    if (dados.nao_lidas > ultimaContagem) {
                        tocarBip();
                    }
                    ultimaContagem = dados.nao_lidas;
                    atualizarBadgeNotificacao(ultimaContagem);
                    renderizarMenuNotificacao(dados.itens);
                })
                .catch(function () {
                    // falha de rede - tenta de novo na próxima checagem
                });
        }

        verificarNotificacoes();
        setInterval(verificarNotificacoes, 25000);
    }
});
