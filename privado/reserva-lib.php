<?php
declare(strict_types=1);

/* No database or extensions required beyond stock PHP 8. */
final class ReservaError extends RuntimeException
{
    public int $status;
    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function reserva_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
}

/* Anchor on day 1 before moving month: January 31 must not skip February. */
function reserva_end(DateTimeImmutable $now): string
{
    return $now->setTimezone(new DateTimeZone('Europe/Madrid'))->modify('first day of this month')->modify('+2 months')->modify('-1 day')->format('Y-m-d');
}

function reserva_base(string $date): array
{
    $day = (int) (new DateTimeImmutable($date, new DateTimeZone('Europe/Madrid')))->format('N');
    $ranges = $day > 5 ? [] : ($day === 5 ? [[960, 1080]] : (in_array($day, [2, 4], true) ? [[600, 720], [960, 1080]] : [[930, 1170]]));
    $times = [];
    foreach ($ranges as [$start, $end]) {
        for ($minute = $start; $minute < $end; $minute += 30) {
            $times[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        }
    }
    $seed = 0;
    foreach (str_split($date) as $char) {
        $seed = ($seed * 31 + ord($char)) % 65521;
    }
    $busy = $times ? [$seed % count($times)] : [];
    if (count($times) === 8) {
        $busy[] = ($seed % 8 + 3 + intdiv($seed, 7) % 4) % 8;
    }
    return array_values(array_filter($times, static fn($time, $index) => !in_array($index, $busy, true), ARRAY_FILTER_USE_BOTH));
}

function reserva_text(array $input, string $key, int $max, bool $required = false): string
{
    $labels = [
        'nombre' => 'tu nombre', 'salon' => 'el nombre del salón', 'telefono' => 'tu teléfono',
        'email' => 'tu email', 'negocio' => 'tu tipo de negocio', 'negocio_otro' => 'a qué te dedicas',
        'direccion' => 'la dirección del salón', 'mensaje' => 'tu mensaje', 'personas' => 'cuántas personas sois',
    ];
    $label = $labels[$key] ?? 'ese campo';
    $value = $input[$key] ?? '';
    if (!is_string($value) || !preg_match('//u', $value) || strlen($value) > $max * 4) {
        throw new ReservaError('Revisa ' . $label . ': hay caracteres que no podemos leer o es demasiado largo.');
    }
    $value = trim(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', strip_tags($value)) ?? '');
    if ($required && $value === '') {
        throw new ReservaError('Escribe ' . $label . '.');
    }
    if (preg_match_all('/./us', $value) > $max) {
        throw new ReservaError('Acorta ' . $label . ': admite hasta ' . $max . ' caracteres.');
    }
    return $value;
}

function reserva_validate(array $input, DateTimeImmutable $now): array
{
    $data = [];
    if (!is_string($input['web'] ?? '') || trim($input['web'] ?? '') !== '') {
        throw new ReservaError('No se ha podido procesar la solicitud.', 400);
    }
    foreach (['fecha' => 10, 'hora' => 5, 'formato' => 10, 'nombre' => 120, 'salon' => 160, 'negocio' => 40] as $key => $max) {
        $data[$key] = reserva_text($input, $key, $max, true);
    }
    foreach (['direccion' => 300, 'telefono' => 30, 'email' => 254, 'mensaje' => 2000] as $key => $max) {
        $data[$key] = reserva_text($input, $key, $max);
    }
    $now = $now->setTimezone(new DateTimeZone('Europe/Madrid'));
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['fecha'], new DateTimeZone('Europe/Madrid'));
    if (!$date || $date->format('Y-m-d') !== $data['fecha'] || $data['fecha'] < $now->format('Y-m-d') || $data['fecha'] > reserva_end($now)) {
        throw new ReservaError('Elige un día disponible de este mes o del siguiente.');
    }
    if (!in_array($data['hora'], reserva_base($data['fecha']), true) || new DateTimeImmutable($data['fecha'] . ' ' . $data['hora'], new DateTimeZone('Europe/Madrid')) <= $now) {
        throw new ReservaError('Esa hora no está disponible. Elige otro hueco.', 409);
    }
    if (!in_array($data['formato'], ['meet', 'visita'], true)) {
        throw new ReservaError('Elige Google Meet o visita a tu salón.');
    }
    if ($data['formato'] === 'visita' && preg_match_all('/./us', $data['direccion']) < 5) {
        throw new ReservaError('Indica la dirección completa del salón para la visita.');
    }
    if ($data['formato'] === 'meet') {
        $data['direccion'] = '';
    }
    if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $data['email']))) {
        throw new ReservaError('Indica un email válido.');
    }
    if ($data['telefono'] !== '' && (!preg_match('/^[+0-9\s().-]+$/', $data['telefono']) || strlen(preg_replace('/\D/', '', $data['telefono'])) < 7 || strlen(preg_replace('/\D/', '', $data['telefono'])) > 20)) {
        throw new ReservaError('Indica un teléfono válido.');
    }
    if ($data['email'] === '' && $data['telefono'] === '') {
        throw new ReservaError('Indica al menos un teléfono o un email.');
    }
    if (!in_array($data['negocio'], ['Peluquería', 'Barbería', 'Salón de belleza', 'Estética', 'Otro'], true)) {
        throw new ReservaError('Elige el tipo de negocio.');
    }
    $data['negocio_otro'] = $data['negocio'] === 'Otro' ? reserva_text($input, 'negocio_otro', 120, true) : '';
    $people = $input['personas'] ?? null;
    if ((!is_int($people) && !is_string($people)) || filter_var($people, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) === false) {
        throw new ReservaError('Indica cuántas personas sois (entre 1 y 1000).');
    }
    $data['personas'] = (int) $people;
    if (!in_array($input['consentimiento'] ?? null, [true, 'si'], true)) {
        throw new ReservaError('Necesitamos tu consentimiento para gestionar la solicitud.');
    }
    $data['consentimiento'] = true;
    return $data;
}

