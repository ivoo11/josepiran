<?php
declare(strict_types=1);
require __DIR__ . '/includes/recorridas.php';
require_once __DIR__ . '/includes/assets.php';
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
session_start();
$configPath = getenv('PIRAN_CONFIG') ?: dirname(__DIR__) . '/private/piran-config.php';
$config = is_file($configPath) ? require $configPath : [];
$ready = !empty($config['enabled']) && !empty($config['dsn']) && !empty($config['user']) && !empty($config['password']) && strlen($config['rate_secret'] ?? '') >= 64;
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['submission'] ??= bin2hex(random_bytes(32));
$data = array_fill_keys(['nombre','apellido','email','telefono','localidad'], '');
$errors = []; $notice = ''; $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$data, $errors] = validate_registration($_POST);
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']) || !is_string($_POST['submission'] ?? null) || !hash_equals($_SESSION['submission'], $_POST['submission'])) {
        http_response_code(403); $notice = 'La sesión venció. Recargá la página y volvé a intentarlo.';
    } elseif (($_POST['consent_version'] ?? '') !== CONSENT_VERSION) {
        http_response_code(409); $notice = 'El texto de autorización se actualizó. Revisalo y volvé a enviar el formulario.';
    } elseif (($_POST['website'] ?? '') !== '') {
        http_response_code(400); $notice = 'No pudimos procesar el envío.';
    } elseif (!$ready) {
        http_response_code(503); $notice = 'Las inscripciones todavía no están habilitadas. Volvé a intentar más adelante.';
    } elseif ($errors) {
        http_response_code(422); $notice = 'Revisá los campos indicados. Tus datos todavía no se enviaron.';
    } else {
        try {
            $db = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
            $db->exec("SET time_zone = '+00:00'");
            // The same submission token makes retries idempotent, even after a lost response.
            $existing = $db->prepare('SELECT id FROM inscripciones WHERE solicitud = ?');
            $existing->execute([$_SESSION['submission']]);
            if (!$existing->fetchColumn()) {
                $key = hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . gmdate('Y-m-d-H'), $config['rate_secret']);
                $db->prepare('INSERT INTO limites_envio (clave, intentos, vence) VALUES (?, 1, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 HOUR)) ON DUPLICATE KEY UPDATE intentos = intentos + 1')->execute([$key]);
                $count = $db->prepare('SELECT intentos FROM limites_envio WHERE clave = ?'); $count->execute([$key]);
                if ((int)$count->fetchColumn() > 10) {
                    http_response_code(429); $notice = 'Hubo varios envíos desde esta conexión. Probá nuevamente más tarde.';
                } else {
                    $stmt = $db->prepare('INSERT INTO inscripciones (solicitud, nombre, apellido, email, telefono, localidad, consentimiento_version, consentimiento_texto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$_SESSION['submission'], $data['nombre'], $data['apellido'], $data['email'], $data['telefono'], $data['localidad'], CONSENT_VERSION, CONSENT_TEXT]);
                }
            }
            if ($notice === '') {
                $_SESSION['registered'] = true;
                header('Location: sumarme.php?gracias=1', true, 303); exit;
            }
        } catch (Throwable $e) {
            http_response_code(503); $notice = 'No pudimos confirmar la inscripción. Tus datos siguen en el formulario; intentá nuevamente en unos minutos.';
            error_log('Piran registration: database operation failed');
        }
    }
}
if (isset($_GET['gracias']) && !empty($_SESSION['registered'])) $success = true;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#321f55">
<title>Sumate a recorrer Tigre | José Pirán</title>
<link rel="stylesheet" href="<?= asset_url('css/recorridas.css') ?>">
</head>
<body>
<main class="card">
<a class="back" href="./">← Volver al sitio</a>
<?php if ($success): ?>
<h1>¡Gracias por sumarte!</h1>
<p class="intro">Recibimos tus datos. El equipo te contactará para coordinar próximas recorridas.</p>
<a class="submit" href="./">VOLVER AL INICIO</a>
<?php else: ?>
<h1>Sumate a<br>recorrer Tigre</h1>
<p class="intro">Dejanos tus datos para que podamos contactarte y coordinar próximas recorridas.</p>
<?php if (!$ready): ?><p class="notice">Las inscripciones todavía no están habilitadas.</p><?php endif; ?>
<?php if ($notice): ?><div class="notice" role="alert"><?= escape($notice) ?></div><?php endif; ?>
<form method="post" action="sumarme.php">
<input type="hidden" name="consent_version" value="<?= escape(CONSENT_VERSION) ?>">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<input type="hidden" name="submission" value="<?= escape($_SESSION['submission']) ?>">
<div class="trap" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
<p class="hint">Todos los campos son obligatorios.</p>
<div class="fields">
<?php foreach (['nombre'=>['Nombre','text','given-name'], 'apellido'=>['Apellido','text','family-name'], 'email'=>['Correo electrónico','email','email'], 'telefono'=>['Teléfono / WhatsApp','tel','tel']] as $name=>$field): ?>
<div class="field">
<label for="<?= $name ?>"><?= $field[0] ?></label>
<input id="<?= $name ?>" name="<?= $name ?>" type="<?= $field[1] ?>" autocomplete="<?= $field[2] ?>" required maxlength="<?= $name === 'email' ? 254 : ($name === 'telefono' ? 40 : 80) ?>" value="<?= escape($data[$name]) ?>" <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="error-'.$name.'"' : '' ?>>
<?php if (isset($errors[$name])): ?><p class="error" id="error-<?= $name ?>"><?= escape($errors[$name]) ?></p><?php endif; ?>
<?php if ($name === 'telefono'): ?><p class="hint">Incluí el código de área.</p><?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<fieldset <?= isset($errors['localidad']) ? 'aria-describedby="error-localidad"' : '' ?>>
<legend>¿En qué localidad de Tigre vivís?</legend>
<div class="locations">
<?php foreach (LOCALIDADES as $i=>$localidad): ?>
<label class="option"><input type="radio" name="localidad" value="<?= escape($localidad) ?>" required <?= $data['localidad'] === $localidad ? 'checked' : '' ?>><span><?= escape($localidad) ?></span></label>
<?php endforeach; ?>
</div>
<?php if (isset($errors['localidad'])): ?><p class="error" id="error-localidad"><?= escape($errors['localidad']) ?></p><?php endif; ?>
</fieldset>
<p class="consent"><?= escape(CONSENT_TEXT) ?></p>
<?php if (!empty($config['privacy_url'])): ?><p class="hint"><a href="<?= escape($config['privacy_url']) ?>">Política de privacidad</a></p><?php endif; ?>
<button class="submit" type="submit" <?= !$ready ? 'disabled' : '' ?>>SUMARME A RECORRER TIGRE</button>
</form>
<?php endif; ?>
</main>
</body>
</html>
