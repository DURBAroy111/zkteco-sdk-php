<?php

namespace DurbaRoy\Zkteco;

use DurbaRoy\Zkteco\Connection\SocketClient;
use DurbaRoy\Zkteco\Models\DeviceUser;
use DurbaRoy\Zkteco\Models\FingerprintTemplate;
use DurbaRoy\Zkteco\Protocol\Commands;
use DurbaRoy\Zkteco\Protocol\PacketBuilder;
use DurbaRoy\Zkteco\Protocol\PacketParser;

class ZktecoClient
{
    private SocketClient  $socket;
    private PacketBuilder $builder;
    private PacketParser  $parser;

    public function __construct(
        string $ip,
        int $port = 4370,
        int $timeout = 3
    ) {
        $this->socket  = new SocketClient($ip, $port, $timeout);
        $this->builder = new PacketBuilder();
        $this->parser  = new PacketParser();
    }

    /**
     * Establish session with device (CMD_CONNECT).
     */
    public function connect(): void
    {
        $this->socket->connect();

        $packet   = $this->builder->build(Commands::CMD_CONNECT);
        $response = $this->socket->send($packet);

        $header   = $this->parser->parse($response);
        $payload  = $header['payload'];

        // For many devices, first 2 bytes of payload are new session ID
        if (strlen($payload) >= 2) {
            $unpacked = unpack('vsessionId', substr($payload, 0, 2));
            $this->builder->setSessionId($unpacked['sessionId']);
        }
    }

    public function disconnect(): void
    {
        $packet   = $this->builder->build(Commands::CMD_EXIT);
        $this->socket->send($packet);
    }

    public function disableDevice(): void
    {
        $packet   = $this->builder->build(Commands::CMD_DISABLEDEVICE);
        $this->socket->send($packet);
    }

    public function enableDevice(): void
    {
        $packet   = $this->builder->build(Commands::CMD_ENABLEDEVICE);
        $this->socket->send($packet);
    }

    /**
     * Get all users from device.
     *
     * @return DeviceUser[]
     */
    public function getUsers(): array
    {
        // 1) Ask device to prepare user data (PREPARE_DATA + CMD_DEVICE/SET_USER etc.)
        // 2) Read blocks using CMD_DATA until all received.
        //
        // The exact sequence is protocol-specific; typical pattern:
        //   - send CMD_PREPARE_DATA with CMD_DEVICE or similar
        //   - read reply with total size
        //   - loop: send CMD_DATA, concat payloads
        //
        // Here we sketch a simple implementation; you MUST adjust based on your
        // device’s lower protocol manual.

        $allPayload = $this->requestPreparedData(Commands::CMD_DEVICE); // generic “device data” command

        return $this->parseUsersPayload($allPayload);
    }

    /**
     * Get all fingerprint templates from device.
     *
     * @return FingerprintTemplate[]
     */
    public function getFingerprintTemplates(): array
    {
        // Similar idea: some devices use CMD_USERTEMP_RRQ + PREPARE_DATA/DATA.
        $allPayload = $this->requestPreparedData(Commands::CMD_USERTEMP_RRQ);

        return $this->parseTemplatesPayload($allPayload);
    }

    /**
     * Create/update a user on the device.
     */
    public function setUser(DeviceUser $user): void
    {
        $payload = $this->buildUserPayload($user);
        $packet  = $this->builder->build(Commands::CMD_SET_USER, $payload);
        $this->socket->send($packet);
    }

