import asyncio
import json
import logging
import os
from datetime import datetime

import redis.asyncio as redis
from fastapi import FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware

from routers import comments
from routers import ws
from routers.ws import manager

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("boardy-api")

REDIS_URL = os.getenv("REDIS_URL", "redis://redis:6379")

app = FastAPI(title="Boardy API", version="0.3.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(ws.router)
app.include_router(comments.router)


@app.get("/api/status")
async def status():
    return {
        "status": "ok",
        "time": str(datetime.now()),
    }


@app.post("/internal/broadcast")
async def internal_broadcast(request: Request):
    data = await request.json()
    await manager.broadcast({
        "type": "new_post",
        "post": data,
    })
    return {"ok": True}


async def redis_subscriber():
    client = redis.from_url(REDIS_URL, decode_responses=True)
    pubsub = client.pubsub()

    await pubsub.subscribe("new_post", "user.renamed")

    logger.info("Redis subscriber started: new_post, user.renamed")

    try:
        async for message in pubsub.listen():
            if message["type"] != "message":
                continue

            channel = message["channel"]
            payload = message["data"]

            try:
                data = json.loads(payload)
            except Exception:
                data = {"raw": payload}

            if channel == "new_post":
                await manager.broadcast({
                    "type": "new_post",
                    "post": data,
                })

            elif channel == "user.renamed":
                await manager.broadcast({
                    "type": "user_renamed",
                    "user": data,
                })

    except asyncio.CancelledError:
        logger.info("Redis subscriber stopped")
        raise

    finally:
        await pubsub.close()
        await client.close()


@app.on_event("startup")
async def startup_event():
    app.state.redis_task = asyncio.create_task(redis_subscriber())


@app.on_event("shutdown")
async def shutdown_event():
    task = getattr(app.state, "redis_task", None)

    if task:
        task.cancel()
        try:
            await task
        except asyncio.CancelledError:
            pass

@app.get("/health")
def health():
    return {"ok": True}

@app.get("/api/health")
def api_health():
    return {"ok": True}
