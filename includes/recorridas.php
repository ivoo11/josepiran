<?php
declare(strict_types=1);
const CONSENT_VERSION = '2026-10-14-v1';
const CONSENT_TEXT = 'Al hacer clic en “SUMARME A RECORRER TIGRE”, acepto que el equipo de José Pirán utilice mis datos para contactarme y coordinar mi participación en recorridas por Tigre. También autorizo, de forma gratuita, la captación y publicación de mi imagen y voz durante esas actividades para su difusión política en el sitio web y las redes sociales oficiales de José Pirán y su equipo.';
const LOCALIDADES = ['Benavidez', 'Delta', 'Dique Luján', 'Don Torcuato', 'El Talar', 'General Pacheco', 'Nuevo Delta', 'Ricardo Rojas', 'Rincón de Milberg', 'Tigre Centro', 'Troncos del Talar', 'Otra/No estoy seguro'];
function escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function validate_registration(array $input): array {
    $data = []; $errors = [];
    foreach (['nombre', 'apellido', 'email', 'telefono', 'localidad'] as $field) {
        $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
    }
    foreach (['nombre', 'apellido'] as $field) {
        if ($data[$field] === '' || mb_strlen($data[$field]) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $data[$field])) $errors[$field] = 'Completá este campo (hasta 80 caracteres).';
    }
    if (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Ingresá un correo electrónico válido.';
    $digits = preg_replace('/\D/', '', $data['telefono']);
    if (!preg_match('/^\+?[0-9 ()\-]+$/', $data['telefono']) || strlen($digits) < 10 || strlen($digits) > 15 || strlen($data['telefono']) > 40) $errors['telefono'] = 'Ingresá un teléfono con código de área, entre 10 y 15 dígitos.';
    if (!in_array($data['localidad'], LOCALIDADES, true)) $errors['localidad'] = 'Seleccioná una localidad.';
    return [$data, $errors];
}
