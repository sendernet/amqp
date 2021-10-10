<?php

namespace Anik\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazySSLConnection;

class Connection
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $vhost;
    private $sslOptions;
    private $connectOptions;
    private $sslProtocol;

    private $connection = null;
    private $channels = [];

    public function __construct(
        string $host,
        int $port,
        string $username,
        ?string $password,
        string $vhost = '/',
        ?array $sslOptions = [],
        ?array $connectOptions = [],
        ?string $sslProtocol = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->vhost = $vhost;
        $this->sslOptions = $sslOptions;
        $this->connectOptions = $connectOptions;
        $this->sslProtocol = $sslProtocol;
    }

    public function getConnection(): AMQPLazySSLConnection
    {
        return $this->connection ?? ($this->connection = $this->getAmqpConnection());
    }

    public function getChannel(?int $channelId = null): AMQPChannel
    {
        if (!is_null($channelId) && isset($this->channels[$channelId])) {
            return $this->channels[$channelId];
        }

        $channel = $this->getConnection()->channel($channelId);

        return $this->channels[$channel->getChannelId()] = $channel;
    }

    private function getAmqpConnection(): AMQPLazySSLConnection
    {
        return new AMQPLazySSLConnection(
            $this->host,
            $this->port,
            $this->username,
            $this->password,
            $this->vhost,
            $this->sslOptions,
            $this->connectOptions,
            $this->sslProtocol
        );
    }
}
