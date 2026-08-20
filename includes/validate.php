<?php
// Chained input validation with field error messages.

class Validator {
    private array $errors = [];

    // Require non-empty value.
    public function required(string $field, string $value, string $label = ''): self {
        if (trim($value) === '') {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' is required.';
        }
        return $this;
    }

    // Validate person name (letters, spaces, apostrophes, hyphens only).
    public function name(string $field, string $value, string $label = 'Name'): self {
        $val = trim($value);

        if (isset($this->errors[$field])) {
            return $this;
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

    // Basic email format check.
    public function email(string $field, string $value): self {
        $email = trim($value);

        $pattern = '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/';

        if (!preg_match($pattern, $email)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }

        return $this;
    }

    // Validate domain deliverability (API → Disify → DNS).
    public function emailDomain(string $field, string $value): self {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $email = strtolower(trim($value));

        if (!defined('ABSTRACT_EMAIL_API_KEY')) {
            $cfg = __DIR__ . '/config.php';
            if (file_exists($cfg)) require_once $cfg;
        }

        $apiKey = defined('ABSTRACT_EMAIL_API_KEY') ? ABSTRACT_EMAIL_API_KEY : '';

        if (!empty($apiKey)) {
            $res = $this->callAbstractAPI($email, $apiKey);
            if ($res !== null) {
                $deliverability = $res['email_deliverability'] ?? [];
                $status         = $deliverability['status'] ?? 'unknown';
                $isFormatValid  = $deliverability['is_format_valid'] ?? true;

                if (!$isFormatValid || $status === 'undeliverable') {
                    $this->errors[$field] = 'This email address does not exist. Please enter an active, real email address.';
                    return $this;
                }
                return $this;
            }
        }

        $res = $this->callDisify($email);
        if ($res !== null) {
            if (!empty($res['disposable'])) {
                $this->errors[$field] = 'Disposable or temporary email addresses are not allowed.';
                return $this;
            }
            if (isset($res['dns']) && $res['dns'] === false) {
                $this->errors[$field] = 'This email domain cannot receive mail. Please use a real email address (e.g. Gmail, Outlook, Yahoo).';
                return $this;
            }
            return $this;
        }

        $domain = explode('@', $email, 2)[1] ?? '';
        if (empty($domain)) {
            $this->errors[$field] = 'Please enter a valid email address.';
            return $this;
        }

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

    // Check disposable email via Disify.
    private function callDisify(string $email): ?array {
        if (!function_exists('curl_init')) return null;

        $url = 'https://www.disify.com/api/email/' . urlencode($email);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        if ($curlErr !== 0 || $httpCode !== 200 || !$response) return null;

        $data = json_decode($response, true);
        return (is_array($data) && array_key_exists('dns', $data)) ? $data : null;
    }

    // Check deliverability via Abstract Email Reputation API.
    private function callAbstractAPI(string $email, string $apiKey): ?array {
        if (!function_exists('curl_init')) return null;

        $baseUrl = defined('ABSTRACT_EMAIL_API_URL')
            ? ABSTRACT_EMAIL_API_URL
            : 'https://emailreputation.abstractapi.com/v1/';
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

    // Enforce minimum string length.
    public function minLength(string $field, string $value, int $min, string $label = ''): self {
        if (mb_strlen(trim($value)) < $min) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be at least {$min} characters.";
        }
        return $this;
    }

    // Enforce maximum string length.
    public function maxLength(string $field, string $value, int $max, string $label = ''): self {
        if (mb_strlen(trim($value)) > $max) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be at most {$max} characters.";
        }
        return $this;
    }

    // Password rules: min 8 chars, uppercase, lowercase, digit.
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

    // Check numeric value is within a min/max range.
    public function numericRange(string $field, $value, float $min, float $max, string $label = ''): self {
        $val = trim($value);
        if ($val === '') return $this;
        if (!is_numeric($val)) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' must be a number.';
        } elseif ((float)$val < $min || (float)$val > $max) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . " must be between {$min} and {$max}.";
        }
        return $this;
    }

    // Check value is one of the allowed options.
    public function inList(string $field, string $value, array $allowed, string $label = ''): self {
        if (!in_array(trim($value), $allowed, true)) {
            $this->errors[$field] = ($label ?: ucfirst($field)) . ' has an invalid value.';
        }
        return $this;
    }

    // True when no errors were recorded.
    public function passes(): bool {
        return empty($this->errors);
    }

    // All field errors.
    public function errors(): array {
        return $this->errors;
    }

    // First recorded error message.
    public function firstError(): string {
        return reset($this->errors) ?: '';
    }
}
