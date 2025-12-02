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
        $this->ip      = $ip;
        $this->port    = $port;
        $this->timeout = $timeout;
    }

    public function connect(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (! $this->socket) {
            throw new \RuntimeException('Unable to create UDP socket: ' . socket_strerror(socket_last_error()));
        }

        if (! @socket_connect($this->socket, $this->ip, $this->port)) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new \RuntimeException("Unable to connect to device {$this->ip}:{$this->port} - {$error}");
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec'  => $this->timeout,
            'usec' => 0,
        ]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec'  => $this->timeout,
            'usec' => 0,
        ]);
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send a raw binary packet and return raw binary response.
     */
    public function send(string $packet): string
    {
        if ($this->socket === null) {
            $this->connect();
        }

        $len = strlen($packet);
        $sent = @socket_write($this->socket, $packet, $len);

        if ($sent === false || $sent !== $len) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new \RuntimeException("Failed to write to socket: {$error}");
        }

        $buffer = '';
        $bytesRead = @socket_recv($this->socket, $buffer, 8192, 0);

        if ($bytesRead === false) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new \RuntimeException("Failed to read from socket: {$error}");
        }

        return $buffer;
    }

    public function __destruct()
    {
        $this->close();
    }
}
