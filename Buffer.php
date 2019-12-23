<?php
///// Cherry picked from https://github.com/Bit-Wasp/buffertools-php


class Buffer 
{
    protected $size;
    protected $buffer; //string

    public function __construct(string $byteString = '', int $byteSize = null)
    {
        if ($byteSize !== null) {
            // Check the integer doesn't overflow its supposed size
            if (strlen($byteString) > $byteSize) {
                throw new \Exception('Byte string exceeds maximum size');
            }
        } else {
            $byteSize = strlen($byteString);
        }
        $this->size   = $byteSize;
        $this->buffer = $byteString;
    }

    public static function hex(string $hexString = '', int $byteSize = null)
    {
        if (strlen($hexString) > 0 && !ctype_xdigit($hexString)) {
            throw new \InvalidArgumentException('Buffer::hex: non-hex character passed');
        }
        $binary = pack("H*", $hexString);
        return new self($binary, $byteSize);
    }
    
    public static function int($integer, $byteSize = null)
    {
        $hex_dec = dechex($integer);
        return Buffer::hex($hex_dec, $byteSize);
    }
    
    
    

    public function getSize()
    {
        return $this->size;
    }
   
   
    public function getBinary()
    {
        if ($this->size !== null) {
            if (strlen($this->buffer) < $this->size) {
                return str_pad($this->buffer, $this->size, chr(0), STR_PAD_LEFT);
            } elseif (strlen($this->buffer) > $this->size) {
                return substr($this->buffer, 0, $this->size);
            }
        }
        return $this->buffer;
    }
    
    public function getHex()
    {
        return unpack("H*", $this->getBinary())[1];
    }
   
   
    public function equals(Buffer $other)
    {
        return ($other->getSize() === $this->getSize()
             && $other->getBinary() === $this->getBinary());
    }
}
