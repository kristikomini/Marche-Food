<?php

namespace App\Services;

/**
 * Self-contained TOTP (RFC 6238) / HOTP (RFC 4226) implementation — no external
 * dependencies. Used for two-factor authentication. Validated against the
 * RFC 6238 test vectors in the test suite.
 */
class TotpService
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a random Base32 secret (default 160 bits, like Google Authenticator). */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** The otpauth:// URI an authenticator app scans. */
    public function otpauthUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer)
        );
    }

    /** TOTP code for a given moment. */
    public function codeAt(string $base32Secret, int $timestamp, int $digits = 6, int $period = 30): string
    {
        $counter = intdiv($timestamp, $period);
        return $this->hotp($this->base32Decode($base32Secret), $counter, $digits);
    }

    /**
     * Verify a submitted code, tolerating clock drift of ±$window periods.
     */
    public function verify(string $base32Secret, string $code, int $window = 1, ?int $timestamp = null, int $digits = 6, int $period = 30): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{' . $digits . '}$/', (string) $code)) {
            return false;
        }
        $timestamp ??= time();

        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $this->codeAt($base32Secret, $timestamp + ($i * $period), $digits, $period);
            if (hash_equals($candidate, (string) $code)) {
                return true;
            }
        }
        return false;
    }

    /** HOTP for a binary key + counter. */
    public function hotp(string $binaryKey, int $counter, int $digits = 6): string
    {
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian
        $hash = hash_hmac('sha1', $binCounter, $binaryKey, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $modulo = 10 ** $digits;
        return str_pad((string) ($value % $modulo), $digits, '0', STR_PAD_LEFT);
    }

    public function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        if ($b32 === '') {
            return '';
        }
        $binary = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
