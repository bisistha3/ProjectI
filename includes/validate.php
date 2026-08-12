<?php
/**
 * HealthFlow — Input Validation Helper
 * Server-side validation for all form fields.
 */

class Validator {
    private array $errors = [];

    /**
     * Validate a required field is not empty.
     */
    public function required(string $field, string $value, string $label = ''): self {
        if (trim($value) === '') {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' is required.';
        }
        return $this;
    }

    /**
     * Validate a person's name:
     * - Cannot start with a number
     * - Only letters, spaces, apostrophes and hyphens
     */
    public function name(string $field, string $value, string $label = 'Name'): self {
        $val = trim($value);

        if (isset($this->errors[$field])) {
            return $this; // skip if a previous rule already failed
        }

        if ($val !== '' && is_numeric(substr($val, 0, 1))) {
            $this->errors[$field] = $label . ' cannot start with a number.';
            return $this;
        }

        if ($val !== '' && !preg_match("/^[\p{L}][\p{L}\s'\-]*$/u", $val)) {
            $this->errors[$field] = $label . ' can only contain letters, spaces, apostrophes and hyphens.';
        }

        return $this;
    }

    /**
     * Validate email format using a regex.
     */
    public function email(string $field, string $value): self {
        $email = trim($value);

        $pattern = '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/';

        if (!preg_match($pattern, $email)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }

        return $this;
    }

    /**
     * Verify the email address via API.
     *
     * Priority order:
     *  1. AbstractAPI  — if ABSTRACT_EMAIL_API_KEY is set in config.php
     *                    (SMTP-level mailbox check, most accurate, 100 free/month)
     *  2. mailcheck.ai — free, no key required, confirmed reachable.
     *                    Checks: domain MX records, disposable, spam flag.
     *  3. DNS fallback — local MX check, no external dependency.
     *
     * NOTE: Gmail / Outlook / Yahoo block all external SMTP probes to
     * protect user privacy. No API on earth can verify a specific mailbox
     * at those providers without actually sending an email. The checks below
     * will catch: fake domains, disposable services, and known spam domains.
     */
    public function emailDomain(string $field, string $value): self {
        if (isset($this->errors[$field])) {
            return $this; // skip if format already failed
        }

        $email = strtolower(trim($value));

        // Load config once
        if (!defined('ABSTRACT_EMAIL_API_KEY')) {
            $cfg = __DIR__ . '/config.php';
            if (file_exists($cfg)) require_once $cfg;
        }

        $apiKey = defined('ABSTRACT_EMAIL_API_KEY') ? ABSTRACT_EMAIL_API_KEY : '';

        // ── 1. AbstractAPI (SMTP-level, requires free key) ────────────────────
        if (!empty($apiKey)) {
            $res = $this->callAbstractAPI($email, $apiKey);
            if ($res !== null) {
                if (($res['is_disposable_email']['value'] ?? false) === true) {
                    $this->errors[$field] = 'Disposable or temporary email addresses are not allowed.';
                    return $this;
                }
                if (($res['is_mx_found']['value'] ?? true) === false) {
                    $this->errors[$field] = 'This email domain has no mail server. Please use a real email address.';
                    return $this;
                }
                if (($res['deliverability'] ?? 'UNKNOWN') === 'UNDELIVERABLE') {
                    $this->errors[$field] = 'This email address does not exist. Please enter an active, real email address.';
                    return $this;
                }
                // DELIVERABLE or UNKNOWN — allow
                return $this;
            }
            // AbstractAPI failed → fall through to Disify
        }

        // ── 2. Disify (free, no key, no registration required) ───────────────
        // Response: { format, domain, disposable, dns, whitelist, confidence }
        //   dns         → false = no MX records (domain can't receive mail)
        //   disposable  → true  = known throwaway/temp email provider
        $res = $this->callDisify($email);
        if ($res !== null) {
            if (!empty($res['disposable'])) {
                $this->errors[$field] = 'Disposable or temporary email addresses are not allowed.';
                return $this;
            }
            // dns: false means the domain has no mail server (fake/non-existent domain)
            if (isset($res['dns']) && $res['dns'] === false) {
                $this->errors[$field] = 'This email domain cannot receive mail. Please use a real email address (e.g. Gmail, Outlook, Yahoo).';
                return $this;
            }
            // Passed API checks
            return $this;
        }

        // ── 3. DNS fallback (no external dependency) ──────────────────────────
        $domain = explode('@', $email, 2)[1] ?? '';
        if (empty($domain)) {
            $this->errors[$field] = 'Please enter a valid email address.';
            return $this;
        }

        // Disposable blocklist
        $blocked = [
            'mailinator.com','guerrillamail.com','guerrillamail.net','guerrillamail.org',
            'guerrillamail.biz','guerrillamail.de','guerrillamail.info','sharklasers.com',
            'guerrillamailblock.com','grr.la','spam4.me','yopmail.com','yopmail.fr',
            'cool.fr.nf','jetable.fr.nf','nospam.ze.tc','nomail.xl.cx','mega.zik.dj',
            'trashmail.at','trashmail.me','trashmail.io','trashmail.com','trashmail.net',
            'trashmail.org','throwam.com','throwam.net','dispostable.com','tempmail.com',
            'temp-mail.org','tempinbox.com','tempr.email','fakeinbox.com','maildrop.cc',
            'mailnull.com','mailnew.com','throwaway.email','discard.email',
            'emailondeck.com','armyspy.com','cuvox.de','dayrep.com','einrot.com',
            'fleckens.hu','gustr.com','jourrapide.com','rhyta.com','teleworm.us',
            'lazyinbox.com','getairmail.com','filzmail.com','binkmail.com',
            'bobmail.info','clrmail.com','drdrb.net','fakemail.net','filzmail.de',
            'meltmail.com','my10minutemail.com','pookmail.com','proxymail.eu',
            'spam.la','spamavert.com','spambob.com','spambob.net','spambob.org',
            'spamday.com','spamex.com','spamhole.com','spaml.de','spammotel.com',
            'spamspot.com','tfwno.gf','tilien.com','tmailinator.com','trash2009.com',
            'trashdevil.com','trashdevil.de','trashemail.de','trbvm.com','twinmail.de',
        ];

        if (in_array($domain, $blocked, true)) {
            $this->errors[$field] = 'Disposable or temporary email addresses are not allowed.';
            return $this;
        }

        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $this->errors[$field] = 'This email domain does not exist. Please use a real email address (e.g. Gmail, Outlook, Yahoo).';
        }

