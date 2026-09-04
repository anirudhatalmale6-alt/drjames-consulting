<?php
/**
 * Contact form handler.
 *
 * Takes the POST from contact.html, checks it, sends it to the practice
 * mailbox and sends the visitor back to the page with a result flag.
 * It never prints anything itself, so there is no second page to style and
 * nothing that can echo a visitor's input back into a browser.
 */

declare(strict_types=1);

const TO        = 'info@microbusinessaccountingservices.com';
const FROM      = 'info@microbusinessaccountingservices.com';
const PAGE      = 'contact.html';
const RATE_MAX  = 5;      // messages accepted from one address ...
const RATE_WIN  = 3600;   // ... per this many seconds

/** Send the visitor back to the page and stop. */
function back(string $sent, string $why = ''): void {
    $q = 'sent=' . $sent . ($why !== '' ? '&why=' . rawurlencode($why) : '');
    header('Location: ' . PAGE . '?' . $q, true, 303);
    exit;
}

/** Strip anything that could open a second header line. */
function oneline(string $s): string {
    return trim(preg_replace('/[\r\n\t]+/', ' ', $s));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    back('0', 'send');
}

// A robot fills every field it is given. A person never sees this one.
if (oneline((string)($_POST['website'] ?? '')) !== '') {
    back('1');                       // look successful, deliver nothing
}

$first   = oneline((string)($_POST['first']   ?? ''));
$last    = oneline((string)($_POST['last']    ?? ''));
$email   = oneline((string)($_POST['email']   ?? ''));
$phone   = oneline((string)($_POST['phone']   ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$name = trim($first . ' ' . $last);
if ($name === '')                                         back('0', 'name');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) back('0', 'email');
if ($message === '')                                      back('0', 'message');

if (mb_strlen($name) > 160 || mb_strlen($email) > 150
    || mb_strlen($phone) > 40 || mb_strlen($message) > 4000) {
    back('0', 'send');
}

/* ---- rate limit -------------------------------------------------------
   One file per address, holding the timestamps of accepted messages. Only
   entries that have aged out of the window are dropped; the entries the
   limit is counted from are never removed by the thing they are limiting,
   or the limit would reset itself every time it was used. */
$ip   = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$file = sys_get_temp_dir() . '/drj-contact-' . hash('sha256', $ip) . '.log';
$now  = time();
$hits = [];
if (is_readable($file)) {
    foreach (explode("\n", (string)file_get_contents($file)) as $line) {
        $t = (int)trim($line);
        if ($t > 0 && $now - $t < RATE_WIN) {
            $hits[] = $t;
        }
    }
}
if (count($hits) >= RATE_MAX) {
    back('0', 'send');
}

/* ---- compose ---------------------------------------------------------- */
$subject = 'Website message from ' . $name;
$body = "A message was sent from the Contact page.\n\n"
      . "Name:    " . $name . "\n"
      . "Email:   " . $email . "\n"
      . "Phone:   " . ($phone !== '' ? $phone : '(not given)') . "\n"
      . "Sent:    " . gmdate('Y-m-d H:i:s') . " UTC\n"
      . str_repeat('-', 52) . "\n\n"
      . $message . "\n";

$headers = [
    'From: D. R. James Consulting <' . FROM . '>',
    'Reply-To: ' . oneline($name) . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: drjames-contact',
];

$ok = @mail(TO, oneline($subject), $body, implode("\r\n", $headers), '-f' . FROM);

if (!$ok) {
    back('0', 'send');
}

$hits[] = $now;
@file_put_contents($file, implode("\n", $hits), LOCK_EX);

back('1');
