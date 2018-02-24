<?php
namespace ePHP\Hash;

class Encrypt
{
    /**
     * encrypt
     *
     * @param string $str
     * @param string $secret default 'ePHP'
     * @return string
     */
    public static function encryptG($str, $secret = 'ePHP')
    {
        if (!$str) {
            return false;
        }

        $key = md5($secret . "30f7384ac1");

        $ciphertext_raw = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return \ePHP\Misc\Func::safe_b64encode($ciphertext_raw);
    }

    /**
     * decrypt
     *
     * @param string $str
     * @param string $secret default 'ePHP'
     * @return string
     */
    public static function decryptG($str, $secret = 'ePHP')
    {
        if (!$str) {
            return false;
        }

        $key = md5($secret . "30f7384ac1");

        $ciphertext_raw = \ePHP\Misc\Func::safe_b64decode($str);
        return openssl_decrypt($ciphertext_raw, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
    }

    /**
     * Others encode or decode
     *
     * <code>
     * echo $str = edcode('1371817454', 'ENCODE','1');
     * echo edcode('XbfSC2GOpSTtwHwOIDW7Fg', 'DECODE','2000558');
     * </code>
     *
     * @param string $string 密文
     * @param string $operation options DECODE | DECODE
     * @param string $key default 'ePHP'
     * @return string
     */
    public static function edcode($string, $operation, $key = 'ePHP')
    {
        // ENCODE
        $key_length    = strlen($key);
        $string        = $operation == 'DECODE' ? \ePHP\Misc\Func::safe_b64decode($string) : substr(md5($string . $key), 0, 8) . $string;
        $string_length = strlen($string);
        $rndkey        = $box = array();
        $result        = '';

        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($key[$i % $key_length]);
            $box[$i]    = $i;
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        // DECODE
        if ($operation == 'DECODE') {
            if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
                return substr($result, 8);
            } else {
                return '';
            }
        } else {
            return \ePHP\Misc\Func::safe_b64encode($result);
        }
    }

    /**
     * hmac sha1，from oauth 1.0 protocol
     *
     * @param string $base_string
     * @param string $key
     * @return string
     */
    public static function hmac_sha1($base_string, $key = 'ePHP')
    {
        return base64_encode(hash_hmac('sha1', $base_string, $key, true));
    }
}
