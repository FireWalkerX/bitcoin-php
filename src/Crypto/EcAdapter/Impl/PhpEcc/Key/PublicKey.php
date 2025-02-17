<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Key;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Serializer\Key\PublicKeySerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\Key;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\SignatureInterface;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Buffertools\Buffer;
use Mdanter\Ecc\Primitives\PointInterface;

class PublicKey extends Key implements PublicKeyInterface
{
    /**
     * @var EcAdapterInterface
     */
    private $ecAdapter;

    /**
     * @var PointInterface
     */
    private $point;

    /**
     * @var bool
     */
    private $compressed;

    /**
     * @param EcAdapter $ecAdapter
     * @param PointInterface $point
     * @param bool $compressed
     */
    public function __construct(
        EcAdapter $ecAdapter,
        PointInterface $point,
        $compressed = false
    ) {
        if (false === is_bool($compressed)) {
            throw new \InvalidArgumentException('PublicKey: Compressed must be a boolean');
        }
        $this->ecAdapter = $ecAdapter;
        $this->point = $point;
        $this->compressed = $compressed;
    }

    /**
     * @return PointInterface
     */
    public function getPoint()
    {
        return $this->point;
    }

    /**
     * @return Buffer
     */
    public function getPubKeyHash()
    {
        return Hash::sha256ripe160($this->getBuffer());
    }

    /**
     * @param Buffer $msg32
     * @param SignatureInterface $signature
     * @return bool
     */
    public function verify(Buffer $msg32, SignatureInterface $signature)
    {
        return $this->ecAdapter->verify($msg32, $this, $signature);
    }

    /**
     * @param int|string $tweak
     * @return PublicKeyInterface
     */
    public function tweakAdd($tweak)
    {
        $adapter = $this->ecAdapter;
        $G = $adapter->getGenerator();
        $point = $this->point->add($G->mul($tweak));
        return $adapter->getPublicKey($point, $this->compressed);
    }

    /**
     * @param int|string $tweak
     * @return PublicKeyInterface
     */
    public function tweakMul($tweak)
    {
        $point = $this->point->mul($tweak);
        return $this->ecAdapter->getPublicKey($point, $this->compressed);
    }

    /**
     * @param Buffer $publicKey
     * @return bool
     */
    public static function isCompressedOrUncompressed(Buffer $publicKey)
    {
        $vchPubKey = $publicKey->getBinary();
        if ($publicKey->getSize() < 33) {
            return false;
        }

        if (ord($vchPubKey[0]) == 0x04) {
            if ($publicKey->getSize() != 65) {
                // Invalid length for uncompressed key
                return false;
            }
        } elseif (in_array($vchPubKey[0], array(
            hex2bin(self::KEY_COMPRESSED_EVEN),
            hex2bin(self::KEY_COMPRESSED_ODD)))) {
            if ($publicKey->getSize() != 33) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Sets a public key to be compressed
     *
     * @param $compressed
     * @return $this
     * @throws \Exception
     */
    public function setCompressed($compressed)
    {
        if (!is_bool($compressed)) {
            throw new \Exception('Compressed flag must be a boolean');
        }

        $this->compressed = $compressed;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCompressed()
    {
        return $this->compressed;
    }

    /**
     * @inheritdoc
     */
    public function isPrivate()
    {
        return false;
    }

    /**
     * @return Buffer
     */
    public function getBuffer()
    {
        return (new PublicKeySerializer($this->ecAdapter))->serialize($this);
    }
}
