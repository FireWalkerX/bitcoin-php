<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Serializer\Signature;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\DerSignatureSerializerInterface;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Buffertools\Exceptions\ParserOutOfRange;
use BitWasp\Buffertools\Parser;
use BitWasp\Buffertools\Buffer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Signature\Signature;
use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\SignatureInterface;
use BitWasp\Buffertools\Template;
use BitWasp\Buffertools\TemplateFactory;

class DerSignatureSerializer implements DerSignatureSerializerInterface
{
    /**
     * @var EcAdapter
     */
    private $ecAdapter;

    /**
     * @param EcAdapter $adapter
     */
    public function __construct(EcAdapter $adapter)
    {
        $this->ecAdapter = $adapter;
    }

    /**
     * @return EcAdapterInterface
     */
    public function getEcAdapter()
    {
        return $this->ecAdapter;
    }

    /**
     * @return Template
     */
    private function getInnerTemplate()
    {
        return (new TemplateFactory())
            ->uint8()
            ->varstring()
            ->uint8()
            ->varstring()
            ->getTemplate();
    }

    /**
     * @return Template
     */
    private function getOuterTemplate()
    {
        return (new TemplateFactory())
            ->uint8()
            ->varstring()
            ->getTemplate();
    }

    /**
     * @param SignatureInterface $signature
     * @return Buffer
     */
    public function serialize(SignatureInterface $signature)
    {
        $math = $this->ecAdapter->getMath();

        // Ensure that the R and S hex's are of even length
        $rBin = pack('H*', $math->decHex($signature->getR()));
        $sBin = pack('H*', $math->decHex($signature->getS()));

        // Pad R and S if their highest bit is flipped, ie,
        // they are negative.
        $rt = $rBin[0] & pack('H*', '80');
        if (ord($rt) == 128) {
            $rBin = pack('H*', '00') . $rBin;
        }

        $st = $sBin[0] & pack('H*', '80');
        if (ord($st) == 128) {
            $sBin = pack('H*', '00') . $sBin;
        }

        return $this->getOuterTemplate()->write([
            0x30,
            $this->getInnerTemplate()->write([
                0x02,
                new Buffer($rBin),
                0x02,
                new Buffer($sBin)
            ])
        ]);
    }

    /**
     * @param Parser $parser
     * @return Signature
     * @throws ParserOutOfRange
     */
    public function fromParser(Parser & $parser)
    {
        try {
            list (, $inner) = $this->getOuterTemplate()->parse($parser);
            list (, $r, , $s) = $this->getInnerTemplate()->parse(new Parser($inner));
            /** @var Buffer $r */
            /** @var Buffer $s */

            return new Signature(
                $this->ecAdapter,
                $r->getInt(),
                $s->getInt()
            );
        } catch (ParserOutOfRange $e) {
            throw new ParserOutOfRange('Failed to extract full signature from parser');
        }
    }

    /**
     * @param $string
     * @return Signature
     * @throws ParserOutOfRange
     */
    public function parse($string)
    {
        $parser = new Parser($string);
        $signature = $this->fromParser($parser);
        return $signature;
    }
}
