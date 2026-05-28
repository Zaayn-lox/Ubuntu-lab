const API_BASE = 'https://api.belyaevubuntu.ru/api';

const root = document.getElementById('comments-root');

if (!root) {
    console.error('comments-root not found');
} else {
    const postId = root.dataset.postId;
    const currentUserName = root.dataset.userName || 'Guest';

    const e = React.createElement;

    function CommentsApp() {
        const [comments, setComments] = React.useState([]);
        const [body, setBody] = React.useState('');
        const [loading, setLoading] = React.useState(true);
        const [error, setError] = React.useState('');
        const [editingId, setEditingId] = React.useState(null);
        const [editingBody, setEditingBody] = React.useState('');

        async function loadComments() {
            setLoading(true);
            setError('');

            try {
                const response = await fetch(`${API_BASE}/posts/${postId}/comments`, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.detail || 'Failed to load comments');
                }

                setComments(data.comments || []);
            } catch (err) {
                console.error(err);
                setError('Не удалось загрузить комментарии: ' + err.message);
            } finally {
                setLoading(false);
            }
        }

        async function createComment(event) {
            event.preventDefault();

            if (!body.trim()) {
                return;
            }

            setError('');

            try {
                const response = await BoardyAuth.authedFetch(`${API_BASE}/posts/${postId}/comments`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        body: body,
                        author_name: currentUserName,
                    }),
                });

                if (!response) {
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.detail || 'Failed to create comment');
                }

                setBody('');
                await loadComments();
            } catch (err) {
                console.error(err);
                setError('Не удалось добавить комментарий: ' + err.message);
            }
        }

        async function updateComment(commentId) {
            if (!editingBody.trim()) {
                return;
            }

            setError('');

            try {
                const response = await BoardyAuth.authedFetch(`${API_BASE}/comments/${commentId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        body: editingBody,
                    }),
                });

                if (!response) {
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.detail || 'Failed to update comment');
                }

                setEditingId(null);
                setEditingBody('');
                await loadComments();
            } catch (err) {
                console.error(err);
                setError('Не удалось обновить комментарий: ' + err.message);
            }
        }

        async function deleteComment(commentId) {
            if (!confirm('Удалить комментарий?')) {
                return;
            }

            setError('');

            try {
                const response = await BoardyAuth.authedFetch(`${API_BASE}/comments/${commentId}`, {
                    method: 'DELETE',
                });

                if (!response) {
                    return;
                }

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.detail || 'Failed to delete comment');
                }

                await loadComments();
            } catch (err) {
                console.error(err);
                setError('Не удалось удалить комментарий: ' + err.message);
            }
        }

        React.useEffect(() => {
            loadComments();

            const ws = new WebSocket('wss://api.belyaevubuntu.ru/ws');

            ws.onopen = () => {
                console.log('WS connected for comments');
            };

            ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);

                    if (
                        message.type === 'new_comment' ||
                        message.type === 'update_comment' ||
                        message.type === 'delete_comment' ||
                        message.type === 'user_renamed'
                    ) {
                        loadComments();
                    }
                } catch (err) {
                    console.error('Invalid WS message', err);
                }
            };

            ws.onerror = (event) => {
                console.error('WS error', event);
            };

            return () => {
                ws.close();
            };
        }, []);

        return e(
            'div',
            null,

            error
                ? e('div', { className: 'alert alert-danger' }, error)
                : null,

            loading
                ? e('div', { className: 'alert alert-light border' }, 'Загрузка комментариев...')
                : null,

            !loading && comments.length === 0
                ? e('div', { className: 'alert alert-light border' }, 'Комментариев пока нет.')
                : null,

            !loading && comments.map((comment) =>
                e(
                    'div',
                    { key: comment.id, className: 'card mb-2' },
                    e(
                        'div',
                        { className: 'card-body' },

                        e(
                            'div',
                            { className: 'fw-bold' },
                            comment.author_name || 'Unknown'
                        ),

                        e(
                            'div',
                            { className: 'text-muted small mb-2' },
                            comment.created_at || ''
                        ),

                        editingId === comment.id
                            ? e(
                                'div',
                                null,
                                e('textarea', {
                                    className: 'form-control mb-2',
                                    rows: 3,
                                    value: editingBody,
                                    onChange: (event) => setEditingBody(event.target.value),
                                }),
                                e(
                                    'button',
                                    {
                                        className: 'btn btn-sm btn-success me-2',
                                        onClick: () => updateComment(comment.id),
                                    },
                                    'Сохранить'
                                ),
                                e(
                                    'button',
                                    {
                                        className: 'btn btn-sm btn-secondary',
                                        onClick: () => {
                                            setEditingId(null);
                                            setEditingBody('');
                                        },
                                    },
                                    'Отмена'
                                )
                            )
                            : e(
                                'div',
                                null,
                                e(
                                    'p',
                                    { style: { whiteSpace: 'pre-line' } },
                                    comment.body
                                ),
                                e(
                                    'button',
                                    {
                                        className: 'btn btn-sm btn-outline-primary me-2',
                                        onClick: () => {
                                            setEditingId(comment.id);
                                            setEditingBody(comment.body);
                                        },
                                    },
                                    'Редактировать'
                                ),
                                e(
                                    'button',
                                    {
                                        className: 'btn btn-sm btn-outline-danger',
                                        onClick: () => deleteComment(comment.id),
                                    },
                                    'Удалить'
                                )
                            )
                    )
                )
            ),

            e(
                'form',
                { className: 'mt-4', onSubmit: createComment },
                e('h3', null, 'Добавить комментарий'),
                e('textarea', {
                    className: 'form-control mb-2',
                    rows: 4,
                    value: body,
                    placeholder: 'Введите комментарий...',
                    onChange: (event) => setBody(event.target.value),
                    required: true,
                }),
                e(
                    'button',
                    { className: 'btn btn-success', type: 'submit' },
                    'Отправить'
                )
            )
        );
    }

    ReactDOM.createRoot(root).render(e(CommentsApp));
}
