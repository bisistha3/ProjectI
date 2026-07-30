<?php
/**
 * HydroFlow — Input Validation Helper
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
     * Validate email format.
     */
    public function email(string $field, string $value): self {
        if (!filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
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
