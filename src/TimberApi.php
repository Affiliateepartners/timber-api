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

        $request_id = json_decode($json)[0]->context->http->request_id;

        $this->logJsonAttempt($json, $request_id);

        try
        {
            $response = $this->postRequest('/frames', $json);

            $this->logSuccess($response, $request_id);

            return $response;
        }
        catch(\GuzzleHttp\Exception\ConnectException $e)
        {
            $data = [
                'msg' => $e->getMessage(),
                'body' => null
            ];

            $this->logError($data, $request_id);

            throw $e;
        }
        catch(\GuzzleHttp\Exception\ClientException $e)
        {
            $response = $e->getResponse();

            $data = [
                'msg' => $e->getMessage(),
                'body' => (string)$response->getContents(),
            ];

            $this->logError($data, $request_id);

            throw $e;
        }
        catch(\Exception $e)
        {
            $data = [
                'msg' => $e->getMessage(),
                'body' => null
            ];

            $this->logError($data, $request_id);

            throw $e;
        }
    }

    private function postRequest(string $path, string $body): \GuzzleHttp\Psr7\Response
    {
        return $this->http_client->post($path,  $this->prepareRequestOptions($body));
    }

    private function prepareRequestOptions(string $body = null): array
    {
        return [
            'body'    => $body,
            'headers' => $this->prepareRequestHeaders(),
            'timeout' => 60,
        ];
    }

    private function logJsonAttempt(string $json, string $request_id)
    {
        $record     = \Illuminate\Support\Facades\Redis::get($request_id);

        if(!$record)
        {
            $data = [
                'attempts' => 0,
                'time' => \Carbon\Carbon::now()->__toString(),
                'responses' => [],
                'body' => json_decode($json, true)[0],
            ];
        }
        else
        {
            $data = json_decode($record, true);
        }

        $data['attempts']++;
        $data['responses'][$data['attempts']] = null;

        \Illuminate\Support\Facades\Redis::set($request_id, json_encode($data));
    }

    private function logError($data, $request_id)
    {
        $record  = \Illuminate\Support\Facades\Redis::get($request_id);
        $olddata = json_decode($record, true);

        $olddata['responses'][$olddata['attempts']] = json_encode($data);

        \Illuminate\Support\Facades\Redis::set("{$request_id}", json_encode($olddata));
    }

    private function logSuccess($response, $request_id)
    {
        $record = \Illuminate\Support\Facades\Redis::get($request_id);
        $data  = json_decode($record, true);

        $data['responses'][$data['attempts']] = json_encode([
            'response_code' => $response->getStatusCode()
        ]);

        if($data['responses'][$data['attempts']] > 1)
        {
            \Illuminate\Support\Facades\Redis::set("ok_{$request_id}", json_encode($data));
        }
        \Illuminate\Support\Facades\Redis::del($request_id);
    }

    private function prepareRequestHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($this->token),
            'Content-Type'  => $this->content_type,
        ];
    }
}
