<?php

namespace Liteweb\TimberApi;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class TimberApi
{
    private $http_client;
    private $token;
    private $content_type = RequestParam::PLAIN;

    function __construct()
    {
        $this->http_client = new \GuzzleHttp\Client([
            'base_uri' => 'https://logs.timber.io'
        ]);
    }

    public function setAuthToken(string $token)
    {
        $this->token = $token;
    }

    public function sendRawLogLine(string $line): \GuzzleHttp\Psr7\Response
    {
        return $this->postRequest('/frames', $line);
    }

    public function sendJsonLogLine(string $json): \GuzzleHttp\Psr7\Response
    {
        $this->content_type = RequestParam::JSON;

        return $this->postRequest('/frames', $json);
    }

    private function postRequest(string $path, string $body): \GuzzleHttp\Psr7\Response
    {
        return $this->http_client->post($path,  $this->prepareRequestOptions($body));
    }

    private function prepareRequestOptions(string $body = null): array
    {
        return [
            'body'    => $body,
            'headers' => $this->prepareRequestHeaders()
        ];
    }

    private function prepareRequestHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->token),
            'Content-Type'  => $this->content_type,
        ];
    }
}