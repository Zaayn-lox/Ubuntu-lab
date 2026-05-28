export function generateVerifier() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);

    return base64UrlEncode(array);
}

export async function generateChallenge(verifier) {
    const data = new TextEncoder().encode(verifier);
    const hash = await crypto.subtle.digest('SHA-256', data);

    return base64UrlEncode(new Uint8Array(hash));
}

export function generateState() {
    return generateVerifier();
}

function base64UrlEncode(bytes) {
    let binary = '';

    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

window.BoardyPkce = {
    generateVerifier,
    generateChallenge,
    generateState,
};