function reserva_store(string $publicRoot): string
{
    foreach ([$publicRoot . '/../sishow-reservas', $publicRoot . '/privado/reservas'] as $path) {
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            continue;
        }
        if (!is_writable($path)) {
            continue;
        }
        // Defence in depth, also protects an accidentally uploaded copy.
        if (@file_put_contents($path . '/.htaccess', "Require all denied\n", LOCK_EX) === false) {
            continue;
        }
        return $path;
    }
    throw new ReservaError('No podemos guardar la solicitud ahora. Envíanos los datos por correo.', 503);
}

function reserva_lock(string $store)
{
    $lock = @fopen($store . '/.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) {
            fclose($lock);
        }
        throw new ReservaError('La agenda no responde ahora. Prueba de nuevo o escríbenos.', 503);
    }
    return $lock;
}

function reserva_read(string $path): array
{
    $raw = @file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) {
        throw new ReservaError('No podemos comprobar la agenda ahora. Escríbenos por correo.', 503);
    }
    return $data;
}

/* Called only under the shared lock; request records expire after twelve months. */
function reserva_prune(string $store, DateTimeImmutable $now): void
{
    $cutoff = $now->modify('-12 months')->getTimestamp();
    foreach (glob($store . '/*.json') ?: [] as $file) {
        $rate = str_starts_with(basename($file), 'rate-');
        $data = reserva_read($file);
        $expired = $rate ? ($data['inicio'] ?? PHP_INT_MAX) <= $now->getTimestamp() - 3600 : ($data['creadaUnix'] ?? PHP_INT_MAX) <= $cutoff;
        if ($expired && !@unlink($file)) {
            throw new ReservaError('No podemos actualizar la agenda ahora.', 503);
        }
    }
}

