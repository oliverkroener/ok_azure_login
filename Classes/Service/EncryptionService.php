<?php

declare(strict_types=1);

namespace OliverKroener\OkAzureLogin\Service;

class EncryptionService
{
    public function encrypt(string $plaintext): string
    {
        $key = $this->deriveKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $key = $this->deriveKey();
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        return $decrypted !== false ? $decrypted : '';
    }

    private function deriveKey(): string
    {
        $encryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];

        return sodium_crypto_generichash($encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
