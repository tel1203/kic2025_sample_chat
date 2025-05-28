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
        $apiServerIp = '127.0.0.1'; // 例: '172.31.X.Y'
        $apiEndpoint = "http://$apiServerIp:5000/messages"; // APIサーバーのポートが5000なので注意

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
                    curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
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
                curl_setopt($ch_get, CURLOPT_URL, $apiEndpoint);
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
    </div>
</body>
</html>

