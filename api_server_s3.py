from flask import Flask, request, jsonify
import os
import datetime
import json
import random
import boto3 # ★追加: boto3 をインポート
import io    # ★追加: io モジュールをインポート

app = Flask(__name__)

# ★★★ ここをあなたのS3バケット名に置き換えてください ★★★
S3_BUCKET_NAME = 'kic2025bucket' # 例: 'kic2025-chat-messages-student01'
S3_OBJECT_KEY = 'student00/messages.jsonl' # S3に保存されるファイル名
MAX_MESSAGES = 50

# S3クライアントの初期化
s3_client = boto3.client('s3')

# --- (既存の /messages POST エンドポイントをS3対応に) ---
@app.route('/messages', methods=['POST'])
def post_message():
    """
    メッセージと投稿者を受け取り、日時を加えてS3に記録するAPI
    """
    data = request.get_json()
    
    if not data or 'author' not in data or 'message' not in data:
        return jsonify({'status': 'error', 'message': 'Invalid input. "author" and "message" are required.'}), 400

    author = data['author']
    message_text = data['message']
    timestamp = datetime.datetime.now().isoformat()

    new_message = {
        'author': author,
        'message': message_text,
        'timestamp': timestamp
    }

    try:
        # S3から既存のメッセージを読み込み
        all_messages = []
        try:
            response = s3_client.get_object(Bucket=S3_BUCKET_NAME, Key=S3_OBJECT_KEY)
            # S3から読み込むストリームはBytesIO
            body = response['Body'].read().decode('utf-8')
            for line in body.splitlines():
                if line.strip(): # 空行をスキップ
                    all_messages.append(json.loads(line))
        except s3_client.exceptions.NoSuchKey:
            # ファイルがまだ存在しない場合は初回なので無視
            pass
        except Exception as e:
            # 読み込み失敗しても、新しいメッセージの追加は試みる（ログに警告）
            app.logger.warning(f"Failed to read existing messages from S3 (may not exist yet): {e}")

        # 新しいメッセージを追加
        all_messages.append(new_message)

        # ファイルに書き込む形式に変換（メモリ上で一時的にファイルのように扱う）
        output = io.StringIO()
        for msg_item in all_messages:
            output.write(json.dumps(msg_item, ensure_ascii=False) + '\n')
        
        # S3にアップロード
        s3_client.put_object(
            Bucket=S3_BUCKET_NAME,
            Key=S3_OBJECT_KEY,
            Body=output.getvalue(), # StringIOの内容を取得
            ContentType='application/jsonl' # JSON LinesのMIMEタイプ
        )
        
        return jsonify({'status': 'success', 'message': 'Message posted successfully to S3.'}), 200
    except Exception as e:
        app.logger.error(f"Failed to write message to S3: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

# --- (既存の /messages GET エンドポイントをS3対応に) ---
@app.route('/messages', methods=['GET'])
def get_messages():
    """
    S3からメッセージを読み込み、最新50件を返すAPI
    """
    messages = []
    try:
        response = s3_client.get_object(Bucket=S3_BUCKET_NAME, Key=S3_OBJECT_KEY)
        body = response['Body'].read().decode('utf-8')
        all_lines = body.splitlines()
        
        # ファイルの末尾から読み込む（最新のメッセージから）
        for line in reversed(all_lines):
            if line.strip():
                messages.append(json.loads(line))
            if len(messages) >= MAX_MESSAGES:
                break
        
        messages.reverse() # 読み込んだのが逆順なので、ここでさらに逆順に戻す
        
        return jsonify({'status': 'success', 'messages': messages}), 200
    except s3_client.exceptions.NoSuchKey:
        # ファイルが存在しない場合、メッセージはまだない
        return jsonify({'status': 'success', 'messages': []}), 200
    except Exception as e:
        app.logger.error(f"Failed to read messages from S3: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

