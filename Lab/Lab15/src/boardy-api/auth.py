import os
from typing import Any, Dict

import jwt
from fastapi import Depends, HTTPException
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jwt import ExpiredSignatureError, InvalidTokenError

security = HTTPBearer(auto_error=False)

OAUTH_PUBLIC_KEY_PATH = os.getenv(
    "OAUTH_PUBLIC_KEY",
    "/laravel-storage/oauth-public.key",
)


def load_public_key() -> str:
    if not os.path.exists(OAUTH_PUBLIC_KEY_PATH):
        raise HTTPException(
            status_code=503,
            detail=f"OAuth public key not found: {OAUTH_PUBLIC_KEY_PATH}",
        )

    with open(OAUTH_PUBLIC_KEY_PATH, "r", encoding="utf-8") as file:
        return file.read()


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> Dict[str, Any]:
    if credentials is None or credentials.scheme.lower() != "bearer":
        raise HTTPException(status_code=401, detail="Token required")

    token = credentials.credentials

    try:
        payload = jwt.decode(
            token,
            load_public_key(),
            algorithms=["RS256"],
            options={"verify_aud": False},
        )
    except ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Token expired")
    except InvalidTokenError as error:
        raise HTTPException(
            status_code=401,
            detail=f"Invalid token: {str(error)}",
        )

    if "sub" not in payload:
        raise HTTPException(status_code=401, detail="Invalid token subject")

    return payload
