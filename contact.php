<?php
/**
 * Contact form handler.
 *
 * Takes the POST from contact.html, checks it, writes it to the inbox, mails
 * a copy to the practice and sends the visitor back to the page with a result
 * flag. It never prints anything itself, so there is no second page to style
 * and nothing that can echo a visitor's input back into a browser.
 */

declare(strict_types=1);

define('DRJ_INTAKE', true);
require __DIR__ . '/_intake.php';

const TO   = 'info@microbusinessaccountingservices.com';
const FROM = 'info@microbusinessaccountingservices.com';
const PAGE = 'contact.html';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    back(PAGE, '0', 'send');
}

// A robot fills every field it is given. A person never sees this one.
if (field('website') !== '') {
    back(PAGE, '1');                     // look successful, deliver nothing
}

$first   = field('first');
$last    = field('last');
$email   = field('email');
$phone   = field('phone');
$message = trim((string)($_POST['message'] ?? ''));

$name = trim($first . ' ' . $last);
if ($name === '') {
    back(PAGE, '0', 'name');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back(PAGE, '0', 'email');
}
if ($message === '') {
    back(PAGE, '0', 'message');
}

if (mb_strlen($name) > 160 || mb_strlen($email) > 150
    || mb_strlen($phone) > 40 || mb_strlen($message) > 4000) {
    back(PAGE, '0', 'send');
}

if (!rate_ok('contact')) {
    back(PAGE, '0', 'send');
}

/* ---- compose ---------------------------------------------------------- */
$body = "A message was sent from the Contact page.\n\n"
      . "Name:    " . $name . "\n"
      . "Email:   " . $email . "\n"
      . "Phone:   " . ($phone !== '' ? $phone : '(not given)') . "\n"
      . "Sent:    " . gmdate('Y-m-d H:i:s') . " UTC\n"
      . str_repeat('-', 52) . "\n\n"
      . $message . "\n";

// on disk first: this, not mail(), is what makes the message received
$saved = store('message', $body);

$headers = [
    'From: D. R. James Consulting <' . FROM . '>',
    'Reply-To: ' . oneline($name) . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: drjames-contact',
];

$mailed = @mail(TO, oneline('Website message from ' . $name),
                $body, implode("\r\n", $headers), '-f' . FROM);

if ($saved || $mailed) {
    back(PAGE, '1');
}
back(PAGE, '0', 'send');
