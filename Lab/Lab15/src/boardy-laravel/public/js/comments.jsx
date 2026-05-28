import { authedFetch, getAccessToken, startLogin } from './auth.js';

const API_BASE = '/api';

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function CommentsApp({ postId, userName }) {
    const [comments, setComments] = React.useState([]);
    const [body, setBody] = React.useState('');
    const [status, setStatus] = React.useState('');

    React.useEffect(() => {
        loadComments();
        connectWs();
    }, []);

    async function loadComments() {
        const response = await fetch(`${API_BASE}/api/posts/${postId}/comments`);
        const data = await response.json();

        setComments(data.comments || []);
    }

    function connectWs() {
        const ws = new WebSocket(`${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.host}/ws`);

        ws.onopen = () => {
            console.log('comments ws connected');
        };

        ws.onmessage = (event) => {
            const message = JSON.parse(event.data);

            if (message.type === 'new_comment' && message.comment?.post_id === postId) {
                setComments((current) => {
                    if (current.some((item) => item.id === message.comment.id)) {
                        return current;
                    }

                    return [...current, message.comment];
                });
            }

            if (message.type === 'update_comment' && message.comment?.post_id === postId) {
                setComments((current) => current.map((item) => {
                    return item.id === message.comment.id ? message.comment : item;
                }));
            }

            if (message.type === 'delete_comment') {
                setComments((current) => current.filter((item) => {
                    return item.id !== message.comment_id;
                }));
            }

            if (message.type === 'user_renamed') {
                setComments((current) => current.map((item) => {
                    if (Number(item.author_id) === Number(message.user_id)) {
                        return {
                            ...item,
                            author_name: message.new_name,
                        };
                    }

                    return item;
                }));
            }
        };

        ws.onclose = () => {
            setTimeout(connectWs, 3000);
        };
    }

    async function submitComment(event) {
        event.preventDefault();

        if (!getAccessToken()) {
            await startLogin();
            return;
        }

        const response = await authedFetch(`${API_BASE}/api/posts/${postId}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                body,
                author_name: userName,
            }),
        });

        if (!response) {
            return;
        }

        if (!response.ok) {
            setStatus('Ошибка создания комментария');
            return;
        }

        setBody('');
        setStatus('Комментарий создан');
    }

    async function updateComment(comment) {
        const newBody = prompt('Новый текст комментария', comment.body);

        if (!newBody) {
            return;
        }

        const response = await authedFetch(`${API_BASE}/api/comments/${comment.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                body: newBody,
            }),
        });

        if (!response || !response.ok) {
            setStatus('Нельзя изменить чужой комментарий или токен недействителен');
            return;
        }

        setStatus('Комментарий обновлён');
    }

    async function deleteComment(comment) {
        if (!confirm('Удалить комментарий?')) {
            return;
        }

        const response = await authedFetch(`${API_BASE}/api/comments/${comment.id}`, {
            method: 'DELETE',
        });

        if (!response || !response.ok) {
            setStatus('Нельзя удалить чужой комментарий или токен недействителен');
            return;
        }

        setStatus('Комментарий удалён');
    }

    return React.createElement(
        'div',
        null,
        React.createElement('h2', null, 'Комментарии'),

        status
            ? React.createElement('div', {className: 'alert alert-info'}, status)
            : null,

        React.createElement(
            'div',
            {className: 'mb-3'},
            comments.length === 0
                ? React.createElement('div', {className: 'alert alert-secondary'}, 'Комментариев пока нет.')
                : comments.map((comment) => {
                    return React.createElement(
                        'div',
                        {className: 'card mb-2', key: comment.id, id: `comment-${comment.id}`},
                        React.createElement(
                            'div',
                            {className: 'card-body'},
                            React.createElement(
                                'div',
                                {className: 'text-muted small mb-2'},
                                `${comment.author_name} · ${comment.created_at || ''}`
                            ),
                            React.createElement('p', {
                                dangerouslySetInnerHTML: {
                                    __html: escapeHtml(comment.body),
                                },
                            }),
                            React.createElement(
                                'button',
                                {
                                    className: 'btn btn-sm btn-outline-primary me-2',
                                    onClick: () => updateComment(comment),
                                },
                                'Редактировать'
                            ),
                            React.createElement(
                                'button',
                                {
                                    className: 'btn btn-sm btn-outline-danger',
                                    onClick: () => deleteComment(comment),
                                },
                                'Удалить'
                            )
                        )
                    );
                })
        ),

        React.createElement(
            'form',
            {onSubmit: submitComment},
            React.createElement('textarea', {
                className: 'form-control mb-2',
                placeholder: 'Введите комментарий',
                value: body,
                onChange: (event) => setBody(event.target.value),
            }),
            React.createElement(
                'button',
                {className: 'btn btn-success'},
                'Отправить'
            )
        )
    );
}

const rootElement = document.getElementById('comments-root');

if (rootElement) {
    const postId = Number(rootElement.dataset.postId);
    const userName = rootElement.dataset.userName || 'Guest';

    ReactDOM.createRoot(rootElement).render(
        React.createElement(CommentsApp, {
            postId,
            userName,
        })
    );
}
