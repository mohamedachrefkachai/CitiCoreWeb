<?php
// src/Service/GeminiService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeminiService
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }

    public function generateResponse(string $prompt): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key='.$this->apiKey,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [
                            'parts' => [
                                ['text' => $this->createPrompt($prompt)]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.9,
                            'topP' => 0.8,
                            'topK' => 40,
                            'maxOutputTokens' => 1024
                        ],
                        'safetySettings' => [
                            [
                                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                            ],
                            [
                                'category' => 'HARM_CATEGORY_HARASSMENT',
                                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                            ],
                            [
                                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                            ],
                            [
                                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                            ]
                        ]
                    ],
                    'timeout' => 30  // Increased timeout
                ]
            );

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode !== 200) {
                throw new \RuntimeException('API returned status: '.$statusCode);
            }

            if (!isset($content['candidates'][0]['content']['parts'][0]['text'])) {
                throw new \RuntimeException('Unexpected API response format');
            }

            return $content['candidates'][0]['content']['parts'][0]['text'];

        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $errorResponse = $e->getResponse();
            $errorContent = $errorResponse->getContent(false);
            $errorDetails = json_decode($errorContent, true) ?? [];
            
            return "API Error (HTTP {$errorResponse->getStatusCode()}): " 
                . ($errorDetails['error']['message'] ?? $e->getMessage());

        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            return "Connection error: " . $e->getMessage();
            
        } catch (\Exception $e) {
            return "Désolé, une erreur s'est produite: " . $e->getMessage();
        }
    }

    private function createPrompt(string $userInput): string
    {
        return "Tu es un assistant conversationnel pour une plateforme de dons appelée Citicore. " .
               "Tu dois répondre aux questions en français de manière concise, amicale et professionnelle. " .
               "Si on te pose une question hors sujet, explique poliment que tu ne peux répondre " .
               "qu'à des questions concernant les dons et les associations. " .
               "Question: " . $userInput;
    }
}