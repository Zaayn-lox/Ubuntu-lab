import asyncio
import json
import logging
from contextlib import asynccontextmanager
from datetime import datetime

import redis.asyncio as redis

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from routers import comments
from routers import ws


logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("boardy-api")


REDIS_URL = "redis://127.0.0.1:6379"


async def redis_subscriber():
    await asyncio.sleep(1)

    redis_client = redis.from_url(REDIS_URL, decode_responses=True)
    pubsub = redis_client.pubsub()

    await pubsub.subscribe("new_post", "user.renamed")

    logger.info("Redis subscriber started: new_post, user.renamed")

    try:
        async for message in pubsub.listen():
            if message.get("type") != "message":
                continue

            channel = message.get("channel")
            raw_data = message.get("data")

            try:
                data = json.loads(raw_data)
            except Exception as error:
                logger.warning("Invalid Redis message: %s", error)
                continue

            if channel == "new_post":
                await ws.manager.broadcast({
                    "type": "new_post",
                    "post": data,
                })

            elif channel == "user.renamed":
                await comments.db_execute(
                    """
                    UPDATE comments
                    SET author_name = %s
                    WHERE author_id = %s
                    """,
                    data["new_name"],
                    int(data["id"]),
                )

                await ws.manager.broadcast({
                    "type": "user_renamed",
                    "user_id": int(data["id"]),
                    "new_name": data["new_name"],
                })

    except asyncio.CancelledError:
        logger.info("Redis subscriber stopped")
        raise

    finally:
        await pubsub.close()
        await redis_client.close()


@asynccontextmanager
async def lifespan(app: FastAPI):
    task = asyncio.create_task(redis_subscriber())

    try:
        yield
    finally:
        task.cancel()

        try:
            await task
        except asyncio.CancelledError:
            pass


app = FastAPI(
    title="Boardy API",
    version="0.5.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://belyaevubuntu.ru",
        "https://www.belyaevubuntu.ru",
    ],
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
