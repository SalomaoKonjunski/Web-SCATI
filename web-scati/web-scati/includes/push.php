<?php
/**
 * Web SCATI - Envio de notificações push (Web Push API).
 *
 * Usa a biblioteca minishlink/web-push (Composer, pasta vendor/), que cuida
 * do protocolo Web Push de verdade (assinatura VAPID, criptografia da
 * mensagem) — chaves em config/database.php (VAPID_PUBLIC_KEY etc.).
 */

declare(strict_types=1);

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Envia uma notificação push para todos os dispositivos em que o usuário
 * informado ativou notificações (tabela push_subscriptions). Remove
 * automaticamente as inscrições que o navegador reportar como
 * inválidas/expiradas (ex.: usuário desinstalou o app ou revogou a
 * permissão) — assim a lista não fica acumulando lixo com o tempo.
 *
 * Não lança exceção: uma falha ao enviar push nunca deve interromper a
 * ação principal (ex.: responder um chamado) que disparou a notificação.
 */
function enviarPushParaUsuario(int $usuarioId, string $titulo, string $corpo, string $url): void
{
    if (!class_exists(WebPush::class)) {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM push_subscriptions WHERE usuario_id = :usuario_id');
    $stmt->execute(['usuario_id' => $usuarioId]);
    $inscricoes = $stmt->fetchAll();

    if (empty($inscricoes)) {
        return;
    }

    try {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);

        $payload = json_encode([
            'titulo' => $titulo,
            'corpo' => $corpo,
            'url' => $url,
            'icone' => BASE_URL . '/assets/icons/icon-192.png',
        ], JSON_UNESCAPED_UNICODE);

        foreach ($inscricoes as $inscricao) {
            $subscription = Subscription::create([
                'endpoint' => $inscricao['endpoint'],
                'keys' => ['p256dh' => $inscricao['p256dh'], 'auth' => $inscricao['auth']],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        foreach ($webPush->flush() as $relatorio) {
            if (!$relatorio->isSuccess() && $relatorio->isSubscriptionExpired()) {
                $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint')
                    ->execute(['endpoint' => $relatorio->getEndpoint()]);
            }
        }
    } catch (\Throwable $e) {
        // Falha ao enviar push (ex.: servidor sem acesso à internet) não
        // pode quebrar a ação que originou a notificação.
    }
}

/**
 * Envia a mesma notificação para todos os usuários de um perfil (ou de
 * vários), exceto o informado em $exceto (tipicamente quem disparou a
 * ação — não faz sentido notificá-lo do próprio ato).
 */
function enviarPushParaPerfis(array $perfis, string $titulo, string $corpo, string $url, ?int $exceto = null): void
{
    if (!class_exists(WebPush::class)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($perfis), '?'));
    $sql = "SELECT id FROM usuarios WHERE perfil IN ($placeholders)";
    $params = $perfis;
    if ($exceto !== null) {
        $sql .= ' AND id != ?';
        $params[] = $exceto;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $usuarioId) {
        enviarPushParaUsuario((int) $usuarioId, $titulo, $corpo, $url);
    }
}
