<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmedValue);

        if ($digits === null || $digits === '') {
            return null;
        }

        return '+'.$digits;
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        return preg_match('/^\+\d{8,15}$/', $value) === 1;
    }

    public static function toChatId(string $value): string
    {
        return ltrim(self::normalize($value) ?? '', '+').'@c.us';
    }

    public static function fromChatId(?string $chatId): ?string
    {
        if ($chatId === null) {
            return null;
        }

        if (! preg_match('/^(\d+)@c\.us$/', $chatId, $matches)) {
            return null;
        }

        return '+'.$matches[1];
    }
}
