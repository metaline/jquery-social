<?php

ini_set('display_errors', 1);
error_reporting(-1);

$facebookAppId = '';
$facebookAppSecret = '';

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
     */
    public function set($id, $value);
}

class FileCacheStore
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
    public function set($id, $value, $ttl = 3600)
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
    public function set($id, $value)
    {
    }
}

class ResponseSender
{
    /**
     * @var FileCacheStore
     */
    private $cache;

    /**
     * @var FetcherInterface[]
     */
    private $fetcher = [];

    /**
     * @param FileCacheStore $cache
     * @return $this
     */
    public function setCache(FileCacheStore $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * @return FileCacheStore
     */
    private function getCache()
    {
        if (null === $this->cache) {
            $this->cache = new NullCacheStore();
        }

        return $this->cache;
    }

    /**
     * @param string           $name
     * @param FetcherInterface $fetcher
     * @return $this
     */
    public function registerFetcher($name, FetcherInterface $fetcher)
    {
        $this->fetcher[$name] = $fetcher;

        return $this;
    }

    /**
     * @param string $url
     */
    public function sendShare($url)
    {
        $this->sendHeaders();

        $response = $this->getCache()->get($url);

        if (null === $response) {
            $share = [];
            foreach ($this->fetcher as $name => $fetcher) {
                $share[$name] = $fetcher->getCount($url);
            }

            $response = json_encode([
                'error' => false,
                'share' => $share,
            ]);

            $this->getCache()->set($url, $response);
        }

        echo $response;
    }

    /**
     * @param string $message
     */
    public function sendError($message)
    {
        $this->sendHeaders();

        echo json_encode([
            'error'   => true,
            'message' => $message,
        ]);
    }

    private function sendHeaders()
    {
        header('Content-Type: application/json; charset=utf-8');
    }
}

/**
 * HTTP Response
 */
$responseSender = new ResponseSender();

try {
    $responseSender->setCache(new FileCacheStore(__DIR__ . '/cache'));

    if (empty($_GET['url'])) {
        throw new ErrorException('You must specify an URL.');
    }

    if (empty($facebookAppId) || empty($facebookAppSecret)) {
        throw new ErrorException('You must specify the Facebook App ID and app secret in the stats file.');
    }

    $responseSender->registerFetcher('facebook', new Facebook($facebookAppId, $facebookAppSecret));

    $responseSender->sendShare($_GET['url']);
} catch (Exception $e) {
    $responseSender->sendError($e->getMessage());
}
