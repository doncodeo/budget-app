<?php
declare(strict_types=1);

namespace App;

class Validator
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function required(string $field, string $label): self
    {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        $val = trim((string)($this->data[$field] ?? ''));
        if (strlen($val) < $min) {
            $this->errors[$field] = "$label must be at least $min characters.";
        }
        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!is_numeric($this->data[$field])) {
                $this->errors[$field] = "$label must be a valid number.";
            }
        }
        return $this;
    }

    public function min(string $field, float $minVal, string $label): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            if ((float)$this->data[$field] < $minVal) {
                $this->errors[$field] = "$label must be at least $minVal.";
            }
        }
        return $this;
    }

    public function rangeInt(string $field, int $min, int $max, string $label): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field])) {
            $val = (int)$this->data[$field];
            if ($val < $min || $val > $max) {
                $this->errors[$field] = "$label must be between $min and $max.";
            }
        } else {
            $this->errors[$field] = "$label must be a valid integer.";
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $label): self
    {
        if (isset($this->data[$field])) {
            if (!in_array($this->data[$field], $allowed, true)) {
                $this->errors[$field] = "$label is invalid.";
            }
        }
        return $this;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?string
    {
        return reset($this->errors) ?: null;
    }
}
