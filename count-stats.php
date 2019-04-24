<?php

ini_set('display_errors', 1);
error_reporting(-1);

$fb_app_id = '';
$fb_app_secret = '';

/**
 * @param string $id
 * @return int
 * @throws ErrorException
 *
 * @see https://developers.facebook.com/docs/graph-api/reference/v3.2/url
 */
function fb_api_url($id)
{
    global $fb_app_id, $fb_app_secret;

    $response = json_decode(
        request(
            'https://graph.facebook.com/v3.2/?' . http_build_query([
                'id'           => $id,
                'fields'       => 'engagement',
                'access_token' => $fb_app_id . '|' . $fb_app_secret
            ])
        ),
        true
    );

    if (isset($response['error']) && $response['error']) {
        if (isset($response['message'])) {
            throw new ErrorException($response['message']);
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
function request($url)
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

/**
 * HTTP Response
 */
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_GET['url'])) {
        throw new ErrorException('You must specify an URL.');
    }

    if (empty($fb_app_id) || empty($fb_app_secret)) {
        throw new ErrorException('You must specify the Facebook App ID and app secret in the stats file.');
    }

    $url = $_GET['url'];

    echo json_encode([
        'error' => false,
        'share' => [
            'facebook' => fb_api_url($url),
        ],
    ]);
} catch (Exception $e) {
    exit(json_encode([
        'error'   => true,
        'message' => $e->getMessage(),
    ]));
}
