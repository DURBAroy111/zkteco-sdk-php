<?php

namespace DurbaRoy\Zkteco\Protocol;

class PacketBuilder
{
    private int $sessionId = 0;
    private int $replyId   = 0;

    public function setSessionId(int $sessionId): void
    {
        $this->sessionId = $sessionId & 0xFFFF;
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    private function nextReplyId(): int
    {
        $this->replyId++;
        if ($this->replyId > 0xFFFF) {
            $this->replyId = 1;
        }
        return $this->replyId;
    }

    /**
     * Build a packet: [cmd, checksum, sessionId, replyId] + payload
     */
    public function build(int $command, string $payload = ''): string
    {
        $replyId = $this->nextReplyId();
        $session = $this->sessionId;

        // checksum = 0 temporarily
        $header  = pack('vvvv', $command, 0, $session, $replyId);
        $packet  = $header . $payload;

        $checksum = $this->checksum($packet);

        // rebuild header with checksum
        $header  = pack('vvvv', $command, $checksum, $session, $replyId);
        return $header . $payload;
    }

    /**
     * ZK checksum (16-bit): sum of little-endian 16-bit words, then ones'-complement.
     */
    private function checksum(string $packet): int
    {
        $sum = 0;
        $len = strlen($packet);

        for ($i = 0; $i < $len; $i += 2) {
            $lo = ord($packet[$i]);
            $hi = ($i + 1 < $len) ? ord($packet[$i + 1]) : 0;
            $word = $lo | ($hi << 8);
            $sum = ($sum + $word) & 0xFFFF;
        }

        $checksum = (0x10000 - $sum) & 0xFFFF;
        if ($checksum === 0) {
            $checksum = 0xFFFF;
        }

        return $checksum;
    }
}
