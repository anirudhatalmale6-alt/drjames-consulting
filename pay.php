<?php
/**
 * Payment link request handler.
 *
 * Takes the POST from pay.html, checks it, writes it to the inbox, mails a
 * copy to the practice and an acknowledgement to the payer, then sends the
 * visitor back to the page with a result flag.
 *
 * Note what this file does NOT do: it takes no card number, no bank detail and
 * no amount that anyone is charged. It collects a request for a payment link,
 * nothing more, so there is no cardholder data anywhere on this server.
 */

declare(strict_types=1);

define('DRJ_INTAKE', true);
require __DIR__ . '/_intake.php';

const TO   = 'info@microbusinessaccountingservices.com';
const FROM = 'info@microbusinessaccountingservices.com';
const PAGE = 'pay.html';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    back(PAGE, '0', 'send');
}

// A robot fills every field it is given. A person never sees this one.
if (field('website') !== '') {
    back(PAGE, '1');                    // look successful, deliver nothing
}

$first    = field('first');
$last     = field('last');
$email    = field('email');
$phone    = field('phone');
$business = field('business');
$invoice  = field('invoice');
$amount   = field('amount');
$method   = field('method');
$message  = trim((string)($_POST['message'] ?? ''));

$name = trim($first . ' ' . $last);

if ($name === '') {
    back(PAGE, '0', 'name');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back(PAGE, '0', 'email');
}
/* A payment link has to be sent somewhere. An address or a number is enough;
   neither is not, because then there is nowhere to send it. */
if ($email === '' && $phone === '') {
    back(PAGE, '0', 'contact');
}

if (mb_strlen($name) > 160 || mb_strlen($email) > 150 || mb_strlen($phone) > 40
    || mb_strlen($business) > 120 || mb_strlen($invoice) > 60
    || mb_strlen($amount) > 40 || mb_strlen($method) > 60
    || mb_strlen($message) > 4000) {
    back(PAGE, '0', 'send');
}

if (!rate_ok('pay')) {
    back(PAGE, '0', 'send');
}

/* ---- compose ---------------------------------------------------------- */
$blank = '(not given)';
$body = "A payment link was requested from the website.\n\n"
      . "Name:        " . $name . "\n"
      . "Business:    " . ($business !== '' ? $business : $blank) . "\n"
      . "Email:       " . ($email    !== '' ? $email    : $blank) . "\n"
      . "Phone:       " . ($phone    !== '' ? $phone    : $blank) . "\n"
      . "Invoice:     " . ($invoice  !== '' ? $invoice  : $blank) . "\n"
      . "Amount:      " . ($amount   !== '' ? $amount   : $blank) . "\n"
      . "Wants to pay by: " . ($method !== '' ? $method : 'no preference') . "\n"
      . "Received:    " . gmdate('Y-m-d H:i:s') . " UTC\n"
      . str_repeat('-', 60) . "\n\n"
      . ($message !== '' ? $message : '(no note left)') . "\n";

// on disk first: this, not mail(), is what makes the request received
$saved = store('payment', $body);

$headers = [
    'From: D. R. James Consulting <' . FROM . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: drjames-pay',
];
if ($email !== '') {
    array_splice($headers, 1, 0, ['Reply-To: ' . oneline($name) . ' <' . $email . '>']);
}

$mailed = @mail(TO, oneline('Payment link requested by ' . $name),
                $body, implode("\r\n", $headers), '-f' . FROM);

/* ---- acknowledge to the payer ------------------------------------------ */
if ($email !== '') {
    $ack = "Hello " . $first . ",\n\n"
         . "Thank you - your request for a payment link has been received by\n"
         . "D. R. James Consulting, LLC. I will send your link shortly.\n\n"
         . "This is what you sent:\n\n"
         . "  Invoice:   " . ($invoice !== '' ? $invoice : 'not given')   . "\n"
         . "  Amount:    " . ($amount  !== '' ? $amount  : 'not given')   . "\n"
         . "  Pay by:    " . ($method  !== '' ? $method  : 'no preference') . "\n\n"
         . "Please note that I will never ask you for card or bank details by\n"
         . "email, by text or over the phone. Your payment link opens the\n"
         . "payment processor's own secure page, and that is the only place\n"
         . "those details should ever be entered.\n\n"
         . "If anything needs changing, reply to this message or call\n"
         . "216-314-2464.\n\n"
         . "Desiree R. James\n"
         . "D. R. James Consulting, LLC\n"
         . "Microbusiness Accounting and Tax Services\n"
         . "216-314-2464 | microbusinessaccountingservices.com\n";

    @mail($email, 'Your payment link request - D. R. James Consulting, LLC', $ack,
          implode("\r\n", [
              'From: D. R. James Consulting <' . FROM . '>',
              'Reply-To: D. R. James Consulting <' . TO . '>',
              'Content-Type: text/plain; charset=UTF-8',
              'MIME-Version: 1.0',
              'X-Mailer: drjames-pay',
          ]), '-f' . FROM);
}

if ($saved || $mailed) {
    back(PAGE, '1');
}
back(PAGE, '0', 'send');
