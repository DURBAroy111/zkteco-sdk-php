<?php

namespace DurbaRoy\Zkteco\Connection;

class SocketClient
{
    protected string $ip;
    protected int $port;
    protected int $timeout;

    /** @var resource|null */
    protected $socket = null;

    public function __construct(string $ip, int $port = 4370, int $timeout = 5)
    {
        $this->ip      = $ip;
        $this->port    = $port;
        $this->timeout = $timeout;
    }

    public function connect(): void
    {
        // Reuse existing valid resource
        if ($this->socket !== null && is_resource($this->socket)) {
            return;
        }

        $address = "udp://{$this->ip}";
        $errno   = 0;
        $errstr  = '';

        $conn = @fsockopen($address, $this->port, $errno, $errstr, $this->timeout);

        if ($conn === false) {
            $this->socket = null;

            throw new \RuntimeException(
                "Unable to connect to ZKTeco device {$this->ip}:{$this->port} via UDP - [{$errno}] {$errstr}"
            );
        }

        stream_set_timeout($conn, $this->timeout);
        $this->socket = $conn;
    }

    public function close(): void
    {
        if ($this->socket !== null && is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * Send raw binary packet and get raw binary response (UDP).
     */
    public function send(string $packet): string
    {
        if ($this->socket === null || !is_resource($this->socket)) {
            $this->connect();
        }

        $written = @fwrite($this->socket, $packet);

        if ($written === false || $written !== strlen($packet)) {
            throw new \RuntimeException('Failed to write to UDP socket.');
        }

        $response = @fread($this->socket, 8192);

        if ($response === false || $response === '') {
            $meta = stream_get_meta_data($this->socket);

            if (!empty($meta['timed_out'])) {
                throw new \RuntimeException(
                    'Failed to read from UDP socket: timed out waiting for device response.'
                );
            }

            throw new \RuntimeException(
                'Failed to read from UDP socket: empty response from device.'
            );
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
