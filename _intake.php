<?php
/**
 * Shared intake helpers for the Contact and Appointment Request forms.
 *
 * The important one is store(). The site sends mail with mail(), and mail()
 * returning true only means the local mail system accepted the message for
 * delivery — it is not a receipt. Right now the domain publishes no MX record
 * at all, so nothing anywhere accepts mail for it and every enquiry sent by
 * mail() alone would be lost silently. Every accepted submission is therefore
 * written to disk first, and the visitor is told it went through if the write
 * succeeded, whether or not the mail did.
 */

declare(strict_types=1);

if (!defined('DRJ_INTAKE')) {          // not a page; only ever included
    http_response_code(404);
    exit;
}

const INBOX     = __DIR__ . '/_inbox';
const RATE_MAX  = 5;                   // submissions accepted from one address ...
const RATE_WIN  = 3600;                // ... per this many seconds

/** Strip anything that could open a second header line. */
function oneline(string $s): string
{
    return trim((string)preg_replace('/[\r\n\t]+/', ' ', $s));
}

/** Read a posted field, folded to one line. */
function field(string $key): string
{
    return oneline((string)($_POST[$key] ?? ''));
}

/** Send the visitor back to a page and stop. */
function back(string $page, string $sent, string $why = ''): void
{
    $q = 'sent=' . $sent . ($why !== '' ? '&why=' . rawurlencode($why) : '');
    header('Location: ' . $page . '?' . $q, true, 303);
    exit;
}

/**
 * Make sure the inbox exists and cannot be read over the web.
 * The rules are written every time they are missing, so restoring the site
 * from a backup that dropped dot-files cannot quietly expose the folder.
 */
function inbox_ready(): bool
{
    if (!is_dir(INBOX) && !@mkdir(INBOX, 0700, true) && !is_dir(INBOX)) {
        return false;
    }
    $ht = INBOX . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht,
            "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    if (!is_file(INBOX . '/index.html')) {
        @file_put_contents(INBOX . '/index.html', '');
    }
    return true;
}

/**
 * Write one submission to the inbox.
 * Returns true only if the bytes are on disk — the caller reports success to
 * the visitor on the strength of this, not on the strength of mail().
 */
function store(string $kind, string $body): bool
{
    if (!inbox_ready()) {
        return false;
    }
    $name = gmdate('Y-m-d_His') . '_' . $kind . '_' . bin2hex(random_bytes(4)) . '.txt';
    $path = INBOX . '/' . $name;
    $n    = @file_put_contents($path, $body, LOCK_EX);
    if ($n === false || $n < strlen($body)) {
        @unlink($path);
        return false;
    }
    @chmod($path, 0600);
    return true;
}

/**
 * Rate limit, one file of timestamps per address.
 * Only entries that have aged out of the window are dropped. The entries the
 * limit is counted from are never removed by the thing they are limiting, or
 * the limit would reset itself every time it was used.
 */
function rate_ok(string $tag): bool
{
    $ip   = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $file = sys_get_temp_dir() . '/drj-' . $tag . '-' . hash('sha256', $ip) . '.log';
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
        return false;
    }
    $hits[] = $now;
    @file_put_contents($file, implode("\n", $hits), LOCK_EX);
    return true;
}
