import {
    generateVerifier,
    generateChallenge,
    generateState,
} from './pkce.js';

const CLIENT_ID = window.BoardyConfig?.oauthClientId || 'a1e20b09-d9e2-435d-81dc-855559999787';
const REDIRECT_URI = window.location.origin + '/oauth/callback';

let accessToken = sessionStorage.getItem('boardy_access_token') || null;

export function getAccessToken() {
    return accessToken;
}

export function setAccessToken(token) {
    accessToken = token;

    if (token) {
        sessionStorage.setItem('boardy_access_token', token);
    } else {
        sessionStorage.removeItem('boardy_access_token');
    }
}

export async function startLogin() {
    if (!CLIENT_ID) {
        alert('OAuth Client ID is not configured');
        return;
    }

    const verifier = generateVerifier();
    const challenge = await generateChallenge(verifier);
    const state = generateState();

    sessionStorage.setItem('pkce_verifier', verifier);
    sessionStorage.setItem('oauth_state', state);

    const params = new URLSearchParams({
        client_id: CLIENT_ID,
        response_type: 'code',
        redirect_uri: REDIRECT_URI,
        code_challenge: challenge,
        code_challenge_method: 'S256',
        state: state,
        prompt: 'consent',
    });

    window.location.href = '/oauth/authorize?' + params.toString();
}

export async function handleCallback() {
    const params = new URLSearchParams(window.location.search);

    const code = params.get('code');
    const state = params.get('state');

    if (!code) {
        return null;
    }

    const savedState = sessionStorage.getItem('oauth_state');

    if (!savedState || state !== savedState) {
        throw new Error('Invalid OAuth state');
    }

    const verifier = sessionStorage.getItem('pkce_verifier');

    if (!verifier) {
        throw new Error('PKCE verifier not found');
    }

    const body = new URLSearchParams({
        grant_type: 'authorization_code',
        client_id: CLIENT_ID,
        redirect_uri: REDIRECT_URI,
        code: code,
        code_verifier: verifier,
    });

    const response = await fetch('/oauth/token', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
        },
        body: body,
    });

    const data = await response.json();

    if (!response.ok) {
        console.error('Token exchange failed:', data);
        throw new Error(data.message || data.error_description || 'Token exchange failed');
    }

    sessionStorage.removeItem('pkce_verifier');
    sessionStorage.removeItem('oauth_state');

    setAccessToken(data.access_token);

    return data.access_token;
}

export async function refreshToken() {
    const body = new URLSearchParams({
        grant_type: 'refresh_token',
        client_id: CLIENT_ID,
    });

    const response = await fetch('/oauth/token', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
        },
        body: body,
    });

    const data = await response.json();

    if (!response.ok) {
        console.error('Refresh token failed:', data);
        setAccessToken(null);
        return null;
    }

    setAccessToken(data.access_token);

    return data.access_token;
}

export async function authedFetch(url, options = {}) {
    let token = getAccessToken();

    if (!token) {
        token = await refreshToken();

        if (!token) {
            await startLogin();
            return null;
        }
    }

    let response = await fetch(url, {
        ...options,
        headers: {
            ...(options.headers || {}),
            'Authorization': 'Bearer ' + token,
            'Accept': 'application/json',
        },
    });

    if (response.status === 401) {
        const newToken = await refreshToken();

        if (!newToken) {
            await startLogin();
            return null;
        }

        response = await fetch(url, {
            ...options,
            headers: {
                ...(options.headers || {}),
                'Authorization': 'Bearer ' + newToken,
                'Accept': 'application/json',
            },
        });
    }

    return response;
}

export function logoutLocal() {
    setAccessToken(null);
}

window.BoardyAuth = {
    startLogin,
    handleCallback,
    refreshToken,
    authedFetch,
    getAccessToken,
    logoutLocal,
};
