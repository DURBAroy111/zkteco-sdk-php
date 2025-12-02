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
        if ($this->socket !== null) {
            return;
        }

        // TCP instead of UDP:
        $address = "tcp://{$this->ip}:{$this->port}";
        $errno   = 0;
        $errstr  = '';

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $this->socket) {
            throw new \RuntimeException(
                "Unable to connect to ZKTeco device {$this->ip}:{$this->port} via TCP - [{$errno}] {$errstr}"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send raw binary packet and get raw binary response (TCP).
     */
    public function send(string $packet): string
    {
        if ($this->socket === null) {
            $this->connect();
        }

        $total = strlen($packet);
        $written = 0;

        while ($written < $total) {
            $w = @fwrite($this->socket, substr($packet, $written));
            if ($w === false || $w === 0) {
                throw new \RuntimeException('Failed to write to TCP socket.');
            }
            $written += $w;
        }

        // Read 1 response frame (ZK packets are small; we can read once)
        $response = @fread($this->socket, 8192);

        if ($response === false || $response === '') {
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new \RuntimeException('Failed to read from TCP socket: timed out waiting for device response.');
            }

            throw new \RuntimeException('Failed to read from TCP socket: empty response from device.');
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