    /**
     * Upload a fingerprint template for an existing user.
     */
    public function setFingerprintTemplate(FingerprintTemplate $tpl): void
    {
        $payload = $this->buildTemplatePayload($tpl);
        // Many implementations send this via CMD_DATA after CMD_PREPARE_DATA;
        // here we show a single-packet style. Adjust per your device docs.
        $packet  = $this->builder->build(Commands::CMD_DATA, $payload);
        $this->socket->send($packet);
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    /**
     * Request *prepared* data (users, templates, logs) from device.
     *
     * 1. Send CMD_PREPARE_DATA with sub-command in payload.
     * 2. Receive total size.
     * 3. Loop sending CMD_DATA until all bytes are read.
     */
    private function requestPreparedData(int $subCommand): string
    {
        // Build payload for PREPARE_DATA: 2 bytes subCommand, 2 bytes zero
        $payload = pack('vv', $subCommand, 0);

        $packet   = $this->builder->build(Commands::CMD_PREPARE_DATA, $payload);
        $response = $this->socket->send($packet);
        $header   = $this->parser->parse($response);

        if ($header['command'] !== Commands::CMD_ACK_OK &&
            $header['command'] !== Commands::CMD_PREPARE_DATA &&
            $header['command'] !== Commands::CMD_ACK_DATA) {
            // You can add more robust handling here
        }

        // Total size usually comes in payload as a 4-byte integer
        $payload = $header['payload'];
        if (strlen($payload) < 4) {
            // no data
            return '';
        }

        $un = unpack('Vsize', substr($payload, 0, 4)); // 32-bit little-endian
        $sizeToRead = $un['size'];

        $data = '';

        while (strlen($data) < $sizeToRead) {
            $packet   = $this->builder->build(Commands::CMD_DATA);
            $response = $this->socket->send($packet);
            $header   = $this->parser->parse($response);

            if ($header['command'] !== Commands::CMD_DATA &&
                $header['command'] !== Commands::CMD_ACK_DATA) {
                break; // or throw exception
            }

            $data .= $header['payload'];

            if (strlen($header['payload']) === 0) {
                break;
            }
        }

        return $data;
    }

    /**
     * Parse concatenated user records into DeviceUser objects.
     * Each record size is USER_DATA_SIZE (72 bytes) in the classic protocol.
     *
     * NOTE: The offsets inside the 72 bytes depend on your specific manual.
     * Here is a common layout: PIN, Privilege, Password[8], Name[24], Card[4], ...
     */
    private function parseUsersPayload(string $payload): array
    {
        $users = [];
        $recordSize = Commands::USER_DATA_SIZE;
        $total = strlen($payload);

        for ($offset = 0; $offset + $recordSize <= $total; $offset += $recordSize) {
            $chunk = substr($payload, $offset, $recordSize);

            // Example layout (you MUST check with your device docs):
            //  0-1: PIN (U16)
            //    2: Privilege (U8)
            //  3-10: Password[8]
            // 11-34: Name[24]
            // 35-38: Card[4]
            // ... remaining bytes ignored or used for group/timezone/etc.

            $pinArr = unpack('vpin', substr($chunk, 0, 2));
            $pin    = $pinArr['pin'];

            $privilege = ord($chunk[2]);

            $passwordRaw = substr($chunk, 3, 8);
            $password    = rtrim($passwordRaw, "\0");

            $nameRaw     = substr($chunk, 11, 24);
            $name        = rtrim($nameRaw, "\0");

            $cardRaw     = substr($chunk, 35, 4);
            $cardNumber  = bin2hex($cardRaw); // or some other formatting

            $users[] = new DeviceUser(
                pin: $pin,
                privilege: $privilege,
                password: $password,
                name: $name,
                cardNumber: $cardNumber,
                userId: null // you can map this yourself based on your DB
            );
        }

        return $users;
    }

    /**
     * Parse concatenated fingerprint templates into FingerprintTemplate objects.
     *
     * A common template structure (C-style) is roughly:
     *   Size (U16), PIN (U16), FingerID (1 byte), Valid (1 byte), Template bytes...
     *
     * You MUST confirm exact layout/size in your manual.
     */
    private function parseTemplatesPayload(string $payload): array
    {
        $templates = [];
        $offset    = 0;
        $total     = strlen($payload);

        while ($offset + 6 <= $total) {
            // Read size (2 bytes)
            $sizeArr = unpack('vsize', substr($payload, $offset, 2));
            $size    = $sizeArr['size'];
            if ($size === 0) {
                break;
            }

            // Each template record payload length: size + header fields (2 + 2 +1+1)
            if ($offset + 6 + $size > $total) {
                break;
            }

            $pinArr = unpack('vpin', substr($payload, $offset + 2, 2));
            $pin    = $pinArr['pin'];

            $fingerIndex = ord($payload[$offset + 4]);
            $validFlag   = ord($payload[$offset + 5]);
            $valid       = ($validFlag === 1);

            $tplData = substr($payload, $offset + 6, $size);

            $templates[] = new FingerprintTemplate(
                pin: $pin,
                fingerIndex: $fingerIndex,
                valid: $valid,
                templateData: $tplData
            );

            // Move to next record
            $offset += 6 + $size;
        }

        return $templates;
    }

    /**
     * Build a 72-byte user record payload for CMD_SET_USER.
     * Layout MUST match your device’s expected struct.
     */
    private function buildUserPayload(DeviceUser $user): string
    {
        $pin       = $user->pin & 0xFFFF;
        $privilege = $user->privilege & 0xFF;

        $password  = substr($user->password, 0, 8);
        $password  = str_pad($password, 8, "\0");

        $name      = substr($user->name, 0, 24);
        $name      = str_pad($name, 24, "\0");

        $cardBytes = hex2bin(str_pad($user->cardNumber ?? '', 8, '0', STR_PAD_LEFT)) ?: str_repeat("\0", 4);

        // Minimal payload: PIN, Privilege, Password[8], Name[24], Card[4], zero padding to 72 bytes
        $payload  = pack('vC', $pin, $privilege);
        $payload .= $password;
        $payload .= $name;
        $payload .= $cardBytes;

        // pad to 72 bytes
        if (strlen($payload) < Commands::USER_DATA_SIZE) {
            $payload = str_pad($payload, Commands::USER_DATA_SIZE, "\0");
        } else {
            $payload = substr($payload, 0, Commands::USER_DATA_SIZE);
        }

        return $payload;
    }

    /**
     * Build template payload for CMD_DATA upload.
     */
    private function buildTemplatePayload(FingerprintTemplate $tpl): string
    {
        $size    = strlen($tpl->templateData);
        $pin     = $tpl->pin & 0xFFFF;
        $finger  = $tpl->fingerIndex & 0xFF;
        $valid   = $tpl->valid ? 1 : 0;

        $header = pack('vvCC', $size, $pin, $finger, $valid);

        return $header . $tpl->templateData;
    }
}
