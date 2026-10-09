<?php
declare(strict_types=1);
require_once __DIR__ . '/privado/reserva-lib.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$json = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
$input = [];
$status = 200;
try {
    if ($method !== 'POST' && !($method === 'GET' && ($_GET['ocupadas'] ?? '') === '1')) {
        header('Allow: GET, POST');
        throw new ReservaError('Usa el formulario de reserva de la landing.', 405);
    }
    if ($method === 'POST') {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
            throw new ReservaError('La solicitud es demasiado larga.', 413);
        }
        if ($json) {
            $raw = file_get_contents('php://input', false, null, 0, 16385);
            if ($raw === false || strlen($raw) > 16384) {
                throw new ReservaError('La solicitud es demasiado larga.', 413);
            }
            $input = json_decode($raw, true);
            if (!is_array($input) || !str_starts_with(ltrim($raw), '{')) {
                $input = [];
                throw new ReservaError('No se ha podido leer la solicitud.', 400);
            }
        } elseif (str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/x-www-form-urlencoded')) {
            $input = $_POST;
        } else {
            throw new ReservaError('Formato de solicitud no admitido.', 415);
        }
    }
    $store = reserva_store(__DIR__);
    $now = reserva_now();
    if ($method === 'GET') {
        $result = ['ocupadas' => reserva_occupied($store, $now)];
    } else {
        $result = reserva_submit($input, $store, $now, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $status = 201;
    }
} catch (ReservaError $error) {
    $status = $error->status;
    $result = ['ok' => false, 'error' => $error->getMessage()];
} catch (Throwable $error) {
    $status = 503;
    $result = ['ok' => false, 'error' => 'No podemos completar el envío ahora. Envíanos los datos por correo.'];
    error_log('siShow reserva: ' . get_class($error));
}
http_response_code($status);
if ($status === 429) {
    header('Retry-After: 3600');
}
if ($method === 'GET' || $json) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
// The native form works without JS. Every failure supplies a prefilled mail alternative.
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'");
$safeInput = [];
foreach ($input as $key => $value) {
    if (is_string($value)) {
        $safeInput[$key] = trim(strip_tags(substr($value, 0, 8000)));
    }
}
$escape = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$success = ($result['ok'] ?? false) === true;
$title = $success ? (($result['emailEnviado'] ?? false) ? 'Te escribimos para confirmar' : 'Tu solicitud está guardada') : 'Revisa tu solicitud';
$message = $success ? (($result['emailEnviado'] ?? false) ? 'Ya tenemos tus datos. La reunión estará confirmada cuando contactemos contigo.' : 'Tu hueco está guardado, pero no hemos podido avisar al equipo. Envíanos los datos por correo para que podamos confirmar contigo.') : $result['error'];
$mail = 'mailto:infosishow@gmail.com?subject=' . rawurlencode('Solicitud de reunión con siShow') . '&body=' . rawurlencode(reserva_body($safeInput));
?><!doctype html>
<html lang="es" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><meta name="color-scheme" content="light dark"><title><?= $escape($title) ?> · siShow</title><link href="favicon.svg" rel="icon" type="image/svg+xml"><link href="favicon-16.png" rel="icon" sizes="16x16" type="image/png"><link href="favicon-32.png" rel="icon" sizes="32x32" type="image/png"><link href="favicon-48.png" rel="icon" sizes="48x48" type="image/png"><link href="favicon-512.png" rel="icon" sizes="512x512" type="image/png"><link href="apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180"><script src="theme-init.js"></script><link rel="stylesheet" href="theme.css"><link rel="stylesheet" href="legal.css"><script defer src="theme.js"></script></head><body><header class="lg-header"><div class="lg-header-row"><a class="lg-logo" href="index.html" aria-label="siShow, inicio"><svg class="brand-mark" data-brand="v3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 -3 176 63"><g class="logo-i-dot" transform="translate(25.576 -1.8) scale(.62)"><path class="logo-calendar-body" d="M8 10H31L25 29H2Z"/><path class="logo-calendar" d="M8 10H31L25 29H2Z M7 29V33H29L32 10V27"/><path class="logo-calendar logo-rings" d="M13 12C10 13 9 10 9 8V5C9 0 15 0 15 5V9 M21 12C18 13 17 10 17 8V5C17 0 23 0 23 5V9 M29 12C26 13 25 10 25 8V5C25 0 31 0 31 5V9"/><path class="logo-check" pathLength="1" stroke-width="3.5" d="M11 21L15 25L23 16"/></g><g class="logo-letters"><path class="logo-letter" data-letter="s" d="M402 -18Q225 -18 148.5 58.0Q72 134 65 309Q64 344 64.5 389.0Q65 434 67 452H293Q291 399 291.0 354.5Q291 310 292 282Q295 224 322.5 200.0Q350 176 402 176Q465 176 493.0 200.0Q521 224 522 282Q522 309 522.0 317.5Q522 326 522.0 334.0Q522 342 522 366Q522 408 507.0 433.5Q492 459 453 471L323 508Q238 534 179.0 571.5Q120 609 89.5 668.0Q59 727 58 815Q58 834 58.0 851.0Q58 868 58 886Q59 1064 138.5 1141.0Q218 1218 414 1218Q593 1218 667.5 1143.5Q742 1069 749 897Q751 863 750.0 817.0Q749 771 747 751H513Q514 773 514.5 808.0Q515 843 514.5 876.0Q514 909 513 926Q510 980 489.0 1002.0Q468 1024 414 1024Q357 1024 334.5 1002.0Q312 980 310 926Q309 901 308.5 887.0Q308 873 308 837Q308 791 322.0 759.5Q336 728 385 715L500 686Q629 654 694.5 584.5Q760 515 760 380Q760 362 760.0 341.5Q760 321 760 303Q759 131 677.0 56.5Q595 -18 402 -18Z" transform="translate(4.000 58) scale(0.031 -0.031)"/><path class="logo-i-stem" data-letter="i" d="M90 0V1200H348V0Z" transform="translate(29.327 58) scale(0.031 -0.031)"/><path class="logo-letter" data-letter="S" d="M442 -18Q243 -18 158.0 67.0Q73 152 64 349Q63 388 63.0 430.0Q63 472 65.0 513.5Q67 555 71 595H314Q310 518 309.5 442.5Q309 367 314 302Q319 248 349.5 220.5Q380 193 442 193Q503 193 531.0 220.5Q559 248 564 302Q568 342 569.5 378.0Q571 414 569.5 451.0Q568 488 564 529Q561 579 539.5 617.0Q518 655 468 668L319 706Q222 731 166.0 778.5Q110 826 85.5 898.0Q61 970 58 1068Q57 1117 57.0 1162.5Q57 1208 58 1253Q63 1384 101.5 1464.5Q140 1545 222.5 1581.5Q305 1618 442 1618Q635 1618 721.5 1533.5Q808 1449 816 1253Q818 1207 817.0 1140.0Q816 1073 813 1011H565Q568 1083 569.0 1155.5Q570 1228 567 1300Q565 1353 532.5 1380.0Q500 1407 442 1407Q380 1407 351.0 1380.0Q322 1353 316 1300Q311 1242 311.0 1184.0Q311 1126 316 1068Q321 1016 342.5 980.0Q364 944 418 931L549 900Q652 875 711.0 825.5Q770 776 795.5 702.0Q821 628 824 529Q825 496 825.0 466.5Q825 437 824.5 408.5Q824 380 822 349Q814 152 728.5 67.0Q643 -18 442 -18Z" transform="translate(42.843 58) scale(0.031 -0.031)"/><path class="logo-letter" data-letter="h" d="M89 0V1600H347V1076H393Q432 1145 476.0 1181.5Q520 1218 608 1218Q725 1218 790.0 1146.0Q855 1074 855 906V0H597V927Q597 980 572.5 1004.5Q548 1029 499 1029Q455 1029 410.0 1004.0Q365 979 347 934V0Z" transform="translate(70.185 58) scale(0.031 -0.031)"/><path class="logo-letter" data-letter="o" d="M450 -18Q316 -18 236.5 18.5Q157 55 120.0 135.5Q83 216 76 348Q74 394 72.5 459.5Q71 525 71.0 597.5Q71 670 72.0 737.5Q73 805 76 854Q83 984 119.5 1064.5Q156 1145 235.5 1181.5Q315 1218 450 1218Q585 1218 663.5 1181.0Q742 1144 778.5 1063.5Q815 983 822 854Q824 808 825.0 742.0Q826 676 826.0 603.5Q826 531 825.0 464.0Q824 397 822 348Q815 218 778.5 137.0Q742 56 663.5 19.0Q585 -18 450 -18ZM450 176Q511 176 536.5 203.0Q562 230 564 284Q566 363 567.5 442.0Q569 521 569.0 601.0Q569 681 567.5 760.0Q566 839 564 917Q562 971 536.5 997.5Q511 1024 450 1024Q390 1024 363.0 997.5Q336 971 334 917Q332 839 330.5 759.5Q329 680 329.0 600.5Q329 521 330.5 441.5Q332 362 334 284Q336 230 363.0 203.0Q390 176 450 176Z" transform="translate(99.294 58) scale(0.031 -0.031)"/><path class="logo-letter" data-letter="w" d="M179 0 29 1200H289L340 597L359 191H428L442 597L496 1198H915L967 597L980 191H1049L1069 597L1122 1200H1384L1229 0H792L756 466L740 1008H670L652 466L616 0Z" transform="translate(127.101 58) scale(0.031 -0.031)"/></g></svg></a><a class="lg-back" href="index.html#reunion">siShow · Volver a la reserva</a><button class="theme-toggle lg-theme-control" id="theme-toggle" type="button" aria-label="Tema: oscuro. Cambiar a claro."><span id="theme-label">Oscuro</span></button></div></header><main class="lg-body"><h1><?= $escape($title) ?></h1><p><?= $escape($message) ?></p><pre><?= $escape(reserva_body($safeInput)) ?></pre><?php if (!$success || !($result['emailEnviado'] ?? false)): ?><p><a href="<?= $escape($mail) ?>">Enviar los datos por correo ↗</a></p><?php endif; ?><p><a href="index.html#reunion">Volver a la reserva</a></p></main></body></html>
