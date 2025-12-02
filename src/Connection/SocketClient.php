<?php

namespace DurbaRoy\Zkteco\Connection;

class SocketClient
{
    protected string $ip;
    protected int $port;
    protected int $timeout;
    /** @var resource|null */
    protected $socket = null;

    public function __construct(string $ip, int $port = 4370, int $timeout = 3)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    public function connect(): void
    {
        if ($this->socket) {
            return;
        }

        $this->socket = @fsockopen("udp://{$this->ip}", $this->port, $errno, $errstr, $this->timeout);

        if (! $this->socket) {
            throw new \RuntimeException("Unable to connect to ZKTeco device {$this->ip}:{$this->port} - $errstr");
        }

        stream_set_timeout($this->socket, $this->timeout);
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @param string $data Raw binary packet to send
     * @return string Raw binary response
     */
    public function send(string $data): string
    {
        if (! $this->socket) {
            $this->connect();
        }

        $written = fwrite($this->socket, $data);
        if ($written === false || $written !== strlen($data)) {
            throw new \RuntimeException('Failed to write to socket');
        }

        // read response (simple version - improve later)
        $response = fread($this->socket, 8192);

        if ($response === false) {
            throw new \RuntimeException('Failed to read from socket');
        }

        return $response;
    }

    public function __destruct()
    {
        $this->close();
    }
}
