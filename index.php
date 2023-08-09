<?php

$enabledLog = (bool) ($_SERVER['SPOTIFY_ENABLED_LOG'] ?? $_ENV['SPOTIFY_ENABLED_LOG'] ?? false);

set_error_handler(function (int $type, string $message, string $file, int $line) {
    throw new \ErrorException($message, 0, $type, $file, $line);
});

set_exception_handler(function (\Throwable $e) {
    log2('An exception occurred.', (string) $e);
    echo "An exception occurred\n";
    echo $e;
    echo "\n";
});

$refreshToken = $_SERVER['SPOTIFY_REFRESH_TOKEN'] ?? $_ENV['SPOTIFY_REFRESH_TOKEN'];
$clientId = $_SERVER['SPOTIFY_CLIENT_ID'] ?? $_ENV['SPOTIFY_CLIENT_ID'];
$clientSecret = $_SERVER['SPOTIFY_CLIENT_SECRET'] ?? $_ENV['SPOTIFY_CLIENT_SECRET'];
$playlistId = $_SERVER['SPOTIFY_PLAYLIST_ID'] ?? $_ENV['SPOTIFY_PLAYLIST_ID'];

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

function get_artists_first_track(string $accessToken, string $artistId): ?string
{
    log2("Get first track for artist ($artistId).");

    $opts = [
        'http' => [
            'protocol_version' => 1.1,
            'method' => 'GET',
            'header' => implode("\r\n", [
                "Authorization: Bearer $accessToken",
                'Content-Length: 0',
            ]),
        ],
    ];

    $context = stream_context_create($opts);

    $endpoint = "https://api.spotify.com/v1/artists/$artistId/top-tracks?market=FR";

    $topTracks = file_get_contents($endpoint, false, $context);

    if (false === $topTracks) {
        throw new \RuntimeException("Could not get first track for artist ($artistId).");
    }

    $topTracks = json_decode($topTracks, true);

    return $topTracks['tracks'][0]['id'] ?? null;
}

function get_album_first_track(string $accessToken, string $albumId): ?string
{
    log2("Get first track for album ($albumId).");

    $opts = [
        'http' => [
            'protocol_version' => 1.1,
            'method' => 'GET',
            'header' => implode("\r\n", [
                "Authorization: Bearer $accessToken",
                'Content-Length: 0',
            ]),
        ],
    ];

    $context = stream_context_create($opts);

    $endpoint = "https://api.spotify.com/v1/albums/$albumId";

    $album = file_get_contents($endpoint, false, $context);

    if (false === $album) {
        throw new \RuntimeException("Could not get first track for artist ($albumId).");
    }

    $album = json_decode($album, true);

    return $album['tracks']['items'][0]['id'] ?? null;
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
        'position' => 0,
    ]);

    $ok = file_get_contents($endpoint, false, $context);

    if (false === $ok) {
        throw new \RuntimeException("Could not add track ($trackId) to the playlist ($playlistId).");
    }
}

function log2(string $message, mixed $payload = null)
{
    global $enabledLog;
    if (!$enabledLog) {
        return;
    }

    if (null !== $payload) {
        $message .= ' '.json_encode($payload);
    }

    error_log($message);
}

$payload = json_decode(file_get_contents('php://input'), true);

log2("New payload.", $payload);

if (!$payload) {
    log2('No payload.');
    echo 'no payload';

    return;
}

if ('url_verification' === $payload['type']) {
    log2("return URL verification");

    echo $payload['challenge'];

    return;
}

if ('event_callback' === $payload['type']) {
    // It means the message has not been posted yet.
    // We want to track only shared message in the channel.
    // So we discard this event.
    if ('composer' === $payload['event']['source']) {
        log2('It is a composer event.');

        return;
    }

    $trackIds = [];
    $accessToken = get_access_token($clientId, $clientSecret, $refreshToken);

    $links = $payload['event']['links'] ?? [];
    foreach ($links as ['url' => $url]) {
        $host = parse_url($url, PHP_URL_HOST);
        if ('open.spotify.com' !== $host) {
            log2('Not a spotify link.');
            continue;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = ltrim($path, '/');
        $parts = explode('/', $path);

        log2("Current link's", $parts);

        if (count($parts) < 2) {
            log2('Not recognized.');
            continue;
        }

        switch ($parts[0]) {
            case 'track':
                $trackIds[] = 'spotify:track:'.$parts[1];
                break;
            case 'artist':
                $id = get_artists_first_track($accessToken, $parts[1]);
                if (!$id) {
                    log2('No track found for artist.');
                    break;
                }
                $trackIds[] = 'spotify:track:'.$id;
                break;
            case 'album':
                $id = get_album_first_track($accessToken, $parts[1]);
                if (!$id) {
                    log2('No track found for album.');
                    break;
                }
                $trackIds[] = 'spotify:track:'.$id;
                break;
        }
    }

    if (!$trackIds) {
        log2('No tracks detected.');

        return;
    }

    foreach ($trackIds as $trackId) {
        log2("Add track $trackId to playlist $playlistId.");
        add_track_to_playlist($accessToken, $playlistId, $trackId);
    }

    log2('OK');

    return;
}

log2('Payload no supported.');
echo 'Payload no supported.';
