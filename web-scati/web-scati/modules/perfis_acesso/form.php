<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
exigirAdmin();

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$edicao = $id !== null;
$modulos = modulosSistema();

$perfil = ['nome' => '', 'protegido' => 0];
$permissoes = [];

if ($edicao) {
    $stmt = $pdo->prepare('SELECT * FROM perfis_acesso WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $registro = $stmt->fetch();
    if (!$registro) {
        flash('danger', 'Perfil não encontrado.');
        redirect('/modules/perfis_acesso/index.php');
    }
    $perfil = array_merge($perfil, $registro);

    if ($perfil['protegido']) {
        // Administrador não tem linhas em perfil_permissoes — tem acesso
        // completo sempre (ver temPermissao()); mostra tudo marcado só
        // pra deixar isso visível na tela.
        foreach ($modulos as $chave => $modulo) {
            $permissoes[$chave] = ['ver' => true, 'alterar' => !($modulo['somenteVisualizar'] ?? false), 'ver_todos' => true];
        }
    } else {
        $stmtPerm = $pdo->prepare('SELECT modulo, visualizar, alterar, ver_todos FROM perfil_permissoes WHERE perfil_id = :id');
        $stmtPerm->execute(['id' => $id]);
        foreach ($stmtPerm->fetchAll() as $linha) {
            $permissoes[$linha['modulo']] = [
                'ver' => (bool) $linha['visualizar'],
                'alterar' => (bool) $linha['alterar'],
                'ver_todos' => (bool) $linha['ver_todos'],
            ];
        }
    }
}

$somenteLeitura = $edicao && (bool) $perfil['protegido'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$somenteLeitura) {
    $perfil['nome'] = trim($_POST['nome'] ?? '');

    foreach ($modulos as $chave => $modulo) {
        $ver = isset($_POST['ver_' . $chave]);
        $alterar = !($modulo['somenteVisualizar'] ?? false) && isset($_POST['alterar_' . $chave]);
        // "Alterar" sem "Visualizar" não faz sentido — marcar um dos dois
        // sempre implica o outro também marcado.
        if ($alterar) {
            $ver = true;
        }
        // "Ver Todos os Chamados" só existe no módulo Chamados (ver tabela
        // mais abaixo) — não faz sentido sem "Visualizar" também marcado.
        $verTodosChamados = $chave === 'chamados' && $ver && isset($_POST['ver_todos_chamados']);
        $permissoes[$chave] = ['ver' => $ver, 'alterar' => $alterar, 'ver_todos' => $verTodosChamados];
    }

    if ($perfil['nome'] === '') {
        $erros[] = 'O campo Nome é obrigatório.';
    }

    if (empty($erros)) {
        $sqlCheck = 'SELECT id FROM perfis_acesso WHERE nome = :nome' . ($edicao ? ' AND id != :id' : '');
        $stmtCheck = $pdo->prepare($sqlCheck);
        $paramsCheck = ['nome' => $perfil['nome']];
        if ($edicao) {
            $paramsCheck['id'] = $id;
        }
        $stmtCheck->execute($paramsCheck);
        if ($stmtCheck->fetch()) {
            $erros[] = 'Já existe um perfil com este nome.';
        }
    }

    if (empty($erros)) {
        if ($edicao) {
            // Renomear aqui não precisa atualizar usuarios.perfil na mão —
            // a FK fk_usuario_perfil tem ON UPDATE CASCADE e propaga
            // sozinha pra quem já usava o nome antigo.
            $pdo->prepare('UPDATE perfis_acesso SET nome = :nome WHERE id = :id')
                ->execute(['nome' => $perfil['nome'], 'id' => $id]);
            $perfilId = $id;
        } else {
            $pdo->prepare('INSERT INTO perfis_acesso (nome, protegido) VALUES (:nome, 0)')
                ->execute(['nome' => $perfil['nome']]);
            $perfilId = (int) $pdo->lastInsertId();
        }

        // Reconstrói as permissões do zero a cada salvamento — mais simples
        // e seguro que tentar sincronizar diferença por diferença, e o
        // volume de linhas é pequeno (no máximo 1 por módulo do sistema).
        $pdo->prepare('DELETE FROM perfil_permissoes WHERE perfil_id = :id')->execute(['id' => $perfilId]);
        $stmtInsert = $pdo->prepare(
            'INSERT INTO perfil_permissoes (perfil_id, modulo, visualizar, alterar, ver_todos) VALUES (:perfil_id, :modulo, :ver, :alterar, :ver_todos)'
        );
        foreach ($permissoes as $chave => $valores) {
            if (!$valores['ver'] && !$valores['alterar']) {
                continue;
            }
            $stmtInsert->execute([
                'perfil_id' => $perfilId,
                'modulo' => $chave,
                'ver' => $valores['ver'] ? 1 : 0,
                'alterar' => $valores['alterar'] ? 1 : 0,
                'ver_todos' => $valores['ver_todos'] ? 1 : 0,
            ]);
        }

        flash('success', $edicao ? 'Perfil atualizado com sucesso.' : 'Perfil cadastrado com sucesso.');
        redirect('/modules/perfis_acesso/index.php');
    }
}

$pageTitle = $somenteLeitura ? 'Ver Permissões' : ($edicao ? 'Editar Perfil' : 'Novo Perfil');

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-person-badge me-2"></i><?= e($pageTitle) ?></h1>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
</div>

<?php if (!empty($erros)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($somenteLeitura): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-lock-fill me-1"></i> Este é o perfil protegido do sistema — sempre tem acesso completo e
        não pode ser renomeado nem ter suas permissões alteradas.
    </div>
<?php endif; ?>

<form method="post">
    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome do Perfil *</label>
                <input type="text" name="nome" class="form-control" required <?= $somenteLeitura ? 'readonly' : '' ?> value="<?= e($perfil['nome']) ?>">
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-ui-checks-grid me-1"></i> Permissões</span>
            <?php if (!$somenteLeitura): ?>
                <span class="small">
                    <a href="#" class="text-decoration-none me-3 js-perfil-marcar-tudo"><i class="bi bi-check-all"></i> Marcar tudo</a>
                    <a href="#" class="text-decoration-none text-muted js-perfil-desmarcar-tudo"><i class="bi bi-x-lg"></i> Desmarcar tudo</a>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                <strong>Visualizar</strong>: a aba aparece no menu e o perfil pode abrir as páginas (só leitura).
                <strong>Alterar</strong>: além de ver, pode criar, editar e excluir registros ali — só fica
                disponível pra marcar quando "Visualizar" também está marcado. Em <strong>Chamados</strong>, tem
                uma coluna extra: <strong>Ver Todos</strong> libera ver (sem poder alterar) os chamados atribuídos
                a qualquer pessoa da equipe na aba Em Atendimento — sem marcar, cada um só vê os próprios.
            </p>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Módulo</th>
                            <th class="text-center" style="width: 9rem;">Visualizar</th>
                            <th class="text-center" style="width: 9rem;">Alterar</th>
                            <th class="text-center" style="width: 9rem;">Ver Todos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modulos as $chave => $modulo): ?>
                            <?php
                                $ver = $permissoes[$chave]['ver'] ?? false;
                                $alterar = $permissoes[$chave]['alterar'] ?? false;
                                $verTodosChamados = $permissoes[$chave]['ver_todos'] ?? false;
                                $somenteVer = $modulo['somenteVisualizar'] ?? false;
                            ?>
                            <tr>
                                <td><i class="bi <?= e($modulo['icone']) ?> me-2 text-muted"></i><?= e($modulo['label']) ?></td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input js-perfil-ver" name="ver_<?= e($chave) ?>" data-modulo="<?= e($chave) ?>"
                                           <?= $ver ? 'checked' : '' ?> <?= $somenteLeitura ? 'disabled' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <?php if ($somenteVer): ?>
                                        <span class="text-muted" title="Não se aplica">—</span>
                                    <?php else: ?>
                                        <input type="checkbox" class="form-check-input js-perfil-alterar" name="alterar_<?= e($chave) ?>" data-modulo="<?= e($chave) ?>"
                                               <?= $alterar ? 'checked' : '' ?> <?= $somenteLeitura ? 'disabled' : '' ?>>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($chave === 'chamados'): ?>
                                        <input type="checkbox" class="form-check-input" name="ver_todos_chamados"
                                               <?= $verTodosChamados ? 'checked' : '' ?> <?= $somenteLeitura ? 'disabled' : '' ?>
                                               title="Ver (sem poder alterar) os chamados atribuídos a qualquer pessoa da equipe">
                                    <?php else: ?>
                                        <span class="text-muted" title="Só se aplica a Chamados">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!$somenteLeitura): ?>
        <div class="d-flex gap-2 mb-5">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    <?php endif; ?>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