        return $this;
    }

    /**
     * Disify — free email domain verifier, no API key required.
     * GET https://www.disify.com/api/email/{email}
     * Returns JSON: { format, domain, disposable, dns, whitelist, confidence }
     *
     *  dns         → false means no MX records  (fake/dead domain)
     *  disposable  → true  means throwaway inbox
     *  whitelist   → true  means known trusted provider
     *
     * @return array|null  Parsed response, or null on network failure.
     */
    private function callDisify(string $email): ?array {
        if (!function_exists('curl_init')) return null;

        $url = 'https://www.disify.com/api/email/' . urlencode($email);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,   // follow HTTPS redirect
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,  // Disify SSL cert is valid but XAMPP may lack CA bundle
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        if ($curlErr !== 0 || $httpCode !== 200 || !$response) return null;

        $data = json_decode($response, true);
        // Disify returns { "format": bool, "dns": bool, "disposable": bool, ... }
        return (is_array($data) && array_key_exists('dns', $data)) ? $data : null;
    }

    /**
     * AbstractAPI Email Validation — requires a free API key.
     * Sign up at https://app.abstractapi.com/api/email-validation (100 free/month)
     * GET https://emailvalidation.abstractapi.com/v1/?api_key=KEY&email=EMAIL
     *
     * @return array|null  Response data, or null on failure.
     */
    private function callAbstractAPI(string $email, string $apiKey): ?array {
        if (!function_exists('curl_init')) return null;

        $baseUrl = defined('ABSTRACT_EMAIL_API_URL')
            ? ABSTRACT_EMAIL_API_URL
            : 'https://emailvalidation.abstractapi.com/v1/';
        $timeout = defined('ABSTRACT_EMAIL_API_TIMEOUT') ? (int)ABSTRACT_EMAIL_API_TIMEOUT : 5;

        $url = $baseUrl . '?' . http_build_query(['api_key' => $apiKey, 'email' => $email]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'HealthFlow/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        if ($curlErr !== 0 || $httpCode !== 200 || !$response) return null;

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Validate minimum length.
     */
    public function minLength(string $field, string $value, int $min, string $label = ''): self {
        if (mb_strlen(trim($value)) < $min) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be at least {$min} characters.";
        }
        return $this;
    }

    /**
     * Validate maximum length.
     */
    public function maxLength(string $field, string $value, int $max, string $label = ''): self {
        if (mb_strlen(trim($value)) > $max) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be at most {$max} characters.";
        }
        return $this;
    }

    /**
     * Validate password strength:
     * - min 8 chars, at least 1 uppercase, 1 lowercase, 1 digit
     */
    public function password(string $field, string $value): self {
        $val = trim($value);
        if (mb_strlen($val) < 8) {
            $this->errors[$field] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $val)) {
            $this->errors[$field] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $val)) {
            $this->errors[$field] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $val)) {
            $this->errors[$field] = 'Password must contain at least one digit.';
        }
        return $this;
    }

    /**
     * Validate a numeric value within range.
     */
    public function numericRange(string $field, $value, float $min, float $max, string $label = ''): self {
        $val = trim($value);
        if ($val === '') return $this; // skip if empty (use required() to enforce)
        if (!is_numeric($val)) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' must be a number.';
        } elseif ((float)$val < $min || (float)$val > $max) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be between {$min} and {$max}.";
        }
        return $this;
    }

    /**
     * Validate value is one of allowed options.
     */
    public function inList(string $field, string $value, array $allowed, string $label = ''): self {
        if (!in_array(trim($value), $allowed, true)) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' has an invalid value.';
        }
        return $this;
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Get all errors.
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Get first error message.
     */
    public function firstError(): string {
        return reset($this->errors) ?: '';
    }
}
