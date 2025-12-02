<?php

namespace DurbaRoy\Zkteco\Protocol;

class PacketBuilder
{
    protected int $sessionId = 0;
    protected int $replyId = 0;

    public function reset(): void
    {
        $this->sessionId = 0;
        $this->replyId   = 0;
    }

    public function nextReplyId(): int
    {
        $this->replyId++;
        if ($this->replyId > 65535) {
            $this->replyId = 1;
        }
        return $this->replyId;
    }

    public function setSessionId(int $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    /**
     * Build a generic packet.
     *
     * @param int $command
     * @param string $payload
     */
    public function build(int $command, string $payload = ''): string
    {
        $length   = 8 + strlen($payload); // header length (8 bytes) + payload
        $replyId  = $this->nextReplyId();
        $session  = $this->sessionId;

        // header without checksum
        $header = pack('vvvv', $command, 0, $session, $replyId); // checksum placeholder = 0

        $packet = $header . $payload;
        $checksum = $this->checksum($packet);

        // rebuild header with checksum
        $header = pack('vvvv', $command, $checksum, $session, $replyId);
        return $header . $payload;
    }

    protected function checksum(string $packet): int
    {
        // Basic checksum algorithm used by many ZK devices (you can refine it from specs)
        $sum = 0;
        $len = strlen($packet);
        for ($i = 0; $i < $len; $i += 2) {
            $val = ord($packet[$i]) | (isset($packet[$i + 1]) ? ord($packet[$i + 1]) << 8 : 0);
            $sum += $val;
            $sum = $sum & 0xFFFF;
        }
        $checksum = 0xFFFF - $sum + 1;
        $checksum = $checksum & 0xFFFF;
        if ($checksum === 0) {
            $checksum = 0xFFFF;
        }
        return $checksum;
    }
}
