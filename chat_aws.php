<!DOCTYPE html>
<html>
<head>
    <title>チャットサンプル on AWS</title> <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; }
        .chat-container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .message-form { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .message-form input[type="text"], .message-form textarea { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .message-form button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .message-form button:hover { background-color: #0056b3; }
        .messages-list { border-top: 1px solid #eee; padding-top: 20px; }
        .message-item { background-color: #e9e9e9; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .message-item strong { color: #333; }
        .message-item span.timestamp { font-size: 0.8em; color: #666; float: right; margin-left: 10px;}
        .message-item span.sentiment { font-size: 0.8em; color: #007bff; font-weight: bold; margin-left: 10px;}
        .message-item span.translation { font-size: 0.9em; color: #555; margin-top: 5px; display: block;}
        .error { color: red; }

        .message-item button.play-voice {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2em;
            margin-left: 5px;
            color: #007bff;
        }
        .message-item button.play-voice:hover {
            color: #0056b3;
        }

    </style>
</head>
<body>
    <div class="chat-container">
        <h1>チャットサンプル on AWS</h1> <div id="statusMessage" style="margin-bottom: 10px;"></div> 

        <h2>メッセージ投稿</h2>
メッセージは書き込み後にCTRL+ENTERでも投稿可能です
        <form class="message-form" id="messageForm" action="" method="post"> <input type="text" id="authorInput" name="author" placeholder="投稿者名" required> <textarea id="messageTextInput" name="message_text" rows="3" placeholder="メッセージを入力" required></textarea> <button type="submit">投稿</button>
        </form>

        <h2>最新メッセージ</h2>
        <div id="messagesList" class="messages-list"> <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg_item): ?>
                    <div class="message-item">
                        <strong><?php echo htmlspecialchars($msg_item['author']); ?></strong>: 
                        <?php echo nl2br(htmlspecialchars($msg_item['message'])); ?>
                        <span class="sentiment">(感情: <?php echo htmlspecialchars($msg_item['sentiment'] ?? 'N/A'); ?>)</span>
                        <span class="timestamp"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($msg_item['timestamp']))); ?></span>
                        <span class="translation">英訳: <?php echo htmlspecialchars($msg_item['englishMessage'] ?? 'N/A'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>まだメッセージはありません。</p>
            <?php endif; ?>
        </div>

        <hr style="margin-top: 30px; margin-bottom: 30px;">

        <h2>メッセージ検索</h2>
        <form class="message-form" onsubmit="event.preventDefault(); searchMessages();">
            <input type="text" id="searchAuthor" placeholder="投稿者名で検索 (任意)">
            <input type="text" id="searchKeyword" placeholder="キーワードで検索 (任意)">
            <button type="submit">検索</button>
        </form>

        <div id="searchResults" class="messages-list">
            </div>
    </div>

    <script>
    // ★★★ ここに生成されたLambda Function URLのベースURLを貼り付けてください ★★★
    const baseLambdaUrl = 'XXXXXXXXXXXXXXXX'; // ★指定されたURLを記述★

    // Lambda Function URLの末尾にスラッシュがない場合を考慮
    // (既にURLがスラッシュで終わっているので、この処理は実質何もしないが安全のため残す)
    const messagesApiEndpoint = baseLambdaUrl.endsWith('/') ? baseLambdaUrl + 'messages' : baseLambdaUrl + '/messages';
    const searchApiEndpoint = baseLambdaUrl.endsWith('/') ? baseLambdaUrl + 'messages/search' : baseLambdaUrl + '/messages/search';
    const synthesizeSpeechApiEndpoint = 'XXXXXXXXXXXXX'; // ★SynthesizeSpeechFunctionのFunction URLをここに貼り付け★



    // HTML要素の参照をDOMContentLoadedの外に出してグローバルアクセス可能にする
    const authorInput = document.getElementById('authorInput');
    const messageTextInput = document.getElementById('messageTextInput');
    const messageForm = document.getElementById('messageForm');
    const messagesListDiv = document.getElementById('messagesList');
    const searchResultsDiv = document.getElementById('searchResults');
    const statusDiv = document.getElementById('statusMessage'); // ステータスメッセージ表示用要素


    // HTMLエスケープ関数 (セキュリティのため)
    function escapeHtml(text) {
        if (text === null || typeof text === 'undefined') {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ステータスメッセージ表示関数
    function showStatusMessage(message, type = 'success') {
        statusDiv.textContent = message;
        statusDiv.style.color = type === 'error' ? 'red' : 'green';
        statusDiv.style.fontWeight = 'bold';
    }

    // 最新メッセージを読み込む関数
    async function fetchMessages() {
        messagesListDiv.innerHTML = '<p>メッセージを読み込み中...</p>'; // ローディング表示

        try {
            const response = await fetch(messagesApiEndpoint);
            const data = await response.json();
            
            if (data.status === 'success' && data.messages) {
                // ★新しいメッセージを上に表示するために配列を逆順にする★
                const sortedMessages = data.messages.reverse(); 

                if (sortedMessages.length === 0) {
                    messagesListDiv.innerHTML = '<p>まだメッセージはありません。</p>';
                } else {
                    let html = '';
                    sortedMessages.forEach(item => {
                        const displayTimestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A';

                        html += `<div class="message-item">`;
                        html += `<strong>${escapeHtml(item.author)}</strong>: ${escapeHtml(item.message)}`;
                        // 日本語メッセージの再生ボタン
                        html += ` <button class="play-voice" data-text="${escapeHtml(item.message)}" data-lang="ja-JP">🔊</button>`; 
                        html += ` <span class="sentiment">(感情: ${escapeHtml(item.sentiment || 'N/A')})</span>`;
                        html += ` <span class="timestamp">${displayTimestamp}</span>`;
                        html += `<span class="translation">英訳: ${escapeHtml(item.englishMessage || 'N/A')}`;
                        // 英語翻訳の再生ボタン
                        html += ` <button class="play-voice" data-text="${escapeHtml(item.englishMessage || '')}" data-lang="en-US">🔊</button></span>`;
                        html += `</div>`; // message-item の閉じタグ

                    });
                    messagesListDiv.innerHTML = html;

                    messagesListDiv.querySelectorAll('.play-voice').forEach(button => {
                        button.addEventListener('click', async () => {
                            const text = button.dataset.text;
                            const lang = button.dataset.lang;
                            if (text) {
                                showStatusMessage(`音声を再生中...`, 'success');
                                button.disabled = true; // 再生中はボタンを無効化
                                try {
                                    const audioBase64 = await synthesizeSpeech(text, lang);
                                    if (audioBase64) {
                                        const audio = new Audio(`data:audio/mp3;base64,${audioBase64}`);
                                        audio.play();
                                        audio.onended = () => {
                                            button.disabled = false; // 再生終了後有効化
                                            showStatusMessage('', 'success'); // ステータスメッセージをクリア
                                        };
                                        audio.onerror = () => {
                                            button.disabled = false;
                                            showStatusMessage('音声再生エラー', 'error');
                                        };
                                    } else {
                                        button.disabled = false;
                                        showStatusMessage('音声データ取得失敗', 'error');
                                    }
                                } catch (error) {
                                    button.disabled = false;
                                    showStatusMessage(`音声合成リクエスト失敗: ${escapeHtml(error.message)}`, 'error');
                                }
                            }
                        });
                    });
        
                }
            } else {
                showStatusMessage(`メッセージ取得エラー: ${escapeHtml(data.message || '不明なエラー')}`, 'error');
                messagesListDiv.innerHTML = '<p class="error">メッセージの読み込みに失敗しました。</p>';
            }
        } catch (error) {
            showStatusMessage(`メッセージ取得リクエスト失敗: ${escapeHtml(error.message)}`, 'error');
            messagesListDiv.innerHTML = '<p class="error">メッセージの読み込みに失敗しました。</p>';
        }
    }

    // メッセージ投稿フォームのイベントハンドラ
    messageForm.addEventListener('submit', async function(event) {
        event.preventDefault(); // フォームのデフォルト送信（ページリロード）を防ぐ

        const author = authorInput.value;
        const messageText = messageTextInput.value;

        if (!author || !messageText) {
            showStatusMessage("投稿者名とメッセージの両方を入力してください。", 'error');
            return;
        }

        showStatusMessage("メッセージを投稿中...", 'success');

        try {
            const response = await fetch(messagesApiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ author: author, message: messageText })
            });
            const data = await response.json();

            if (data.status === 'success') {
                showStatusMessage("メッセージが正常に投稿されました！", 'success');
                // ★投稿者名をlocalStorageに保存★
                localStorage.setItem('chatAuthorName', author);
                messageTextInput.value = ''; // メッセージ入力欄をクリア
                messageTextInput.focus(); // ★メッセージ入力欄にフォーカス★
                fetchMessages(); // 最新メッセージを再読み込み
            } else {
                showStatusMessage(`メッセージ投稿エラー: ${escapeHtml(data.message || '不明なエラー')}`, 'error');
            }
        } catch (error) {
            showStatusMessage(`メッセージ投稿リクエスト失敗: ${escapeHtml(error.message)}`, 'error');
        }
    });

    // ★メッセージ入力欄でCtrl/Cmd + Enterで投稿★
    messageTextInput.addEventListener('keydown', function(event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault(); // デフォルトの改行を防ぐ
            messageForm.dispatchEvent(new Event('submit')); // フォームのsubmitイベントを発火
        }
    });


    // 検索機能 (既存のまま)
    async function searchMessages() {
        const searchAuthor = document.getElementById('searchAuthor').value;
        const searchKeyword = document.getElementById('searchKeyword').value;
        
        let queryParams = [];
        if (searchAuthor) {
            queryParams.push(`author=${encodeURIComponent(searchAuthor)}`);
        }
        if (searchKeyword) {
            queryParams.push(`keyword=${encodeURIComponent(searchKeyword)}`);
        }

        const fullSearchUrl = searchApiUrl + (queryParams.length > 0 ? `?${queryParams.join('&')}` : '');
        
        searchResultsDiv.innerHTML = '<p>検索中...</p>';

        try {
            const response = await fetch(fullSearchUrl);
            const data = await response.json();
            
            if (data.status === 'success' && data.results) {
                searchResultsDiv.innerHTML = '<h3>検索結果:</h3>';
                if (data.results.length === 0) {
                    searchResultsDiv.innerHTML += '<p>一致するメッセージは見つかりませんでした。</p>';
                } else {
                    let html = '';
                    data.results.forEach(item => {
                        const displayTimestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A';
                        html += `<div class="message-item"><strong>${escapeHtml(item.author)}</strong>: ${escapeHtml(item.message)}`;
                        html += ` <span class="sentiment">(感情: ${escapeHtml(item.sentiment || 'N/A')})</span>`;
                        html += ` <span class="timestamp">${displayTimestamp}</span>`;
                        html += `<span class="translation">英訳: ${escapeHtml(item.englishMessage || 'N/A')}</span></div>`;
                    });
                    searchResultsDiv.innerHTML += html;
                }
            } else {
                showStatusMessage(`検索エラー: ${escapeHtml(data.message || '不明なエラー')}`, 'error');
                searchResultsDiv.innerHTML = `<p class="error">検索エラー: ${escapeHtml(data.message || '不明なエラー')}</p>`;
            }
        } catch (error) {
            showStatusMessage(`検索リクエスト失敗: ${escapeHtml(error.message)}`, 'error');
            searchResultsDiv.innerHTML = `<p class="error">検索リクエスト失敗: ${escapeHtml(error.message)}</p>`;
        }
    }

    // ページロード時の初期処理
    document.addEventListener('DOMContentLoaded', function() {
        // ★投稿者名をlocalStorageから読み込み、フォームに設定★
        const storedAuthor = localStorage.getItem('chatAuthorName');
        if (storedAuthor) {
            authorInput.value = storedAuthor;
            // ★投稿者名が入力されていたら、メッセージ入力欄にフォーカス★
            messageTextInput.focus();
        } else {
            // 投稿者名がなければ、投稿者名入力欄にフォーカス
            authorInput.focus();
        }
        
        fetchMessages(); // 最新メッセージを読み込む
    });

    // 音声合成Lambdaを呼び出す関数
    async function synthesizeSpeech(text, languageCode) {
        if (!text) {
            return null;
        }
        try {
            const response = await fetch(synthesizeSpeechApiEndpoint, {
                method: 'POST', // POSTでテキストを送る
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ text: text, language: languageCode })
            });
            const data = await response.json();
   
            if (response.status === 200 && data.audio_base64) {
                return data.audio_base64;
            } else {
                // エラーログも、data.error や data.message がない可能性があるので、
                // response.status や response.statusText も表示するとより丁寧
                console.error(
                    'SynthesizeSpeech API error:', 
                    `Status: ${response.status} ${response.statusText}`, // HTTPステータスコードとテキスト
                    `Body: ${JSON.stringify(data)}` // レスポンスボディ全体
                );
                return null;
            }
        } catch (error) {
            console.error('SynthesizeSpeech API request failed:', error);
            throw error;
        }
    }
    
    </script>
</body>
</html>

