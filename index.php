<?php

$refreshToken = $_SERVER['SPOTIFY_REFRESH_TOKEN'] ?? $_ENV['SPOTIFY_REFRESH_TOKEN'];
$clientId = $_SERVER['SPOTIFY_CLIENT_ID'] ?? $_ENV['SPOTIFY_CLIENT_ID'];
$clientSecret = $_SERVER['SPOTIFY_CLIENT_SECRET'] ?? $_ENV['SPOTIFY_CLIENT_SECRET'];
$playlistId = $_SERVER['SPOTIFY_PLAYLIST_ID'] ?? $_ENV['SPOTIFY_PLAYLIST_ID'];

set_error_handler(function (int $type, string $message, string $file, int $line) {
    throw new \ErrorException($message, 0, $type, $file, $line);
});

set_exception_handler(function (\Throwable $e) {
    echo "An exception occurred\n";
    echo $e;
    echo "\n";
});

function get_access_token(string $clientId, string $clientSecret, string $refreshToken): string
{
    $basicToken = base64_encode("$clientId:$clientSecret");

    $data = http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
    ]);

    $opts = [
        'http' => [
            'protocol_version' => 1.1,
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Accept: */*',
                "Authorization: Basic $basicToken",
                'Content-Type: application/x-www-form-urlencoded',
            ]),
            'content' => $data,
        ],
    ];

    $context = stream_context_create($opts);

    $endpoint = 'https://accounts.spotify.com/api/token';

    $result = file_get_contents($endpoint, false, $context);

    if (false === $result) {
        throw new \RuntimeException("Could not get token for client ($clientId).");
    }

    return json_decode($result, true)['access_token'];
}

function add_track_to_playlist(string $accessToken, string $playlistId, string $trackId)
{
    $opts = [
        'http' => [
            'protocol_version' => 1.1,
            'method' => 'POST',
            'header' => implode("\r\n", [
                "Authorization: Bearer $accessToken",
                'Content-Length: 0',
            ]),
        ],
    ];

    $context = stream_context_create($opts);

    $endpoint = "https://api.spotify.com/v1/playlists/$playlistId/tracks?";
    $endpoint .= http_build_query([
        'uris' => $trackId,
    ]);

    $ok = file_get_contents($endpoint, false, $context);

    if (false === $ok) {
        throw new \RuntimeException("Could not add track ($trackId) to the playlist ($playlistId).");
    }
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload) {
    echo 'no payload';

    return;
}

if ('url_verification' === $payload['type']) {
    echo $payload['challenge'];

    return;
}

if ('event_callback' === $payload['type']) {
    $trackIds = [];

    $links = $payload['event']['links'] ?? [];
    foreach ($links as ['url' => $url]) {
        $host = parse_url($url, PHP_URL_HOST);
        if ('open.spotify.com' !== $host) {
            continue;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = ltrim($path, '/');
        $parts = explode('/', $path);

        if (count($parts) < 2 || 'track' !== $parts[0]) {
            continue;
        }

        $trackIds[] = 'spotify:track:'.$parts[1];
    }

    if (!$trackIds) {
        echo 'no tracks detected';

        return;
    }

    $accessToken = get_access_token($clientId, $clientSecret, $refreshToken);
    foreach ($trackIds as $trackId) {
        add_track_to_playlist($accessToken, $playlistId, $trackId);
    }

    echo 'OK';

    return;
}

echo 'payload no supported';