function reserva_occupied(string $store, DateTimeImmutable $now): array
{
    $lock = reserva_lock($store);
    try {
        reserva_prune($store, $now);
        $occupied = [];
        foreach (glob($store . '/????-??-??-????.json') ?: [] as $file) {
            $date = substr(basename($file), 0, 10);
            $time = substr(basename($file), 11, 2) . ':' . substr(basename($file), 13, 2);
            if ($date >= $now->format('Y-m-d') && $date <= reserva_end($now)) {
                $occupied[] = ['fecha' => $date, 'hora' => $time];
            }
        }
        return $occupied;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function reserva_body(array $data): string
{
    $names = ['fecha' => 'Día', 'hora' => 'Hora (Europe/Madrid, 30 min)', 'formato' => 'Formato', 'direccion' => 'Dirección', 'nombre' => 'Nombre', 'salon' => 'Salón', 'telefono' => 'Teléfono', 'email' => 'Email', 'negocio' => 'Tipo de negocio', 'negocio_otro' => 'Actividad (Otro)', 'personas' => 'Personas', 'mensaje' => 'Mensaje'];
    $body = "Solicitud de reunión con siShow. Pendiente de confirmación.\n\n";
    foreach ($names as $key => $name) {
        $value = $data[$key] ?? '';
        if (!is_scalar($value)) {
            $value = '';
        }
        if ($key === 'formato') {
            $value = $value === 'visita' ? 'Visita a tu salón' : 'Videollamada (Google Meet)';
        }
        $body .= $name . ': ' . $value . "\n";
    }
    return $body . 'Consentimiento para gestionar la solicitud: ' . (in_array($data['consentimiento'] ?? null, [true, 'si'], true) ? 'Sí' : 'No');
}

function reserva_mail(array $data): bool
{
    $headers = [
        'From: siShow <reuniones@sishow.es>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    if ($data['email'] !== '') {
        $headers[] = 'Reply-To: ' . $data['email'];
    }
    $subject = '=?UTF-8?B?' . base64_encode('Reunión siShow · ' . $data['fecha'] . ' ' . $data['hora']) . '?=';
    return @mail('infosishow@gmail.com', $subject, chunk_split(base64_encode(reserva_body($data))), implode("\r\n", $headers));
}

function reserva_submit(array $input, string $store, DateTimeImmutable $now, string $ip, ?callable $mailer = null): array
{
    $lock = reserva_lock($store);
    try {
        reserva_prune($store, $now);
        // Trust only REMOTE_ADDR, never a visitor-supplied forwarded header.
        $rateFile = $store . '/rate-' . hash('sha256', $ip) . '.json';
        $rate = is_file($rateFile) ? reserva_read($rateFile) : ['inicio' => $now->getTimestamp(), 'intentos' => 0];
        if ($rate['intentos'] >= 8) {
            throw new ReservaError('Has enviado varias solicitudes. Espera una hora o escríbenos por correo.', 429);
        }
        $rate['intentos']++;
        if (@file_put_contents($rateFile, json_encode($rate, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new ReservaError('No podemos procesar la solicitud ahora.', 503);
        }
        $data = reserva_validate($input, $now);
        $file = $store . '/' . $data['fecha'] . '-' . str_replace(':', '', $data['hora']) . '.json';
        if (file_exists($file)) {
            throw new ReservaError('Alguien ha solicitado ese hueco. Elige otra hora.', 409);
        }
        $data['creada'] = $now->format(DATE_ATOM);
        $data['creadaUnix'] = $now->getTimestamp();
        $data['estado'] = 'pendiente';
        $data['privacidadVersion'] = '2026-10-reuniones';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $handle = @fopen($file, 'x');
        if (!$handle) {
            throw new ReservaError('No podemos guardar la solicitud ahora.', 503);
        }
        $written = fwrite($handle, $json);
        $flushed = fflush($handle);
        fclose($handle);
        if ($written !== strlen($json) || !$flushed) {
            @unlink($file);
            throw new ReservaError('No podemos guardar la solicitud ahora.', 503);
        }
        @chmod($file, 0600);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    // A mail failure never loses an already persisted request or releases its slot.
    try {
        $sent = ($mailer ?? 'reserva_mail')($data);
    } catch (Throwable $error) {
        $sent = false;
    }
    return ['ok' => true, 'emailEnviado' => (bool) $sent, 'fecha' => $data['fecha'], 'hora' => $data['hora']];
}
