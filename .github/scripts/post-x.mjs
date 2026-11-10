/**
 * Posts a single status update to X (Twitter) via OAuth 1.0a user context.
 *
 * Expects POST_TEXT plus X_API_KEY / X_API_SECRET / X_ACCESS_TOKEN /
 * X_ACCESS_TOKEN_SECRET environment variables. Requires the twitter-api-v2
 * package to be installed beforehand.
 */
import { TwitterApi } from 'twitter-api-v2';

const {
    POST_TEXT,
    X_API_KEY,
    X_API_SECRET,
    X_ACCESS_TOKEN,
    X_ACCESS_TOKEN_SECRET,
} = process.env;

if (!POST_TEXT) {
    console.error('POST_TEXT is empty');
    process.exit(1);
}

const client = new TwitterApi({
    appKey: X_API_KEY,
    appSecret: X_API_SECRET,
    accessToken: X_ACCESS_TOKEN,
    accessSecret: X_ACCESS_TOKEN_SECRET,
});

const { data } = await client.v2.tweet(POST_TEXT);
console.log(`Posted to X: https://x.com/i/web/status/${data.id}`);
