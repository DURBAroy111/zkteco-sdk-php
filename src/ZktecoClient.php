<?php

namespace DurbaRoy\Zkteco;

use DurbaRoy\Zkteco\Connection\SocketClient;
use DurbaRoy\Zkteco\Models\DeviceUser;
use DurbaRoy\Zkteco\Models\FingerprintTemplate;
use DurbaRoy\Zkteco\Protocol\PacketBuilder;
use DurbaRoy\Zkteco\Protocol\PacketParser;

class ZktecoClient
{
    protected SocketClient $socket;
    protected PacketBuilder $builder;
    protected PacketParser $parser;

    public function __construct(string $ip, int $port = 4370)
    {
        $this->socket  = new SocketClient($ip, $port);
        $this->builder = new PacketBuilder();
        $this->parser  = new PacketParser();
    }

    public function connect(): void
    {
        $this->socket->connect();

        // Build & send "connect" packet
        $packet   = $this->builder->build(0x0000); // Command ID for "connect" (example)
        $response = $this->socket->send($packet);

        $header = $this->parser->parseHeader($response);

        // Session ID comes from payload or header depending on device
        $payload = $header['payload'];
        $sessionId = unpack('vsessionId', substr($payload, 0, 2))['sessionId'] ?? 0;
        $this->builder->setSessionId($sessionId);
    }

    public function disableDevice(): void
    {
        $packet   = $this->builder->build(0x0002); // example: disable command ID
        $response = $this->socket->send($packet);
        // parse & check success from $response
    }

    public function enableDevice(): void
    {
        $packet   = $this->builder->build(0x0003); // example: enable command ID
        $response = $this->socket->send($packet);
        // parse & check success
    }

    /**
     * Read all users from device.
     * @return DeviceUser[]
     */
    public function getUsers(): array
    {
        $users = [];

        // 1. Send "read all users" command
        $packet   = $this->builder->build(0x0005); // placeholder command ID
        $response = $this->socket->send($packet);

        $header  = $this->parser->parseHeader($response);
        $payload = $header['payload'];

        // 2. Parse payload according to ZK protocol user structure
        //    (offsets: user id, name, password, role, etc.)
        // For now, we leave as TODO; you'll implement based on protocol doc.

        // Example pseudo-code:
        /*
        $offset = 0;
        while ($offset < strlen($payload)) {
            $uid = trim(substr($payload, $offset, 9), "\0");
            $offset += 9;
            $role   = ord($payload[$offset]); $offset += 1;
            $password = trim(substr($payload, $offset, 8), "\0"); $offset += 8;
            $name   = trim(substr($payload, $offset, 24), "\0"); $offset += 24;

            $users[] = new DeviceUser($uid, $role, $name, $password);
        }
        */

        return $users;
    }

    /**
     * Read all fingerprint templates from device.
     * @return FingerprintTemplate[]
     */
    public function getFingerprintTemplates(): array
    {
        $templates = [];

        // TODO: implement "read templates" command & parse payload
        // Use protocol spec to know record size, fields, etc.

        return $templates;
    }

    public function setUser(DeviceUser $user): void
    {
        // Build payload according to protocol, then send
        // $payload = ...
        // $packet = $this->builder->build(COMMAND_SET_USER, $payload);
        // $this->socket->send($packet);
    }

    public function setFingerprintTemplate(FingerprintTemplate $template): void
    {
        // Build payload with userId, fingerIndex, templateData, flags
        // $packet = $this->builder->build(COMMAND_SET_TEMPLATE, $payload);
        // $this->socket->send($packet);
    }
}
