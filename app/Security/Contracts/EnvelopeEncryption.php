<?php

namespace App\Security\Contracts;

interface EnvelopeEncryption
{
    /**
     * @return array{version:string,wrapped_dek:string,wrap_nonce:string,ciphertext:string,cipher_nonce:string,aad:string|null}
     */
    public function encrypt(string $plaintext, ?string $aad = null): array;

    /**
     * @param array{version:string,wrapped_dek:string,wrap_nonce:string,ciphertext:string,cipher_nonce:string,aad:string|null} $envelope
     */
    public function decrypt(array $envelope, ?string $aad = null): string;
}
