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
            // Ignora o clique se foi em um botão/link dentro da linha
            if (e.target.closest('a, button, input')) return;
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

    // Ativa a aba correspondente ao hash da URL (ex: #observacoes após adicionar uma observação)
    if (window.location.hash) {
        const tabTrigger = document.querySelector('[data-bs-target="' + window.location.hash + '"]');
        if (tabTrigger) {
            new bootstrap.Tab(tabTrigger).show();
        }
    }

    // Mostra/oculta campos específicos de impressora ou servidor no formulário de equipamentos
    const tipoSelect = document.getElementById('tipo');
    const printerFields = document.getElementById('printerFields');
    const serverFields = document.getElementById('serverFields');
    function toggleTypeFields() {
        if (!tipoSelect) return;
        if (printerFields) {
            printerFields.style.display = tipoSelect.value === 'Impressora' ? '' : 'none';
        }
        if (serverFields) {
            serverFields.style.display = tipoSelect.value === 'Servidor' ? '' : 'none';
        }
    }
    if (tipoSelect) {
        tipoSelect.addEventListener('change', toggleTypeFields);
        toggleTypeFields();
    }
});
