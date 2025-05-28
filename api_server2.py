from flask import Flask, request, jsonify
import os
import datetime
import json
import random

app = Flask(__name__)

# メッセージを保存するファイルパス
MESSAGES_FILE = 'messages.jsonl' # JSON Lines形式

# 最新メッセージの件数
MAX_MESSAGES = 50

@app.route('/messages', methods=['POST'])
def post_message():
    """
    メッセージと投稿者を受け取り、日時を加えてファイルに記録するAPI
    """
    data = request.get_json()
    
    # 入力値の検証
    if not data or 'author' not in data or 'message' not in data:
        return jsonify({'status': 'error', 'message': 'Invalid input. "author" and "message" are required.'}), 400

    author = data['author']
    message_text = data['message']
    timestamp = datetime.datetime.now().isoformat() # ISOフォーマットで日時を取得

    new_message = {
        'author': author,
        'message': message_text,
        'timestamp': timestamp
    }

    try:
        # ファイルに追記 (JSON Lines形式)
        with open(MESSAGES_FILE, 'a', encoding='utf-8') as f:
            f.write(json.dumps(new_message, ensure_ascii=False) + '\n')
        
        return jsonify({'status': 'success', 'message': 'Message posted successfully.'}), 200
    except Exception as e:
        app.logger.error(f"Failed to write message to file: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

@app.route('/messages', methods=['GET'])
def get_messages():
    """
    ファイルからメッセージを読み込み、最新50件を返すAPI
    """
    messages = []
    if os.path.exists(MESSAGES_FILE):
        try:
            with open(MESSAGES_FILE, 'r', encoding='utf-8') as f:
                # ファイルの末尾から読み込む（最新のメッセージから）
                # 大量のメッセージがある場合、効率的ではないがプロトタイプ用
                all_lines = f.readlines()
                # 逆順にしてから最新N件を取得
                for line in reversed(all_lines):
                    if line.strip(): # 空行をスキップ
                        messages.append(json.loads(line))
                    if len(messages) >= MAX_MESSAGES:
                        break
        except Exception as e:
            app.logger.error(f"Failed to read messages from file: {e}")
            return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

    # 日時でソートし直す (古い順にする場合、reverse=False)
    # 読み込んだのが逆順なので、ここでさらに逆順に戻す
    messages.reverse()
    
    return jsonify({'status': 'success', 'messages': messages}), 200

@app.route('/omikuji', methods=['GET'])
def omikuji():
    """
    アクセスするたびにおみくじの結果（大吉、吉、小吉、凶）を返すAPI
    """
    fortunes = ["大吉", "吉", "小吉", "凶"]
    
    # fortunes リストからランダムに1つ選択
    result = random.choice(fortunes)
    
    # シンプルなテキストとして返す
    return result, 200, {'Content-Type': 'text/plain; charset=utf-8'} # 日本語表示のためにcharset指定


if __name__ == '__main__':
    # 全てのネットワークインターフェースからのアクセスを許可 (セキュリティグループで制御)
    # 本番環境ではより堅牢なWSGIサーバー (Gunicorn, uWSGI) とNginx/Apacheを組み合わせるべきです。
    app.run(host='0.0.0.0', port=5000) # デフォルトのポートを5000にする

