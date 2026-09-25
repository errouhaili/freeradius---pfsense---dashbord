<?php
/**
 * تطبيق بسيط لـ TOTP (RFC 6238) متوافق مع Google Authenticator / Authy / Microsoft Authenticator،
 * بدون أي اعتماديات خارجية (لا Composer، لا مكتبات).
 */
class TOTP
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** توليد سر عشوائي (160 بت) بترميز Base32 */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** بناء رابط otpauth:// لعرضه كـ QR Code (Google Authenticator وما شابه) */
    public static function provisioningUri(string $secret, string $accountName, string $issuer = 'RADIUS Dashboard'): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** التحقق من رمز أدخله المستخدم، بهامش تسامح لفارق الساعة (±1 فترة = ±30 ثانية) */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', (string)$code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($secret, $counter + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    private static function hotp(string $base32Secret, int $counter): string
    {
        $key = self::base32Decode($base32Secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8 octets big-endian
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated =
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);
        $codeInt = $truncated % (10 ** self::DIGITS);
        return str_pad((string)$codeInt, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= $alphabet[bindec($chunk)];
        }
        return $output;
    }

    private static function base32Decode(string $b32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr(bindec($byte));
            }
        }
        return $output;
    }
}
