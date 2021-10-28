<?php

namespace Anik\Amqp\Tests\Integration;

use Anik\Amqp\Exchanges\Direct;
use Anik\Amqp\Exchanges\Exchange;
use Anik\Amqp\Exchanges\Fanout;
use Anik\Amqp\Exchanges\Headers;
use Anik\Amqp\Exchanges\Topic;
use Anik\Amqp\Producer;
use Anik\Amqp\Producible;
use Anik\Amqp\ProducibleMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ProducerTest extends AmqpTestCase
{
    protected function getMessage($message = 'anik.amqp.msg', array $properties = []): Producible
    {
        return new ProducibleMessage($message, $properties);
    }

    protected function getProducer(?AbstractConnection $connection = null, ?AMQPChannel $channel = null): Producer
    {
        return new Producer($connection ?? $this->connection, $channel ?? $this->channel);
    }

    protected function publishExpectation(
        $expectedMessage,
        $expectedExchangeName = ProducerTest::EXCHANGE_NAME,
        $expectedRoutingKey = ProducerTest::ROUTING_KEY,
        $expectedMandatory = false,
        $expectedImmediate = false,
        $expectedTicket = null,
        $method = 'batch_basic_publish',
        $times = 1
    ) {
        $this->setMethodExpectationsOnChannel(
            [
                $method => [
                    'times' => $times,
                    'checks' => $this->returnCallback(
                        function (
                            $msg,
                            $en,
                            $rk,
                            $mandatory,
                            $immediate,
                            $ticket
                        ) use (
                            $expectedMessage,
                            $expectedExchangeName,
                            $expectedRoutingKey,
                            $expectedMandatory,
                            $expectedImmediate,
                            $expectedTicket
                        ) {
                            $this->assertInstanceOf(AMQPMessage::class, $msg);
                            $this->assertEquals($expectedRoutingKey, $rk);
                            $this->assertEquals($expectedExchangeName, $en);
                            $this->assertEquals($expectedMandatory, $mandatory);
                            $this->assertEquals($expectedImmediate, $immediate);
                            $this->assertEquals($expectedTicket, $ticket);
                        }
                    ),
                ],
            ]
        );

        if ($method === 'batch_basic_publish') {
            $this->setMethodExpectationsOnChannel(
                [
                    'publish_batch' => ['times' => $this->any(), 'return' => true],
                ]
            );
        }
    }

    public function publishMessageDataProvider(): array
    {
        return [
            'exchange passed as parameter' => [
                [
                    'exchange' => $this->getExchange(['declare' => false]),
                ],
            ],
            'should not declare exchange declared with false' => [
                [
                    'exchange' => $this->getExchange(['declare' => false]),
                    'expectations' => [
                        'exchange' => ['times' => $this->never()],
                    ],
                ],
            ],
            'should declare exchange when declare is true' => [
                [
                    'exchange' => $this->getExchange(['declare' => true]),
                    'expectations' => [
                        'exchange' => ['times' => $this->once()],
                    ],
                ],
            ],
            'when exchange is null it should create instance from options' => [
                [
                    'options' => [
                        'exchange' => ($options = $this->exchangeOptions(['declare' => true])),
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->once()],
                        'exchange_name' => $options['name'],
                    ],
                ],
            ],
            'when exchange is created from options and declare is false' => [
                [
                    'options' => [
                        'exchange' => ($options = $this->exchangeOptions(['declare' => false])),
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->never()],
                        'exchange_name' => $options['name'],
                    ],
                ],
            ],
            'exchange can be reconfigured through options' => [
                [
                    'exchange' => $this->getExchange(),
                    'options' => [
                        'exchange' => ['declare' => false],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->never()],
                    ],
                ],
            ],
            'with fanout exchange' => [
                [
                    'exchange' => Fanout::make(['name' => self::EXCHANGE_NAME]),
                    'options' => [
                        'exchange' => ['declare' => true],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->once(),],
                    ],
                ],
            ],
            'with topic exchange' => [
                [
                    'exchange' => Topic::make(['name' => self::EXCHANGE_NAME]),
                    'options' => [
                        'exchange' => ['declare' => false],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->never(),],
                    ],
                ],
            ],
            'with headers exchange' => [
                [
                    'exchange' => Headers::make(['name' => self::EXCHANGE_NAME]),
                    'options' => [
                        'exchange' => ['declare' => false],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->never(),],
                    ],
                ],
            ],
            'with direct exchange' => [
                [
                    'exchange' => Direct::make(['name' => self::EXCHANGE_NAME]),
                    'options' => [
                        'exchange' => ['declare' => true],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->once(),],
                    ],
                ],
            ],
            'with default exchange' => [
                [
                    'exchange' => Exchange::make(['name' => '', 'type' => '']),
                    'options' => [
                        'exchange' => ['declare' => true],
                    ],
                    'expectations' => [
                        'exchange' => ['times' => $this->once(),],
                    ],
                ],
            ],
            'mandatory, immediate, ticket can be set through options with key publish' => [
                [
                    'exchange' => $this->getExchange(),
                    'options' => [
                        'publish' => [
                            'mandatory' => true,
                            'immediate' => true,
                            'ticket' => 5,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider publishMessageDataProvider
     *
     * @param array $data
     */
    public function testPublishBasic(array $data)
    {
        $msg = $data['message'] ?? $this->getMessage();
        $routingKey = $data['routing_key'] ?? $this->routingKey();
        $exchange = $data['exchange'] ?? null;
        $mandatory = $data['options']['publish']['mandatory'] ?? false;
        $immediate = $data['options']['publish']['immediate'] ?? false;
        $ticket = $data['options']['publish']['ticket'] ?? null;
        $this->exchangeDeclareExpectation($data['expectations']['exchange']['times'] ?? null);

        $this->publishExpectation(
            $msg,
            $exchange instanceof Exchange ? $exchange->getName() : ($data['expectations']['exchange_name'] ?? ''),
            $routingKey,
            $mandatory,
            $immediate,
            $ticket,
            'basic_publish'
        );

        $options = [];
        if ($data['options']['exchange'] ?? false) {
            $options['exchange'] = $data['options']['exchange'];
        }

        if ($data['options']['publish'] ?? []) {
            $options['publish'] = $data['options']['publish'];
        }

        $this->getProducer()->publishBasic($msg, $routingKey, $exchange, $options);
    }
}
