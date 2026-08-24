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

    // Mostra/oculta campos específicos de impressora, servidor ou computador no formulário de equipamentos
    const tipoSelect = document.getElementById('tipo');
    const printerFields = document.getElementById('printerFields');
    const serverFields = document.getElementById('serverFields');
    const computerFields = document.getElementById('computerFields');
    const placaMaeField = document.getElementById('placaMaeField');
    const placaVideoField = document.getElementById('placaVideoField');
    const tonerNotaNovoField = document.getElementById('tonerNotaNovoField');
    const hardwareFields = document.getElementById('hardwareFields');
    function toggleTypeFields() {
        if (!tipoSelect) return;
        if (printerFields) {
            printerFields.style.display = tipoSelect.value === 'Impressora' ? '' : 'none';
        }
        if (tonerNotaNovoField) {
            tonerNotaNovoField.style.display = tipoSelect.value === 'Impressora' ? '' : 'none';
        }
        if (hardwareFields) {
            hardwareFields.style.display = tipoSelect.value === 'Impressora' ? 'none' : '';
        }
        if (serverFields) {
            serverFields.style.display = tipoSelect.value === 'Servidor' ? '' : 'none';
        }
        if (computerFields) {
            computerFields.style.display = tipoSelect.value === 'Computador' ? '' : 'none';
        }
        if (placaMaeField) {
            placaMaeField.style.display = tipoSelect.value === 'Computador' ? '' : 'none';
        }
        if (placaVideoField) {
            placaVideoField.style.display = tipoSelect.value === 'Computador' ? '' : 'none';
        }
    }
    if (tipoSelect) {
        tipoSelect.addEventListener('change', toggleTypeFields);
        toggleTypeFields();
    }

    // Mostra/oculta os cards de campos extras no formulário de item de
    // estoque, conforme os grupos habilitados na categoria selecionada
    // (data-grupos em cada <option>, ex.: "hardware,rede_computador").
    const categoriaEstoqueSelect = document.getElementById('categoriaEstoqueSelect');
    function toggleGruposCategoriaEstoque() {
        if (!categoriaEstoqueSelect) return;
        const opcaoSelecionada = categoriaEstoqueSelect.options[categoriaEstoqueSelect.selectedIndex];
        const grupos = (opcaoSelecionada && opcaoSelecionada.dataset.grupos) ? opcaoSelecionada.dataset.grupos.split(',') : [];
        document.querySelectorAll('[id^="grupoCampos_"]').forEach(function (card) {
            const chave = card.id.replace('grupoCampos_', '');
            card.style.display = grupos.includes(chave) ? '' : 'none';
        });
    }
    if (categoriaEstoqueSelect) {
        categoriaEstoqueSelect.addEventListener('change', toggleGruposCategoriaEstoque);
        toggleGruposCategoriaEstoque();
    }

    // Ao escolher uma categoria de estoque marcada como "Equipamento" (tem
    // data-equipamento-url), leva direto para o cadastro de Equipamentos em
    // vez de deixar criar uma linha de estoque com quantidade.
    function redirecionarSeCategoriaForEquipamento() {
        if (!categoriaEstoqueSelect) return;
        const opcaoSelecionada = categoriaEstoqueSelect.options[categoriaEstoqueSelect.selectedIndex];
        const url = opcaoSelecionada ? opcaoSelecionada.dataset.equipamentoUrl : '';
        if (url) {
            window.location.href = url;
        }
    }
    if (categoriaEstoqueSelect) {
        categoriaEstoqueSelect.addEventListener('change', redirecionarSeCategoriaForEquipamento);
    }

    // No formulário de Categoria de Estoque, mostra/oculta o seletor de tipo
    // de equipamento e o card de campos extras conforme o modo escolhido.
    const modoEstoqueRadio = document.getElementById('modoEstoque');
    const modoEquipamentoRadio = document.getElementById('modoEquipamento');
    const painelEquipamentoTipo = document.getElementById('painelEquipamentoTipo');
    const painelCamposExtras = document.getElementById('painelCamposExtras');
    function toggleModoCategoriaEstoque() {
        if (!modoEquipamentoRadio) return;
        const ehEquipamento = modoEquipamentoRadio.checked;
        if (painelEquipamentoTipo) {
            painelEquipamentoTipo.style.display = ehEquipamento ? '' : 'none';
        }
        if (painelCamposExtras) {
            painelCamposExtras.style.display = ehEquipamento ? 'none' : '';
        }
    }
    if (modoEstoqueRadio && modoEquipamentoRadio) {
        modoEstoqueRadio.addEventListener('change', toggleModoCategoriaEstoque);
        modoEquipamentoRadio.addEventListener('change', toggleModoCategoriaEstoque);
        toggleModoCategoriaEstoque();
    }

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
