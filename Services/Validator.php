<?php

namespace CandlewaxGames\Services;

class Validator
{
    public function validate(array &$data, array $rules, array &$errors = []): bool
    {
        // Filter out any values from the $data array that do not have a corresponding key in the $rules array.
        $data = array_intersect_key($data, $rules);

        // Loop through the rules and check them against the data.
        foreach ($rules as $key => $ruleSet) {
            foreach ($ruleSet as $rule => $error) {
                if (!array_key_exists($key, $data)) {
                    $data[$key] = '';
                }

                // Apply the rule, and if an error message is returned, add it to the array.
                $valid = $this->applyRule($data[$key], $rule);
                if (!$valid) {
                    $errors[$key][] = $error;
                }
            }
        }

        return count($errors) == 0;
    }

    public function validateImage(string $filePath, int $maxSize, array $allowedTypes): bool
    {
        // Make sure the image isn't too large.
        if (filesize($filePath) > $maxSize) {
            return false;
        }

        // Check if the given file is of an allowed type.
        $extension = $this->getImageType($filePath);

        return $extension !== '' && in_array($extension, $allowedTypes);
    }

    public function getImageType(string $imagePath): string
    {
        // Construct an array of valid mime types.
        $types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];

        // Check if the given file's mime type is an image.
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $imagePath);
        finfo_close($fileInfo);

        return $types[$mimeType] ?? '';
    }

    private function applyRule(string $value, string $rule): bool
    {
        [$ruleName, $param] = explode(':', $rule . ':');

        return match ($ruleName) {
            'required' => $this->required($value),
            'email' => $this->email($value),
            "identical" => $this->identical($value, $param),
            "min" => $this->min($value, $param),
            "max" => $this->max($value, $param),
            "alphaNum" => $this->alphaNum($value),
            default => true
        };
    }

    public function required(string $value): bool
    {
        return !empty($value);
    }

    public function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function identical(string $value, string $copy): bool
    {
        return $value === $copy;
    }

    public function min(string $value, int $length): bool
    {
        return strlen($value) >= $length;
    }

    public function max(string $value, int $length): bool
    {
        return strlen($value) <= $length;
    }

    public function alphaNum(string $value): bool
    {
        return ctype_alnum($value);
    }
}
