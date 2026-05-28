import datetime
import os
from typing import Any, Dict, List, Optional

import aiomysql
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field

from auth import get_current_user
from routers.ws import manager

router = APIRouter(prefix="/api")

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "mysql"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USER", "boardy"),
    "password": os.getenv("DB_PASSWORD", "boardy_password"),
    "db": os.getenv("DB_NAME", "boardy_api"),
    "charset": "utf8mb4",
    "autocommit": True,
}


class CommentIn(BaseModel):
    body: str = Field(..., min_length=1, max_length=2000)
    author_name: str = Field(..., min_length=1, max_length=255)


class CommentUpdate(BaseModel):
    body: str = Field(..., min_length=1, max_length=2000)


async def get_db():
    return await aiomysql.connect(**DB_CONFIG)


def normalize_row(row: Optional[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    if row is None:
        return None

    for key, value in list(row.items()):
        if isinstance(value, datetime.datetime):
            row[key] = value.strftime("%Y-%m-%d %H:%M:%S")

    return row


def normalize_rows(rows: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return [normalize_row(row) for row in rows]


async def db_query(sql: str, *params) -> List[Dict[str, Any]]:
    conn = await get_db()

    try:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, params)
            rows = await cur.fetchall()
            return normalize_rows(list(rows))
    finally:
        conn.close()


async def db_query_one(sql: str, *params) -> Optional[Dict[str, Any]]:
    conn = await get_db()

    try:
        async with conn.cursor(aiomysql.DictCursor) as cur:
            await cur.execute(sql, params)
            row = await cur.fetchone()
            return normalize_row(row)
    finally:
        conn.close()


async def db_execute(sql: str, *params) -> int:
    conn = await get_db()

    try:
        async with conn.cursor() as cur:
            affected = await cur.execute(sql, params)
            await conn.commit()
            return affected
    finally:
        conn.close()


async def db_insert(sql: str, *params) -> int:
    conn = await get_db()

    try:
        async with conn.cursor() as cur:
            await cur.execute(sql, params)
            await conn.commit()
            return cur.lastrowid
    finally:
        conn.close()


def current_user_id(payload: dict) -> int:
    try:
        return int(payload["sub"])
    except Exception:
        raise HTTPException(status_code=401, detail="Invalid token subject")


@router.get("/posts/{post_id}/comments")
async def list_comments(post_id: int):
    comments = await db_query(
        """
        SELECT id, post_id, author_id, author_name, body, created_at, updated_at
        FROM comments
        WHERE post_id = %s
        ORDER BY created_at ASC
        """,
        post_id,
    )

    return {
        "comments": comments,
        "count": len(comments),
    }


@router.post("/posts/{post_id}/comments")
async def create_comment(
    post_id: int,
    data: CommentIn,
    user=Depends(get_current_user),
):
    user_id = current_user_id(user)

    comment_id = await db_insert(
        """
        INSERT INTO comments (post_id, author_id, author_name, body)
        VALUES (%s, %s, %s, %s)
        """,
        post_id,
        user_id,
        data.author_name,
        data.body,
    )

    comment = await db_query_one(
        """
        SELECT id, post_id, author_id, author_name, body, created_at, updated_at
        FROM comments
        WHERE id = %s
        """,
        comment_id,
    )

    await manager.broadcast({
        "type": "new_comment",
        "comment": comment,
    })

    return comment


@router.put("/comments/{comment_id}")
async def update_comment(
    comment_id: int,
    data: CommentUpdate,
    user=Depends(get_current_user),
):
    user_id = current_user_id(user)

    existing = await db_query_one(
        """
        SELECT id, author_id
        FROM comments
        WHERE id = %s
        """,
        comment_id,
    )

    if not existing:
        raise HTTPException(status_code=404, detail="Comment not found")

    if int(existing["author_id"]) != user_id:
        raise HTTPException(status_code=403, detail="Not your comment")

    await db_execute(
        """
        UPDATE comments
        SET body = %s, updated_at = NOW()
        WHERE id = %s
        """,
        data.body,
        comment_id,
    )

    comment = await db_query_one(
        """
        SELECT id, post_id, author_id, author_name, body, created_at, updated_at
        FROM comments
        WHERE id = %s
        """,
        comment_id,
    )

    await manager.broadcast({
        "type": "update_comment",
        "comment": comment,
    })

    return comment


@router.delete("/comments/{comment_id}")
async def delete_comment(
    comment_id: int,
    user=Depends(get_current_user),
):
    user_id = current_user_id(user)

    existing = await db_query_one(
        """
        SELECT id, author_id
        FROM comments
        WHERE id = %s
        """,
        comment_id,
    )

    if not existing:
        raise HTTPException(status_code=404, detail="Comment not found")

    if int(existing["author_id"]) != user_id:
        raise HTTPException(status_code=403, detail="Not your comment")

    await db_execute(
        """
        DELETE FROM comments
        WHERE id = %s
        """,
        comment_id,
    )

    await manager.broadcast({
        "type": "delete_comment",
        "comment_id": comment_id,
    })

    return {"ok": True}
