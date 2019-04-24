<?php

ini_set('display_errors', 1);
error_reporting(-1);

$facebookAppId = '';
$facebookAppSecret = '';
$cacheDir = __DIR__ . '/cache';

interface FetcherInterface
{
    public function getCount($url);
}

class Facebook implements FetcherInterface
{
    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $appSecret;

    /**
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct($appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }

    /**
     * @param string $url
     * @return int
     * @throws ErrorException
     *
     * @see https://developers.facebook.com/docs/graph-api/reference/v3.2/url
     */
    public function getCount($url)
    {
        $response = json_decode(
            $this->sendRequest(
                'https://graph.facebook.com/v3.2/?' . http_build_query([
                    'id'           => $url,
                    'fields'       => 'engagement',
                    'access_token' => $this->appId . '|' . $this->appSecret
                ])
            ),
            true
        );

        if (isset($response['error']) && $response['error']) {
            if (isset($response['error']['message'])) {
                throw new ErrorException($response['error']['message']);
            }

            throw new ErrorException('An error has occurred with the Facebook API call.');
        }

        $share = 0;
        if (isset($response['engagement']) && is_array($response['engagement'])) {
            foreach ($response['engagement'] as $item) {
                $share += (int) $item;
            }
        }

        return $share;
    }

    /**
     * @param string $url
     * @return string
     * @throws ErrorException
     */
    private function sendRequest($url)
    {
        $handle = curl_init();

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($handle);

        $errNo = curl_errno($handle);
        $errDesc = curl_error($handle);

        curl_close($handle);

        if ($errNo) {
            throw new ErrorException($errDesc);
        }

        return $response;
    }
}

interface CacheStoreInterface
{
    /**
     * @param string $id
     * @return string|null
     */
    public function get($id);

    /**
     * @param string $id
     * @param string $value
     * @param int    $ttl
     */
    public function set($id, $value, $ttl = 7200);
}

class FileCacheStore implements CacheStoreInterface
{
    private $cacheDir;

    /**
     * @param string $cacheDir
     * @throws ErrorException
     */
    public function __construct($cacheDir)
    {
        if (empty($cacheDir)) {
            throw new ErrorException('Cache dir must be specified');
        } elseif (!file_exists($cacheDir)) {
            throw new ErrorException('Cache dir does not exist');
        } elseif (!is_readable($cacheDir)) {
            throw new ErrorException('Cache dir is not readable');
        } elseif (!is_writable($cacheDir)) {
            throw new ErrorException('Cache dir is not writable');
        }

        $this->cacheDir = $cacheDir;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $filePath = $this->filePath($id);

        if (file_exists($filePath) && is_readable($filePath)) {
            $data = include $filePath;

            if ($data['expired'] < time()) {
                return null;
            }

            return $data['value'];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function set($id, $value, $ttl = 7200)
    {
        $filePath = $this->filePath($id);
        $dirPath = dirname($filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        $data = [
            'expired' => time() + $ttl,
            'value'   => $value,
        ];

        file_put_contents($filePath, '<?php return ' . var_export($data, true) . ';');
    }

    /**
     * @param string $id
     * @return string
     */
    private function filePath($id)
    {
        $id = md5($id);

        return $this->cacheDir . '/' . substr($id, 0, 1) . '/' . substr($id, 1, 1) . '/' . $id . '.php';
    }
}

class NullCacheStore implements CacheStoreInterface
{
    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function set($id, $value, $ttl = 3600)
    {
    }
}

class Response
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $reasonPhrase;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * @param int    $statusCode
     * @param string $reasonPhrase
     * @param array  $headers
     */
    public function __construct($statusCode = 200, $reasonPhrase = 'OK', array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = $headers;
        $this->body = '';
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     * @return $this
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    /**
     * @param string $reasonPhrase
     * @return $this
     */
    public function setReasonPhrase($reasonPhrase)
    {
        $this->reasonPhrase = $reasonPhrase;

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function addHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    public static function __set_state(array $data)
    {
        $response = new self();
        foreach ($data as $name => $value) {
            $response->$name = $value;
        }

        return $response;
    }
}

class JsonResponse extends Response
{
    public function __construct($statusCode = 200, $reasonPhrase = 'OK', array $headers = [])
    {
        parent::__construct($statusCode, $reasonPhrase, $headers);

        $this->addHeader('Content-Type', 'application/json; charset=utf-8');
    }
}

interface ResponseBuilderInterface
{
    /**
     * @param string           $name
     * @param FetcherInterface $fetcher
     * @return $this
     */
    public function registerFetcher($name, FetcherInterface $fetcher);

    /**
     * @param string $url
     * @return JsonResponse
     */
    public function shareResponse($url);

    /**
     * @param string $message
     * @return JsonResponse
     */
    public function errorResponse($message);
}

class ResponseBuilder implements ResponseBuilderInterface
{
    /**
     * @var FetcherInterface[]
     */
    private $fetcher = [];

    /**
     * @inheritdoc
     */
    public function registerFetcher($name, FetcherInterface $fetcher)
    {
        $this->fetcher[$name] = $fetcher;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function shareResponse($url)
    {
        $share = [];
        foreach ($this->fetcher as $name => $fetcher) {
            $share[$name] = $fetcher->getCount($url);
        }

        $responseBody = json_encode([
            'error' => false,
            'share' => $share,
        ]);

        $response = new JsonResponse();
        $response->setBody($responseBody);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function errorResponse($message)
    {
        $response = new JsonResponse();
        $response->setBody(json_encode([
            'error'   => true,
            'message' => $message,
        ]));

        return $response;
    }
}

class CachedResponseBuilder implements ResponseBuilderInterface
{
    /**
     * @var ResponseBuilderInterface
     */
    private $responseBuilder;

    /**
     * @var CacheStoreInterface
     */
    private $cache;

    /**
     * @param ResponseBuilderInterface $responseBuilder
     * @param CacheStoreInterface      $cache
     */
    public function __construct(ResponseBuilderInterface $responseBuilder, CacheStoreInterface $cache)
    {
        $this->responseBuilder = $responseBuilder;
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     */
    public function registerFetcher($name, FetcherInterface $fetcher)
    {
        $this->responseBuilder->registerFetcher($name, $fetcher);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function shareResponse($url)
    {
        $response = $this->cache->get($url);

        if ($response instanceof Response) {
            $response->addHeader('X-Cache', 'HIT');
        } else {
            $response = $this->responseBuilder->shareResponse($url);
            $this->cache->set($url, $response);

            $response->addHeader('X-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function errorResponse($message)
    {
        return $this->responseBuilder->errorResponse($message);
    }
}

class ResponseSender
{
    public function send(Response $response)
    {
        header('HTTP/1.1 ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());

        foreach ($response->getHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->getBody();
    }
}

/**
 * HTTP Response
 */
$responseBuilder = new ResponseBuilder();

try {
    $responseBuilder = new CachedResponseBuilder(
        $responseBuilder,
        new FileCacheStore($cacheDir)
    );

    if (empty($_GET['url'])) {
        throw new ErrorException('You must specify an URL.');
    }

    if (empty($facebookAppId) || empty($facebookAppSecret)) {
        throw new ErrorException('You must specify the Facebook App ID and app secret in the stats file.');
    }

    $responseBuilder->registerFetcher('facebook', new Facebook($facebookAppId, $facebookAppSecret));

    $response = $responseBuilder->shareResponse($_GET['url']);
} catch (Exception $e) {
    $response = $responseBuilder->errorResponse($e->getMessage());
}

(new ResponseSender())->send($response);
