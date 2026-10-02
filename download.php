<?php
// AVENLO secure Stripe download endpoint.
// Requires config.php with STRIPE_SECRET_KEY. Keep config.php out of GitHub.
require __DIR__ . '/config.php';

$sessionId = $_GET['session_id'] ?? '';
if (!preg_match('/^cs_[A-Za-z0-9_\-]+$/', $sessionId)) {
    http_response_code(400);
    exit('Invalid download session.');
}

function stripe_get(string $path, string $secret): array {
    $ch = curl_init('https://api.stripe.com' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        return [null, $code];
    }
    $json = json_decode($body, true);
    return [$json, $code];
}

[$session, $status] = stripe_get('/v1/checkout/sessions/' . rawurlencode($sessionId) . '?expand[]=line_items.data.price', STRIPE_SECRET_KEY);
if (!$session || ($session['payment_status'] ?? '') !== 'paid') {
    http_response_code(403);
    exit('Payment not verified.');
}

$expectedPaymentLink = 'plink_1ULxYxBwiny2pl6Cm7Z2p0xY';
if (($session['payment_link'] ?? '') !== $expectedPaymentLink) {
    http_response_code(403);
    exit('This purchase is not authorised for this download.');
}

$file = __DIR__ . '/protected/workshop-bin-system-v2.zip';
if (!is_file($file)) {
    http_response_code(500);
    exit('Download package unavailable.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="AVENLO_Workshop_Bin_System_V2.zip"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
readfile($file);
exit;
