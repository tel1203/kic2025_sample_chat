<!DOCTYPE html>
<html>
<head>
    <title>簡易チャットシステム</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; }
        .chat-container { max-width: 600px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .message-form { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .message-form input[type="text"], .message-form textarea { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .message-form button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .message-form button:hover { background-color: #0056b3; }
        .messages-list { border-top: 1px solid #eee; padding-top: 20px; }
        .message-item { background-color: #e9e9e9; padding: 10px; border-radius: 6px; margin-bottom: 10px; }
        .message-item strong { color: #333; }
        .message-item span.timestamp { font-size: 0.8em; color: #666; float: right; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="chat-container">
        <h1>簡易チャットシステム</h1>

        <?php
        // ★★★ ここにAPIサーバーのプライベートIPアドレスを設定してください ★★★
        $apiServerIp = '18.183.186.11'; // 例: '172.31.X.Y'
        $messagesApiEndpoint = "http://$apiServerIp:5000/messages"; // メッセージ投稿/読み出しAPI
        $searchApiEndpoint = "http://$apiServerIp:5000/messages/search"; // 検索API

        $errorMessage = '';
        $successMessage = '';

        // 新しいメッセージを送信 (POSTリクエスト)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['author']) && isset($_POST['message_text'])) {
            $author = trim($_POST['author']);
            $message_text = trim($_POST['message_text']);

            if (empty($author) || empty($message_text)) {
                $errorMessage = "投稿者名とメッセージの両方を入力してください。";
            } else {
                $postData = json_encode([
                    'author' => $author,
                    'message' => $message_text
                ]);

                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $messagesApiEndpoint);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // タイムアウト設定 (5秒)
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($response === false) {
                        $errorMessage = "メッセージ送信に失敗しました: " . htmlspecialchars($curlError);
                    } elseif ($httpCode !== 200) {
                        $errorMessage = "APIサーバーがエラーを返しました (HTTPコード: " . $httpCode . "): " . htmlspecialchars($response);
                    } else {
                        $data = json_decode($response, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($data['status']) && $data['status'] === 'success') {
                            $successMessage = "メッセージが送信されました！";
                            // 送信成功後、ページをリロードして最新メッセージを再表示
                            echo '<meta http-equiv="refresh" content="0">'; 
                        } else {
                            $errorMessage = "APIサーバーからのレスポンスを解析できませんでした: " . htmlspecialchars($response);
                        }
                    }
                } else {
                    $errorMessage = "cURL拡張がインストールされていません。メッセージ送信にはcURLが必要です。";
                }
            }
        }

        // GETリクエストで最新メッセージを取得
        $messages = [];
        if (empty($errorMessage)) { // 送信エラーがなければ取得を試みる
            if (function_exists('curl_init')) {
                $ch_get = curl_init();
                curl_setopt($ch_get, CURLOPT_URL, $messagesApiEndpoint);
                curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_get, CURLOPT_TIMEOUT, 5);
                
                $response_get = curl_exec($ch_get);
                $httpCode_get = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
                $curlError_get = curl_error($ch_get);
                curl_close($ch_get);

                if ($response_get === false) {
                    $errorMessage = "メッセージ取得に失敗しました: " . htmlspecialchars($curlError_get);
                } elseif ($httpCode_get !== 200) {
                    $errorMessage = "APIサーバーからのメッセージ取得中にエラーが発生しました (HTTPコード: " . $httpCode_get . "): " . htmlspecialchars($response_get);
                } else {
                    $data_get = json_decode($response_get, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data_get['status']) && $data_get['status'] === 'success' && isset($data_get['messages'])) {
                        $messages = $data_get['messages'];
                    } else {
                        $errorMessage = "APIサーバーからのメッセージレスポンスを解析できませんでした: " . htmlspecialchars($response_get);
                    }
                }
            } else {
                $errorMessage = "cURL拡張がインストールされていません。メッセージ取得にはcURLが必要です。";
            }
        }
        ?>

        <?php if (!empty($errorMessage)): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <p style="color: green;"><?php echo $successMessage; ?></p>
        <?php endif; ?>

        <h2>メッセージ投稿</h2>
        <form class="message-form" action="" method="post">
            <input type="text" name="author" placeholder="投稿者名" required>
            <textarea name="message_text" rows="3" placeholder="メッセージを入力" required></textarea>
            <button type="submit">投稿</button>
        </form>

        <h2>最新メッセージ</h2>
        <div class="messages-list">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg_item): ?>
                    <div class="message-item">
                        <strong><?php echo htmlspecialchars($msg_item['author']); ?></strong>: 
                        <?php echo nl2br(htmlspecialchars($msg_item['message'])); ?>
                        <span class="timestamp"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($msg_item['timestamp']))); ?></span>
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
    // APIサーバーのURLをPHPからJavaScriptに渡す
    const apiBaseUrl = "<?php echo $messagesApiEndpoint; ?>"; // /messages まで含める
    const searchApiUrl = "<?php echo $searchApiEndpoint; ?>";

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
        
        const searchResultsDiv = document.getElementById('searchResults');
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
                        // DynamoDBから取得したtimestampは文字列のままなので、Dateオブジェクトに変換してローカライズ表示
                        const displayTimestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A';
                        html += `<div class="message-item"><strong>${escapeHtml(item.author)}</strong>: ${escapeHtml(item.message)} <span class="timestamp">${displayTimestamp}</span></div>`;
                    });
                    searchResultsDiv.innerHTML += html;
                }
            } else {
                searchResultsDiv.innerHTML = `<p class="error">検索エラー: ${escapeHtml(data.message || '不明なエラー')}</p>`;
            }
        } catch (error) {
            searchResultsDiv.innerHTML = `<p class="error">検索リクエスト失敗: ${escapeHtml(error.message)}</p>`;
        }
    }
    </script>
</body>
</html>

