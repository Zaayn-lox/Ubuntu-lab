import jwt

from fastapi import Header, HTTPException


PUBLIC_KEY_PATH = "/opt/boardy-api/oauth-public.key"


def load_public_key() -> str:
    with open(PUBLIC_KEY_PATH, "r", encoding="utf-8") as file:
        return file.read()


PUBLIC_KEY = load_public_key()


async def get_current_user(authorization: str = Header(None)):
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Token required")

    token = authorization.split(" ", 1)[1]

    try:
        payload = jwt.decode(
            token,
            PUBLIC_KEY,
            algorithms=["RS256"],
            options={"verify_aud": False},
        )

        if "sub" not in payload:
            raise HTTPException(status_code=401, detail="Invalid token: no subject")

        return payload

    except jwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Token expired")

    except jwt.InvalidTokenError as error:
        raise HTTPException(status_code=401, detail=f"Invalid token: {error}")
