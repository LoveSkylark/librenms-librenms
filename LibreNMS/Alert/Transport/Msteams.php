<?php
    /*
    * LibreNMS
    *
    * Copyright (c) 2016 Søren Friis Rosiak <sorenrosiak@gmail.com>
    * This program is free software: you can redistribute it and/or modify it
    * under the terms of the GNU General Public License as published by the
    * Free Software Foundation, either version 3 of the License, or (at your
    * option) any later version.  Please see LICENSE.txt at the top level of
    * the source code distribution for details.
    */

    namespace LibreNMS\Alert\Transport;

    use LibreNMS\Alert\Transport;
    use LibreNMS\Exceptions\AlertTransportDeliveryException;
    use LibreNMS\Util\Http;

    class Msteams extends Transport
    {
        protected string $name = 'Microsoft Teams';

        public function deliverAlert(array $alert_data): bool
        {
            $data = [
                'title' => $alert_data['title'],
                'themeColor' => self::getColorForState($alert_data['state']),
                'text' => strip_tags($alert_data['msg'], '<strong><em><h1><h2><h3><strike><ul><ol><li><pre><blockquote><a><img><p>'),
                'summary' => $alert_data['title'],
            ];

            $client = Http::client();

            // template will contain raw json
            if ($this->config['use-json'] === 'on') {
                if ($this->config['connection-method'] === 'Workflow') {
                    $workflowContent = $alert_data['uid'] === '000'
                        ? $this->workflowCard() // Use pre-made workflowCard for tests
                        : $alert_data['msg'];
                    
                    // Include Microsoft Teams header for standardizing AdaptiveCard format.
                    $msg = [
                        'type' => 'message',
                        'attachments' => [
                            [
                                'contentType' => 'application/vnd.microsoft.card.adaptive',
                                'content' => json_decode($workflowContent, true) // Decode the JSON string to an associative array
                            ]
                        ]
                    ];
                } else {
                    $msg = $alert_data['uid'] === '000'
                        ? $this->webhookCard() // Use pre-made webhookCard for tests
                        : $alert_data['msg'];
                }
            
                $client->withBody($msg, 'application/json');
            }

            $res = $client->post($this->config['msteam-url'], $data);

            if ($res->successful()) {
                return true;
            }

            throw new AlertTransportDeliveryException($alert_data, $res->status(), $res->body(), $data['text'], $data);
        }

        public static function configTemplate(): array
        {
            return [
                'config' => [
                    [
                        'title' => 'Teams URL',
                        'name' => 'msteam-url',
                        'descr' => 'Microsoft Teams Webhook URL',
                        'type' => 'text',
                    ],
                    [
                        'title' => 'Connection Method',
                        'name' => 'connection-method',
                        'descr' => 'Webhook or Workflow',
                        'type' => 'select',
                        'options' => [
                            'Webhook' => 'Webhook',
                            'Workflow' => 'Workflow',
                        ],
                    ],
                    [
                        'title' => 'Use JSON?',
                        'name' => 'use-json',
                        'descr' => 'Compose MessageCard with JSON rather than Markdown. Your template must be valid MessageCard JSON',
                        'type' => 'checkbox',
                        'default' => false,
                    ],
                ],
                'validation' => [
                    'msteam-url' => 'required|url',
                ],
            ];
        }

        private function webhookCard(): string
        {
            return '{
                "@context": "https://schema.org/extensions",
                "@type": "MessageCard",
                "potentialAction": [
                    {
                        "@type": "OpenUri",
                        "name": "View MessageCard Reference",
                        "targets": [
                            {
                                "os": "default",
                                "uri": "https://learn.microsoft.com/en-us/outlook/actionable-messages/message-card-reference"
                            }
                        ]
                    },
                    {
                        "@type": "OpenUri",
                        "name": "View LibreNMS Website",
                        "targets": [
                            {
                                "os": "default",
                                "uri": "https://www.librenms.org/"
                            }
                        ]
                    }
                ],
                "sections": [
                    {
                        "facts": [
                            {
                                "name": "Next Action:",
                                "value": "Make your alert template emit valid MessageCard Json"
                            }
                        ],
                        "text": "You have successfully sent a pre-formatted MessageCard message to teams."
                    }
                ],
                "summary": "Test Successful",
                "themeColor": "0072C6",
                "title": "Test MessageCard"
            }';
        }

        private function workflowCard(): string
        {
            return '{
                "type": "message",
                "attachments": [
                    {
                    "contentType": "application/vnd.microsoft.card.adaptive",
                    "content": {
                        "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
                        "type": "AdaptiveCard",
                        "version": "1.6",
                        "body": [
                        {
                            "type": "TextBlock",
                            "text": "Test MessageCard",
                            "size": "Medium",
                            "weight": "Bolder",
                            "wrap": true
                        },
                        {
                            "type": "TextBlock",
                            "text": "You have successfully sent a pre-formatted MessageCard message to teams.",
                            "wrap": true
                        }
                        ],
                        "actions": [
                        {
                            "type": "Action.OpenUrl",
                            "title": "View MessageCard Reference",
                            "url": "https://learn.microsoft.com/en-us/outlook/actionable-messages/message-card-reference"
                        },
                        {
                            "type": "Action.OpenUrl",
                            "title": "View LibreNMS Website",
                            "url": "https://www.librenms.org/"
                        }
                        ],
                        "themeColor": "0072C6",
                        "summary": "Test Successful"
                    }
                    }
                ]
            }';
        }
    }
