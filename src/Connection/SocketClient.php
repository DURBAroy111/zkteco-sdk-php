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
        // If we already have a valid resource, reuse it.
        if ($this->socket !== null && is_resource($this->socket)) {
            return;
        }

        $address = "tcp://{$this->ip}:{$this->port}";
        $errno   = 0;
        $errstr  = '';

        $conn = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($conn === false) {
            // Ensure we don't keep a bad value
            $this->socket = null;

            throw new \RuntimeException(
                "Unable to connect to ZKTeco device {$this->ip}:{$this->port} via TCP - [{$errno}] {$errstr}"
            );
        }

        $this->socket = $conn;
        stream_set_timeout($this->socket, $this->timeout);
    }

    public function close(): void
    {
        if ($this->socket !== null && is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    /**
     * Send raw binary packet and get raw binary response (TCP).
     */
    public function send(string $packet): string
    {
        if ($this->socket === null || !is_resource($this->socket)) {
            $this->connect();
        }

        $total   = strlen($packet);
        $written = 0;

        while ($written < $total) {
            $w = @fwrite($this->socket, substr($packet, $written));
            if ($w === false || $w === 0) {
                throw new \RuntimeException('Failed to write to TCP socket.');
            }
            $written += $w;
        }

        $response = @fread($this->socket, 8192);

        if ($response === false || $response === '') {
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new \RuntimeException(
                    'Failed to read from TCP socket: timed out waiting for device response.'
                );
            }

            throw new \RuntimeException(
                'Failed to read from TCP socket: empty response from device.'
            );
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
