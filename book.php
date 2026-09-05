<?php
/**
 * Appointment request handler.
 *
 * Takes the POST from book.html, checks it, writes it to the inbox, mails a
 * copy to the practice and an acknowledgement to the visitor, then sends the
 * visitor back to the page with a result flag. It never prints anything
 * itself, so there is no second page to style and nothing that can echo a
 * visitor's input back into a browser.
 */

declare(strict_types=1);

define('DRJ_INTAKE', true);
require __DIR__ . '/_intake.php';

const TO   = 'info@microbusinessaccountingservices.com';
const FROM = 'info@microbusinessaccountingservices.com';
const PAGE = 'book.html';

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
$need     = field('need');
$day      = field('day');
$time     = field('time');
$via      = field('via');
$message  = trim((string)($_POST['message'] ?? ''));

/* ---- the short assessment ----------------------------------------------
   Seven questions, so the free fifteen minutes is spent on the business
   rather than on collecting facts that could have been collected here.
   Every one of them is OPTIONAL on purpose: a caller who does not know how
   far behind their books are must still be able to ask for an appointment,
   and "not sure" is itself a useful answer. */
$ASSESS = [
    'entity'  => 'Structure',
    'years'   => 'Trading',
    'books'   => 'Books in',
    'current' => 'Books are',
    'helpers' => 'Pays others',
    'filings' => 'Tax filings',
    'urgency' => 'Wants to start',
];
$assess = [];
foreach ($ASSESS as $k => $label) {
    $v = field($k);
    if (mb_strlen($v) > 90) {
        back(PAGE, '0', 'send');
    }
    $assess[$label] = $v;
}

$name = trim($first . ' ' . $last);

if ($name === '') {
    back(PAGE, '0', 'name');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back(PAGE, '0', 'email');
}
/* Someone who asks to be phoned may not want to leave an address, and someone
   without a phone may only leave one. One of the two is enough; neither is
   not, because then there is no way to confirm the appointment. */
if ($email === '' && $phone === '') {
    back(PAGE, '0', 'contact');
}

if (mb_strlen($name) > 160 || mb_strlen($email) > 150 || mb_strlen($phone) > 40
    || mb_strlen($business) > 120 || mb_strlen($need) > 80 || mb_strlen($day) > 80
    || mb_strlen($time) > 60 || mb_strlen($via) > 40 || mb_strlen($message) > 4000) {
    back(PAGE, '0', 'send');
}

if (!rate_ok('book')) {
    back(PAGE, '0', 'send');
}

/* ---- compose ---------------------------------------------------------- */
$blank = '(not given)';
$body = "An appointment was requested from the website.\n\n"
      . "Name:        " . $name . "\n"
      . "Business:    " . ($business !== '' ? $business : $blank) . "\n"
      . "Email:       " . ($email    !== '' ? $email    : $blank) . "\n"
      . "Phone:       " . ($phone    !== '' ? $phone    : $blank) . "\n"
      . "Reach by:    " . ($via      !== '' ? $via      : 'no preference') . "\n"
      . "Needs help:  " . ($need     !== '' ? $need     : $blank) . "\n"
      . "Preferred:   " . trim(($day !== '' ? $day : 'any day') . ', '
                             . ($time !== '' ? $time : 'any time')) . " (Eastern)\n"
      . "Received:    " . gmdate('Y-m-d H:i:s') . " UTC\n";

/* the assessment, printed only where it was answered — a wall of "(not
   given)" makes the answers that ARE there harder to find */
$answered = array_filter($assess, static function ($v) { return $v !== ''; });
if ($answered) {
    $body .= "\nAssessment\n";
    foreach ($answered as $label => $v) {
        $body .= "  " . str_pad($label . ':', 16) . $v . "\n";
    }
} else {
    $body .= "\nAssessment:  (none of it answered)\n";
}

$body .= str_repeat('-', 60) . "\n\n"
      . ($message !== '' ? $message : '(no note left)') . "\n";

// on disk first: this, not mail(), is what makes the request received
$saved = store('appointment', $body);

$headers = [
    'From: D. R. James Consulting <' . FROM . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: drjames-book',
];
if ($email !== '') {
    array_splice($headers, 1, 0, ['Reply-To: ' . oneline($name) . ' <' . $email . '>']);
}

$mailed = @mail(TO, oneline('Appointment request from ' . $name),
                $body, implode("\r\n", $headers), '-f' . FROM);

/* ---- acknowledge to the visitor ---------------------------------------- */
if ($email !== '') {
    $ack = "Hello " . $first . ",\n\n"
         . "Thank you for requesting a free 15-minute consultation with\n"
         . "D. R. James Consulting, LLC. Your request has been received and\n"
         . "I will be in touch to confirm a time.\n\n"
         . "This is what you sent:\n\n"
         . "  Preferred day:   " . ($day  !== '' ? $day  : 'any day')  . "\n"
         . "  Preferred time:  " . ($time !== '' ? $time : 'any time') . " (Eastern)\n"
         . "  Help needed:     " . ($need !== '' ? $need : $blank)     . "\n\n"
         . "If anything needs changing, reply to this message or call\n"
         . "216-314-2464.\n\n"
         . "Desiree R. James\n"
         . "D. R. James Consulting, LLC\n"
         . "Microbusiness Accounting and Tax Services\n"
         . "216-314-2464 | microbusinessaccountingservices.com\n";

    @mail($email, 'Your appointment request - D. R. James Consulting, LLC', $ack,
          implode("\r\n", [
              'From: D. R. James Consulting <' . FROM . '>',
              'Reply-To: D. R. James Consulting <' . TO . '>',
              'Content-Type: text/plain; charset=UTF-8',
              'MIME-Version: 1.0',
              'X-Mailer: drjames-book',
          ]), '-f' . FROM);
}

if ($saved || $mailed) {
    back(PAGE, '1');
}
back(PAGE, '0', 'send');
