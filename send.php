<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

function page(string $heading, string $message, int $status): void {
    http_response_code($status);
    $heading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $heading . '</title><link rel="stylesheet" href="style.css"></head><body><main class="section"><div class="wrap"><div class="card" style="padding:32px;max-width:640px;margin:40px auto"><h1>' . $heading . '</h1><p>' . $message . '</p><a class="btn" href="apply/index.html" style="color:#ffffff !important;text-decoration:none;">Вернуться к форме</a></div></div></main></body></html>';

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    page('Метод не поддерживается', 'Используйте форму подачи заявки.', 405);
}
if (!empty($_POST['website'])) {
    page('Заявка принята', 'Спасибо за обращение.', 200);
}
if (($_POST['consent'] ?? '') !== 'yes') {
    page('Необходимо согласие', 'Подтвердите согласие на обработку персональных данных.', 422);
}

$labels = [
    'voditel' => 'Водитель',
    'povar' => 'Повар',
    'svyazist' => 'Связист',
    'logist' => 'Логист / Кладовщик',
    'medik' => 'Медик / Санитар',
    'mehanik' => 'Механик / Техник',
];
$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
$region = trim((string)($_POST['region'] ?? ''));
$vacancy = (string)($_POST['vacancy'] ?? '');
$len = static fn(string $s): int => function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);

if ($name === '' || $len($name) > 150 || preg_match('/[\r\n]/', $name)
    || !preg_match('/^[+0-9()\-\s]{7,30}$/D', $phone)
    || $age === false || $age < 18 || $age > 60
    || $region === '' || $len($region) > 150 || preg_match('/[\r\n]/', $region)
    || !isset($labels[$vacancy])) {
    page('Проверьте данные', 'Заполните обязательные поля корректно.', 422);
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}
$now = time();
if (isset($_SESSION['last_form_submit']) && $now - (int)$_SESSION['last_form_submit'] < 45) {
    page('Подождите немного', 'Повторную заявку можно отправить через 45 секунд.', 429);
}

$configPath = dirname(__DIR__, 2) . '/mail_config.php';
if (!is_readable($configPath)) {
    error_log('Mail configuration file not found or unreadable');
    page('Ошибка настройки', 'Отправка временно недоступна.', 503);
}
$config = require $configPath;
if (!is_array($config) || empty($config['username']) || empty($config['password'])) {
    error_log('Mail configuration incomplete');
    page('Ошибка настройки', 'Отправка временно недоступна.', 503);
}

$body = "Новая заявка с сайта набор-тыл.рф\n\n"
    . "ФИО: {$name}\nТелефон: {$phone}\nВозраст: {$age}\n"
    . "Регион: {$region}\nВакансия: {$labels[$vacancy]}\n\n"
    . "Согласие на обработку данных: да\n"
    . 'Время сервера: ' . date('Y-m-d H:i:s P');

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.mail.ru';
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->Timeout = 20;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->setFrom('contract_rus_rf@mail.ru', 'Служба по контракту');
    $mail->addAddress('contract_rus_rf@mail.ru');
    $mail->isHTML(false);
    $mail->Subject = 'Новая заявка — Служба по контракту';
    $mail->Body = $body;
    $mail->send();
    $_SESSION['last_form_submit'] = $now;
    page('Заявка отправлена', 'Спасибо! Заявка передана почтовому серверу. Мы свяжемся с вами после её получения.', 200);
} catch (Exception $e) {
    error_log('SMTP application delivery failed: ' . $mail->ErrorInfo);
    page('Ошибка отправки', 'Не удалось отправить заявку. Попробуйте позже или свяжитесь с нами по электронной почте.', 503);
}
