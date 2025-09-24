function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getCSRFToken() {
    return document.getElementById('csrf-token').value;
}

document.getElementById('form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const url = document.getElementById('url').value;
    const resultDiv = document.getElementById('result');

    if (!url || url.length > 2048) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div style="color: red;">❌ Please enter a valid URL (max 2048 characters)</div>';
        return;
    }

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                url: url,
                csrf_token: getCSRFToken()
            })
        });

        const data = await response.json();

        if (data.success) {
            const saved = data.saved;
            const shortUrl = escapeHtml(data.short_url);
            const originalUrl = escapeHtml(data.original_url);

            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div class="short-url">
                    <a href="${shortUrl}" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;">
                        ${shortUrl}
                    </a>
                </div>
                <button class="copy-btn" onclick="copy('${data.short_url}')">
                    📋 Copy Short URL
                </button>

                <div class="stats">
                    <div>
                        <div class="stat-value">${data.original_url.length}</div>
                        <div class="stat-label">Original</div>
                    </div>
                    <div>
                        <div class="stat-value">${data.short_url.length}</div>
                        <div class="stat-label">Shortened</div>
                    </div>
                    <div>
                        <div class="stat-value">${saved > 0 ? '+' + saved : saved}</div>
                        <div class="stat-label">Saved</div>
                    </div>
                </div>

                ${saved > 0 ? `
                    <div class="success">
                        ✅ Saved ${saved} characters (${Math.round(saved/data.original_url.length*100)}% shorter)
                    </div>
                ` : ''}

                <div class="session-info">
                    📊 ${data.total_in_session} URLs stored in current session<br>
                    🔗 This link works across browsers and expires in 1 hour
                </div>
            `;
            document.getElementById('url').value = '';

            if (data.total_in_session === 1) {
                const clearBtnContainer = document.getElementById('clear-btn-container');
                if (clearBtnContainer) {
                    clearBtnContainer.style.display = 'block';
                    clearBtnContainer.innerHTML = `<button class="clear-btn" onclick="clearSession()">Clear All (1 URLs in session)</button>`;
                }
            }
        } else {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<div style="color: red;">❌ ${escapeHtml(data.error || 'Unknown error occurred')}</div>`;
        }
    } catch (error) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `<div style="color: red;">❌ Network error occurred. Please try again.</div>`;
    }
});

function copy(text) {
    navigator.clipboard.writeText(text).then(() => {
        event.target.textContent = '✅ Copied!';
        setTimeout(() => event.target.textContent = '📋 Copy Short URL', 2000);
    }).catch(() => {
        const input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        event.target.textContent = '✅ Copied!';
        setTimeout(() => event.target.textContent = '📋 Copy Short URL', 2000);
    });
}

function clearSession() {
    if (confirm('Clear all shortened URLs from this session?')) {
        const csrfToken = getCSRFToken();
        window.location.href = `api.php?clear=1&csrf_token=${encodeURIComponent(csrfToken)}`;
    }
}