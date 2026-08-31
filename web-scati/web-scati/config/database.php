<?php
/**
 * Web SCATI - Configuração de conexão com o banco de dados
 *
 * Ajuste as constantes abaixo conforme o ambiente de instalação.
 */

declare(strict_types=1);

// Carrega as bibliotecas de terceiros (Composer) — hoje só a lib de envio
// de notificação push (minishlink/web-push). A pasta vendor/ já vem pronta
// no repositório, não é necessário rodar "composer install".
$scatiAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($scatiAutoload)) {
    require_once $scatiAutoload;
}

// ---------------------------------------------------------------------
// Dados de acesso ao MySQL - ALTERE conforme seu ambiente
// ---------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'scati');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------
// URL base da aplicação - ALTERE para o caminho onde o sistema
// está hospedado (sem barra no final). Ex: http://localhost/web-scati
// ---------------------------------------------------------------------
define('BASE_URL', '/web-scati');

// ---------------------------------------------------------------------
// Chave de criptografia - usada só para cifrar a senha de email
// corporativo salva no cadastro de Usuários (não afeta a senha de login,
// que continua em hash bcrypt, irreversível). RECOMENDADO trocar por uma
// chave própria antes de colocar o sistema em uso — gere uma nova com:
//   php -r "echo bin2hex(random_bytes(32));"
// Atenção: trocar a chave depois de já ter senhas de email salvas torna
// essas senhas antigas ilegíveis (é preciso recadastrá-las).
// ---------------------------------------------------------------------
define('ENCRYPTION_KEY', hex2bin('79a5a63ff45630192437fbd0fafc62bba7de1632871773b1c086fe1f7d540c6f'));

// ---------------------------------------------------------------------
// Chaves VAPID - identificam o servidor para os serviços de notificação
// push do navegador (Chrome/Firefox/Safari). RECOMENDADO gerar um par
// próprio antes de usar notificações em produção — gere um novo com:
//   php -r "require 'vendor/autoload.php'; print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
// Atenção: trocar as chaves invalida as inscrições de notificação já
// feitas pelos usuários (cada um precisaria ativar de novo).
// ---------------------------------------------------------------------
define('VAPID_PUBLIC_KEY', 'BJ8wNDG2l9CHsX_Zv3wEklC3gGNUKMFoc997t5EAR6qXxrtPgd6yWkzlwELQUlRRgOP5guXT1QWlBg-cEwypCFg');
define('VAPID_PRIVATE_KEY', 'ym_7jS6Y6Qfc3Y1iOpMHxhAW2hBEdkVca2ywWpHxY44');
// E-mail de contato exigido pelo padrão VAPID — os serviços de push podem
// usá-lo para avisar o administrador do servidor em caso de abuso.
define('VAPID_SUBJECT', 'mailto:admin@example.com');

/**
 * Retorna uma conexão PDO única (padrão Singleton simples).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}

// ---------------------------------------------------------------------
// Fuso horário do PHP - configurável em Configurações > Fuso Horário
// (tabela configuracoes, chave "fuso_horario"; ver fusosHorariosDisponiveis()
// em includes/functions.php para a lista aceita). Sem isso definido, o PHP
// usaria o fuso horário padrão do php.ini do servidor (no XAMPP, geralmente
// "Europe/Berlin" por padrão!), deixando TODO horário calculado pelo
// próprio sistema errado por várias horas — data/hora de manutenções
// registradas, "dias parado" dos chamados, alerta de troca de toner,
// "gerado em" da ficha de impressão, etc. Datas que já vêm prontas do
// banco de dados (ex.: quando um chamado foi criado) não são afetadas por
// isso, só as calculadas pelo PHP na hora.
//
// Lido direto do banco aqui (em vez de usar configGet(), que só é
// carregada depois via includes/functions.php) porque o fuso precisa
// estar certo antes de qualquer função de data ser chamada pelo resto do
// sistema. Envolvido em try/catch para não quebrar a página inteira caso
// a tabela configuracoes ainda não exista (banco desatualizado).
// ---------------------------------------------------------------------
$scatiFusoHorario = 'America/Sao_Paulo';
try {
    $scatiFusoSalvo = db()->query("SELECT valor FROM configuracoes WHERE chave = 'fuso_horario'")->fetchColumn();
    if ($scatiFusoSalvo && in_array($scatiFusoSalvo, timezone_identifiers_list(), true)) {
        $scatiFusoHorario = $scatiFusoSalvo;
    }
} catch (Throwable $e) {
    // Banco ainda sem a tabela configuracoes (instalação desatualizada) -
    // segue com o padrão America/Sao_Paulo.
}
date_default_timezone_set($scatiFusoHorario);
