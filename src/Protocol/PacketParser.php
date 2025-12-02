<?php

namespace DurbaRoy\Zkteco\Protocol;

class PacketParser
{
    /**
     * @return array{command:int,checksum:int,sessionId:int,replyId:int,payload:string}
     */
    public function parse(string $response): array
    {
        if (strlen($response) < 8) {
            throw new \RuntimeException('Response too short, cannot parse header');
        }

        $header = unpack('vcommand/vchecksum/vsessionId/vreplyId', substr($response, 0, 8));

        return [
            'command'   => $header['command'],
            'checksum'  => $header['checksum'],
            'sessionId' => $header['sessionId'],
            'replyId'   => $header['replyId'],
            'payload'   => substr($response, 8),
        ];
    }
}
