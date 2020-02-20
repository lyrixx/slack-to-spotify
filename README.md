# Slack to Spotify

Add to a Spotify playlist all songs posted in a slack channel.

## Installation

This project is as simple as possible. Just one file. No dependency. You can host it
anywhere.

### Spotify auth

Spotify auth is very painful for such application!

We need a real application that run on internet... or we can fake it

#### Initial setup

1. Create a [new app](https://developer.spotify.com/dashboard/applications);
2. Edit this app;
3. Set the redirect URI that point to a service you can trust (you don't need to have access to this server, this is just a hack). We will refer to this value with `REDIRECT_URL`.

#### oAuth Dance

Then we will simulate the oAuth dance by hand.

Edit the following URL with your `CLIENT_ID` and `REDIRECT_URL`, then open it in your browser to get the `CODE`.

Note: The playlist must be public. If not, please adapt the scope to your need.

```
https://accounts.spotify.com/authorize?response_type=code&scope=playlist-modify-public&client_id=CLIENT_ID&redirect_uri=REDIRECT_URL
```

For example:

```
https://accounts.spotify.com/authorize?response_type=code&scope=playlist-modify-public&client_id=81cb20b1d11f45d6babacba97c66a303&redirect_uri=https://jolicode.com/hack-spotify-callback
```

Then accept Spotify Conditions and Application Permissions.
Then and you should hit an URL similar to:

```
REDIRECT_URL?code=CODE
```

For example:

```
https://jolicode.com/hack-spotify-callback?code=AQAh2uk4qPB1PePrmWLw7QJ2E5qUTCf7oZQ12HcpLJIkG2euAmSV4plfeYk6sAbdeFXJAlGZF1RzXQMMIvyM8ybnGQ5GDp_ILIW1pTZe9rL4NAaWCQd-2FYxGJYHCjEIaDuGRZFh_UsB7Mjul62z0hDOohBrV5SE8UQzatSwQEEe_qI_s4t9vY3Bei1hcc-ax3FplBKJva8d6kj6JvkZRzTXO4Azg-q39Laod7YOB0PU_3TIsP6hOg
```

Extract the `CODE` from the URL. Once you get it, you can use it to get the token and the refresh token:

```
curl -X POST https://accounts.spotify.com/api/token -u CLIENT_ID:CLIENT_SECRET -d code=CODE -d grant_type=authorization_code -d redirect_uri=REDIRECT_URL
```

For example:

```
curl -X POST https://accounts.spotify.com/api/token -u 81cb20b1d11f45d6babacba97c66a303:this-is-secret -d code=AQCW4dtKP2p51JrnFJEPzXeD2wE1SchGp2fNfdi-LvOkyGVt8WwrMG9W8-CC5Tj004cAmYzsqYtbwRPjG0z5yh8iYE-JrFxkpQmDAGQyN5OOqFFS7B4aYtD3w3sakrpP2b2FxnnE-mYtwhv270VdaTDZ_6JVQYjRCg8McPhUJyHzxWWjL0N2HUjfN4eMV9hDSzUpYIo1uQCUjnKLaKl2v6ZrUs_oUCl4I_GY76_tsKzEz37Vx4BkSQ -d grant_type=authorization_code -d redirect_uri=https://jolicode.com/hack-spotify-callback
```

You should get something similar to:

```json
{
  "access_token": "BQC2K3L.....XXX0Ou",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "AQDEoH5NHTTo_....2dINcnHHU",
  "scope": "playlist-modify-public"
}
```

Here we go! we have a `refresh_token`. We will use it to get a new token each time we need to talk to Spotify.

### Slack configuration

#### Initial setup

1. Create a [new application](https://api.slack.com/apps);
2. Enable Event Subscription;
3. Add "App unfurl domains" on `spotify.com` links;
4. Install the app in the workspace;
5. Invite the bot in the channel `/invite @BOT_NAME`.

### Application configuration

```
cp .env.dist .env
# edit the file
```

Then configure your web server to use theses environnement variable. If you can
not, you can edit the `index.php` and set the variables directly in it.

## Debug

Run the local server:

```
export $(egrep -v '^#' .env | xargs)
php -d variables_order=EGPCS -S 127.0.0.1:9999
```

Start ngrok:

```
ngrok http 9999
```
