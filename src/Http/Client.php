<?php

namespace PDFfiller\TelescopeClient\Http;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\RequestOptions;

/**
 * Class Client
 * @package App\Supervisor\Client
 */
class Client extends GuzzleHttpClient
{
    /** @var string */
    protected $cacheKey = 'telescope_server_oauth_token';

    /** @var string */
    protected $configKey = 'telescope-client.server';

    /** @var array */
    protected $headers = [];

    /**
     * Client constructor.
     *
     * @param array $options
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $config = config($this->configKey);

        if (!isset($config['base_uri']) || !isset($config['client_id']) || !isset($config['client_secret'])) {
            throw new \Exception('You need to set config ' . $this->configKey);
        }

        $options['http_errors'] = false;
        $options['base_uri'] = $config['base_uri'];

        parent::__construct($options);

        $this->headers = [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $this->getBearerToken($config),
                'Accept'        => 'application/json',
            ]
        ];
    }

    /**
     * @param $config
     *
     * @return string
     * @throws \Exception
     */
    protected function getBearerToken($config): string
    {
        if (cache($this->cacheKey)) {
            return cache($this->cacheKey);
        }

        $response = $this->post('/oauth/token', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
            ],
            RequestOptions::FORM_PARAMS => [
                'client_id'     => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'grant_type'    => 'client_credentials',
            ],
        ]);

        $tokenData = json_decode((string) $response->getBody(), true);

        if (!isset($tokenData['expires_in']) || !isset($tokenData['access_token'])) {
            throw new \Exception('Can\'t get bearer token from ' . $config['base_uri']);
        }

        $tokenExpiresIn = (int)$tokenData['expires_in'];
        $token = $tokenData['access_token'];

        $this->cacheToken($token, $tokenExpiresIn);

        return $token;
    }

    /**
     * @param $data
     * @return array
     */
    protected function prepare($data)
    {
        return array_merge(
            $this->headers,
            $data
        );
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public function getCachedToken(): ?string
    {
        if (cache($this->cacheKey)) {
            return cache($this->cacheKey);
        }

        return null;
    }

    /**
     * @param string $token
     * @param int $tokenExpiresIn
     *
     * @throws \Exception
     */
    protected function cacheToken(string $token, int $tokenExpiresIn): void
    {
        cache(
            [$this->cacheKey => $token],
            $tokenExpiresIn / 60 - 1
        );
    }

    /**
     * @param string $uri
     * @param array $data
     * @return object
     */
    public function post(string $uri, array $data = [])
    {
        return parent::post($uri, $this->prepare($data));
    }

    /**
     * @param string $uri
     * @param array $data
     * @return object
     */
    public function get(string $uri, array $data = [])
    {
        return parent::get($uri, $this->prepare($data));
    }

    /**
     * @param string $uri
     * @param array $data
     * @return object
     */
    public function put(string $uri, array $data = [])
    {
        return parent::put($uri, $this->prepare($data));
    }

    /**
     * @param string $uri
     * @param array $data
     * @return mixed
     */
    public function delete(string $uri, array $data = [])
    {
        return parent::delete($uri, $this->prepare($data));
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $data
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request($method, $uri = '', array $data = [])
    {
        return parent::request($method, $uri, $this->prepare($data));
    }
}
