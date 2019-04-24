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

class ResponseSender
{
    /**
     * @var FetcherInterface[]
     */
    private $fetcher = [];

    /**
     * @param string           $name
     * @param FetcherInterface $fetcher
     */
    public function registerFetcher($name, FetcherInterface $fetcher)
    {
        $this->fetcher[$name] = $fetcher;
    }

    /**
     * @param string $url
     */
    public function sendShare($url)
    {
        $this->sendHeaders();

        $share = [];
        foreach ($this->fetcher as $name => $fetcher) {
            $share[$name] = $fetcher->getCount($url);
        }

        echo json_encode([
            'error' => false,
            'share' => $share,
        ]);
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
