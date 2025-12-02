<?php

namespace DurbaRoy\Zkteco\Protocol;

class PacketParser
{
    public function parseHeader(string $response): array
    {
        if (strlen($response) < 8) {
            throw new \RuntimeException('Invalid response length');
        }

        [$command, $checksum, $sessionId, $replyId] = array_values(unpack('vcommand/vchecksum/vsessionId/vreplyId', substr($response, 0, 8)));

        return [
            'command'   => $command,
            'checksum'  => $checksum,
            'sessionId' => $sessionId,
            'replyId'   => $replyId,
            'payload'   => substr($response, 8),
        ];
    }

    public function payload(string $response): string
    {
        return substr($response, 8);
    }
}
