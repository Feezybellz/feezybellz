<?php

namespace Framework\Core\Validation;

class Validator
{
    protected $data;
    protected $errors = [];
    protected $validatedData = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Static helper to instantiate and run the validator.
     */
    public static function make(array $data, array $rules): self
    {
        $validator = new self($data);
        $validator->validate($data, $rules);
        return $validator;
    }

    /**
     * Process all rules against the provided data.
     */
    public function validate($data, array $rules = null): void
    {
        if (func_num_args() == 2) {
            $this->data = func_get_arg(0);
            $rules = func_get_arg(1);
        }
        foreach ($rules as $field => $ruleString) {
            $fieldRules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $parameters = [];
                
                // Parse rules with parameters like "min:8" or "in:admin,user"
                if (is_string($rule) && strpos($rule, ':') !== false) {
                    // Split only on the first colon (in case regex or date formats have colons)
                    [$rule, $paramString] = explode(':', $rule, 2);
                    $parameters = explode(',', $paramString);
                }

                $methodName = 'validate' . ucfirst(strtolower($rule));

                if (method_exists($this, $methodName)) {
                    // If a rule fails, stop processing further rules for this specific field
                    if (!$this->$methodName($field, $value, $parameters)) {
                        break; 
                    }
                } else {
                    throw new \Exception("Validation rule '{$rule}' does not exist.");
                }
            }

            // If the field passed all its rules, sanitize and add it to validatedData
            if (!isset($this->errors[$field]) && array_key_exists($field, $this->data)) {
                $this->validatedData[$field] = $this->sanitize($value);
            }
        }
    }

    public function passes(): bool { return empty($this->errors); }
    public function fails(): bool { return !empty($this->errors); }
    /**
     * Get the array of all validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // --- ADD THESE NEW METHODS ---

    /**
     * Check if a specific field has an error.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Get the first error message for a specific field.
     * (Very useful for displaying a single error string under an HTML input)
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all error messages for a specific field.
     */
    public function getErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
    
    // ------------------------------
    public function validated(): array { return $this->validatedData; }

    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Basic sanitization for validated data
     */
    protected function sanitize($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
        }
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        return $value;
    }

    // ==========================================
    // 🛡️ INPUT VALIDATION RULES
    // ==========================================

    protected function validateRequired(string $field, $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
            $this->addError($field, "The {$field} field is required.");
            return false;
        }
        return true;
    }

    protected function validateString(string $field, $value): bool
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, "The {$field} must be a string.");
            return false;
        }
        return true;
    }

    protected function validateNumeric(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, "The {$field} must be a number.");
            return false;
        }
        return true;
    }

    protected function validateInteger(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, "The {$field} must be an integer.");
            return false;
        }
        return true;
    }

    protected function validateBoolean(string $field, $value): bool
    {
        $acceptable = [true, false, 1, 0, '1', '0', 'true', 'false'];
        if ($value !== null && !in_array($value, $acceptable, true)) {
            $this->addError($field, "The {$field} field must be true or false.");
            return false;
        }
        return true;
    }

    protected function validateArray(string $field, $value): bool
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, "The {$field} must be an array.");
            return false;
        }
        return true;
    }

    protected function validateEmail(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
            return false;
        }
        return true;
    }

    protected function validateUrl(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, "The {$field} must be a valid URL.");
            return false;
        }
        return true;
    }

    protected function validateDate(string $field, $value): bool
    {
        if ($value !== null && $value !== '') {
            if ((!is_string($value) && !is_numeric($value)) || strtotime((string)$value) === false) {
                $this->addError($field, "The {$field} is not a valid date.");
                return false;
            }
        }
        return true;
    }

    protected function validateAlpha(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, "The {$field} may only contain letters.");
            return false;
        }
        return true;
    }

    protected function validateAlphanum(string $field, $value): bool
    {
        if ($value !== null && $value !== '' && !ctype_alnum(str_replace(' ', '', $value))) {
            $this->addError($field, "The {$field} may only contain letters and numbers.");
            return false;
        }
        return true;
    }

    protected function validateMin(string $field, $value, array $parameters): bool
    {
        if ($value === null || $value === '') return true;
        
        $min = (float) ($parameters[0] ?? 0);
        
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, "The {$field} must be at least {$min} characters.");
            return false;
        }
        if (is_numeric($value) && $value < $min) {
            $this->addError($field, "The {$field} must be at least {$min}.");
            return false;
        }
        if (is_array($value) && count($value) < $min) {
            $this->addError($field, "The {$field} must have at least {$min} items.");
            return false;
        }
        return true;
    }

    protected function validateMax(string $field, $value, array $parameters): bool
    {
        if ($value === null || $value === '') return true;

        $max = (float) ($parameters[0] ?? 0);
        
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, "The {$field} may not be greater than {$max} characters.");
            return false;
        }
        if (is_numeric($value) && $value > $max) {
            $this->addError($field, "The {$field} may not be greater than {$max}.");
            return false;
        }
        if (is_array($value) && count($value) > $max) {
            $this->addError($field, "The {$field} may not have more than {$max} items.");
            return false;
        }
        return true;
    }

    protected function validateLength(string $field, $value, array $parameters): bool
    {
        if ($value === null || $value === '') return true;

        $length = (int) ($parameters[0] ?? 0);
        
        if (is_string($value) && mb_strlen($value) !== $length) {
            $this->addError($field, "The {$field} must be exactly {$length} characters.");
            return false;
        }
        if (is_array($value) && count($value) !== $length) {
            $this->addError($field, "The {$field} must have exactly {$length} items.");
            return false;
        }
        return true;
    }

    protected function validateBetween(string $field, $value, array $parameters): bool
    {
        if ($value === null || $value === '') return true;

        $min = (float) ($parameters[0] ?? 0);
        $max = (float) ($parameters[1] ?? 0);
        $size = is_string($value) ? mb_strlen($value) : (is_array($value) ? count($value) : (float)$value);

        if ($size < $min || $size > $max) {
            $this->addError($field, "The {$field} must be between {$min} and {$max}.");
            return false;
        }
        return true;
    }

    /**
     * Checks if the value is in a list of allowed values.
     * Usage: in:admin,editor,user
     */
    protected function validateIn(string $field, $value, array $parameters): bool
    {
        if ($value !== null && $value !== '' && !in_array((string)$value, $parameters, true)) {
            $this->addError($field, "The selected {$field} is invalid. Allowed: " . implode(', ', $parameters));
            return false;
        }
        return true;
    }

    /**
     * Checks if a value is a strong password.
     * Requires: Minimum 8 characters, at least one letter, one number, and one special character.
     * Usage: password => 'required|password'
     */
    protected function validatePassword(string $field, $value): bool
    {
        // Skip if empty (let the 'required' rule catch empty fields)
        if ($value === null || $value === '') return true;

        // Regex Breakdown:
        // (?=.*[a-zA-Z]) : Contains at least one letter (upper or lowercase)
        // (?=.*\d)       : Contains at least one digit
        // (?=.*[\W_])    : Contains at least one special character (non-word char or underscore)
        // .{8,}          : Is at least 8 characters long
        $pattern = '/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

        if (!preg_match($pattern, (string)$value)) {
            $this->addError(
                $field, 
                "The {$field} must be at least 8 characters long and contain at least one letter, one number, and one special character."
            );
            return false;
        }
        
        return true;
    }

    protected function validateConfirmed(string $field, $value): bool
    {
        // $confirmationField = $field . '_confirmation';
        // $confirmationField2 = $field. '_confirmed';
        $patchArray = ['confirmation', 'confirmed'];
        $found = array_filter($patchArray, function($patch) use ($field) { return isset($this->data[$field . '_' . $patch]); });
        // re-index 
        $found = array_values($found);
        if (empty($found)) {
            $this->addError($field, "The {$field} confirmation field is missing.");
            return false;
        }
        $confirmationField = $field . '_' . $found[0];

        $confirmationValue = $this->data[$confirmationField] ?? null;

        if ($value !== $confirmationValue) {
            $this->addError($field, "The {$field} confirmation does not match.");
            return false;
        }
        return true;
    }

    protected function validateNullable(string $field, $value): bool
    {
        if ($value === null || $value === '') return true;
        return true;
    }
}
