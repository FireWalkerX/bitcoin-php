<?php

namespace BitWasp\Bitcoin\Signature;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcSerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\DerSignatureSerializerInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\SignatureInterface;
use BitWasp\Bitcoin\Exceptions\SignatureNotCanonical;
use BitWasp\Bitcoin\Serializable;
use BitWasp\Bitcoin\Serializer\Signature\TransactionSignatureSerializer;
use BitWasp\Buffertools\Buffer;

class TransactionSignature extends Serializable implements TransactionSignatureInterface
{
    /**
     * @var EcAdapterInterface
     */
    private $ecAdapter;

    /**
     * @var SignatureInterface
     */
    private $sig;

    /**
     * @var int|string
     */
    private $hashType;

    /**
     * @param SignatureInterface $signature
     * @param $hashType
     */
    public function __construct(EcAdapterInterface $ecAdapter, SignatureInterface $signature, $hashType)
    {
        $this->ecAdapter = $ecAdapter;
        $this->sig = $signature;
        $this->hashType = $hashType;
    }

    /**
     * @return SignatureInterface
     */
    public function getSignature()
    {
        return $this->sig;
    }

    /**
     * @return int|string
     */
    public function getHashType()
    {
        return $this->hashType;
    }

    /**
     * @param \BitWasp\Buffertools\Buffer $sig
     * @return bool
     * @throws SignatureNotCanonical
     */
    public static function isDERSignature(Buffer $sig)
    {
        $checkVal = function ($fieldName, $start, $length, $binaryString) {
            if ($length == 0) {
                throw new SignatureNotCanonical('Signature ' . $fieldName . ' length is zero');
            }
            $typePrefix = ord(substr($binaryString, $start - 2, 1));
            if ($typePrefix !== 0x02) {
                throw new SignatureNotCanonical('Signature ' . $fieldName . ' value type mismatch');
            }
            $val = substr($binaryString, $start, $length);
            $vAnd = $val[0] & pack("H*", '80');
            if (ord($vAnd) === 128) {
                throw new SignatureNotCanonical('Signature ' . $fieldName . ' value is negative');
            }
            if ($length > 1 && ord($val[0]) == 0x00 && !ord(($val[1] & pack('H*', '80')))) {
                throw new SignatureNotCanonical('Signature ' . $fieldName . ' value excessively padded');
            }
        };

        $bin = $sig->getBinary();
        $size = $sig->getSize();
        if ($size < 9) {
            throw new SignatureNotCanonical('Signature too short');
        }

        if ($size > 73) {
            throw new SignatureNotCanonical('Signature too long');
        }

        if (ord($bin[0]) !== 0x30) {
            throw new SignatureNotCanonical('Signature has wrong type');
        }

        if (ord($bin[1]) !== $size - 3) {
            throw new SignatureNotCanonical('Signature has wrong length marker');
        }

        $lenR = ord($bin[3]);
        $startR = 4;
        if (5 + $lenR >= $size) {
            throw new SignatureNotCanonical('Signature S length misplaced');
        }

        $lenS = ord($bin[5 + $lenR]);
        $startS = 4 + $lenR + 2;
        if (($lenR + $lenS + 7) !== $size) {
            throw new SignatureNotCanonical('Signature R+S length mismatch');
        }

        $checkVal('R', $startR, $lenR, $bin);
        $checkVal('S', $startS, $lenS, $bin);

        return true;

    }

    /**
     * @return \BitWasp\Buffertools\Buffer
     */
    public function getBuffer()
    {
        $txSigSerializer = new TransactionSignatureSerializer(
            EcSerializer::getSerializer(
                $this->ecAdapter,
                'BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\DerSignatureSerializerInterface'
            )
        );

        return $txSigSerializer->serialize($this);
    }
}
