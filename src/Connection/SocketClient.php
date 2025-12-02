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

        $address = "udp://{$this->ip}";
        $errno   = 0;
        $errstr  = '';

        $this->socket = @fsockopen($address, $this->port, $errno, $errstr, $this->timeout);

        if (! $this->socket) {
            throw new \RuntimeException(
                "Unable to connect to ZKTeco device {$this->ip}:{$this->port} - [{$errno}] {$errstr}"
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
     * Send raw binary packet and get raw binary response.
     */
    public function send(string $packet): string
    {
        if ($this->socket === null) {
            $this->connect();
        }

        $written = @fwrite($this->socket, $packet);

        if ($written === false || $written !== strlen($packet)) {
            throw new \RuntimeException('Failed to write to socket (UDP).');
        }

        // read first chunk of response
        $response = @fread($this->socket, 8192);

        if ($response === false || $response === '') {
            $meta = stream_get_meta_data($this->socket);
            if (!empty($meta['timed_out'])) {
                throw new \RuntimeException('Failed to read from socket: timed out waiting for device response.');
            }

            throw new \RuntimeException('Failed to read from socket: empty response from device.');
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
