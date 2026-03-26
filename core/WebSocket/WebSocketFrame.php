<?php

namespace Framework\Core\WebSocket;

class WebSocketFrame
{
    /**
     * Encode data into WebSocket frame
     */
    public static function encode(string $payload, int $opcode = 0x1): string
    {
        $frame = '';
        $payloadLength = strlen($payload);
        
        // FIN (1 bit) + RSV (3 bits) + Opcode (4 bits)
        $frame .= chr(0x80 | $opcode);
        
        // Mask (1 bit) + Payload length (7 bits)
        if ($payloadLength < 126) {
            $frame .= chr($payloadLength);
        } elseif ($payloadLength < 65536) {
            $frame .= chr(126) . pack('n', $payloadLength);
        } else {
            $frame .= chr(127) . pack('J', $payloadLength);
        }
        
        $frame .= $payload;
        
        return $frame;
    }
    
    /**
     * Decode WebSocket frame with partial frame detection
     */
    public static function decode(string $data): array
    {
        $frame = [
            'fin' => 0,
            'opcode' => 0,
            'masked' => 0,
            'payload' => '',
            'length' => 0,
            'complete' => false,
            'bytesRead' => 0
        ];
        
        $dataLength = strlen($data);
        
        if ($dataLength < 2) {
            return $frame; // Not enough data for header
        }
        
        $byte1 = ord($data[0]);
        $byte2 = ord($data[1]);
        
        $frame['fin'] = ($byte1 & 0x80) >> 7;
        $frame['opcode'] = $byte1 & 0x0F;
        $frame['masked'] = ($byte2 & 0x80) >> 7;
        
        $payloadLength = $byte2 & 0x7F;
        $offset = 2;
        
        // Extended payload length
        if ($payloadLength === 126) {
            if ($dataLength < 4) {
                return $frame; // Not enough data
            }
            $payloadLength = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLength === 127) {
            if ($dataLength < 10) {
                return $frame; // Not enough data
            }
            $payloadLength = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }
        
        // RFC 6455: Control frames must have payload <= 125
        $isControlFrame = ($frame['opcode'] >= 0x8);
        if ($isControlFrame && $payloadLength > 125) {
            throw new \RuntimeException("Control frame payload exceeds 125 bytes (RFC 6455 violation)");
        }
        
        $frame['length'] = $payloadLength;
        
        // Get masking key if present
        $maskingKey = '';
        if ($frame['masked']) {
            if ($dataLength < $offset + 4) {
                return $frame; // Not enough data
            }
            $maskingKey = substr($data, $offset, 4);
            $offset += 4;
        }
        
        // Check if we have the complete payload
        if ($dataLength < $offset + $payloadLength) {
            return $frame; // Partial frame - need more data
        }
        
        // Get payload
        $payload = substr($data, $offset, $payloadLength);
        
        // Unmask payload if masked (optimized)
        if ($frame['masked'] && $maskingKey) {
            $payload = self::unmask($payload, $maskingKey, $payloadLength);
        }
        
        $frame['payload'] = $payload;
        $frame['complete'] = true;
        $frame['bytesRead'] = $offset + $payloadLength;
        
        return $frame;
    }
    
    /**
     * Optimized XOR unmasking
     */
    private static function unmask(string $payload, string $maskingKey, int $length): string
    {
        $unmasked = '';
        
        // Process 4 bytes at a time for better performance
        $chunks = (int)($length / 4);
        $remainder = $length % 4;
        
        for ($i = 0; $i < $chunks; $i++) {
            $offset = $i * 4;
            $unmasked .= $payload[$offset] ^ $maskingKey[0];
            $unmasked .= $payload[$offset + 1] ^ $maskingKey[1];
            $unmasked .= $payload[$offset + 2] ^ $maskingKey[2];
            $unmasked .= $payload[$offset + 3] ^ $maskingKey[3];
        }
        
        // Handle remaining bytes
        $offset = $chunks * 4;
        for ($i = 0; $i < $remainder; $i++) {
            $unmasked .= $payload[$offset + $i] ^ $maskingKey[$i];
        }
        
        return $unmasked;
    }
}
