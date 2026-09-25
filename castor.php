<?php

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\http_request;
use function Castor\io;
use function Castor\load_dot_env;
use function Castor\open;

#[AsTask(description: 'Do the Spotify oAuth dance to get a new refresh token')]
function refresh_token(
    #[AsOption(description: 'The redirect URI configured in the Spotify app')]
    ?string $redirectUri = null,
    #[AsOption(description: 'Do not open the authorization URL in the browser')]
    bool $noOpen = false,
): void {
    $env = file_exists(__DIR__.'/.env') ? load_dot_env(__DIR__.'/.env') : [];

    $clientId = ($env['SPOTIFY_CLIENT_ID'] ?? '') ?: io()->ask('Spotify client ID');
    $clientSecret = ($env['SPOTIFY_CLIENT_SECRET'] ?? '') ?: io()->askHidden('Spotify client secret');
    $redirectUri ??= io()->ask('Redirect URI (as configured in the Spotify app)');

    $authorizeUrl = 'https://accounts.spotify.com/authorize?'.http_build_query([
        'response_type' => 'code',
        'scope' => 'playlist-modify-public',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
    ]);

    io()->section('1. Authorize the application');
    io()->writeln('Open the following URL, then accept the Spotify permissions:');
    io()->newLine();
    io()->writeln($authorizeUrl);
    if (!$noOpen) {
        open($authorizeUrl);
    }

    io()->section('2. Get the code');
    $input = io()->ask('Paste the URL you were redirected to (or only the code)', validator: function (?string $value): string {
        if (!$value) {
            throw new \RuntimeException('The value cannot be empty.');
        }

        return $value;
    });

    $code = $input;
    if (str_contains($input, '?')) {
        parse_str((string) parse_url($input, PHP_URL_QUERY), $query);
        if (isset($query['error'])) {
            throw new \RuntimeException("Spotify returned an error: {$query['error']}.");
        }
        $code = $query['code'] ?? throw new \RuntimeException('Could not find the code in the URL.');
    }

    io()->section('3. Exchange the code for a refresh token');
    $response = http_request('POST', 'https://accounts.spotify.com/api/token', [
        'auth_basic' => [$clientId, $clientSecret],
        'body' => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ],
    ]);

    $data = $response->toArray(false);
    if (200 !== $response->getStatusCode() || !isset($data['refresh_token'])) {
        io()->error(['Could not get the refresh token.', json_encode($data)]);

        return;
    }

    $refreshToken = $data['refresh_token'];
    io()->success('Here is your refresh token:');
    io()->writeln($refreshToken);

    if (!file_exists(__DIR__.'/.env') || !io()->confirm('Update SPOTIFY_REFRESH_TOKEN in the .env file?')) {
        return;
    }

    $content = file_get_contents(__DIR__.'/.env');
    $line = "SPOTIFY_REFRESH_TOKEN='{$refreshToken}'";
    $content = preg_match('/^SPOTIFY_REFRESH_TOKEN=.*$/m', $content)
        ? preg_replace('/^SPOTIFY_REFRESH_TOKEN=.*$/m', $line, $content)
        : rtrim($content)."\n{$line}\n";
    fs()->dumpFile(__DIR__.'/.env', $content);

    io()->success('The .env file has been updated.');
}
