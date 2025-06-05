from flask import Flask, request, jsonify
import os
import datetime
import json
import random
import boto3
import io
import uuid # ★追加: UUIDを生成するため
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# S3関連の設定
# S3_BUCKET_NAME = 'kic2025-chat-all-students-messages'
# STUDENT_ID = os.environ.get('STUDENT_ID', 'default')
# S3_OBJECT_KEY = f'student{STUDENT_ID}/messages.jsonl'
# s3_client = boto3.client('s3')

# --- DynamoDB関連の設定 ---
DYNAMODB_TABLE_NAME = 'ChatMessages'
DYNAMODB_REGION = os.environ.get('AWS_REGION', 'ap-northeast-1') 

dynamodb = boto3.resource('dynamodb', region_name=DYNAMODB_REGION)
table = dynamodb.Table(DYNAMODB_TABLE_NAME)

MAX_MESSAGES = 50
CHAT_ROOM_ID = 'main_chat' # 固定のパーティションキー

# ... (既存の /omikuji GET エンドポイントは変更なし) ...

@app.route('/messages', methods=['POST'])
def post_message():
    """
    メッセージと投稿者を受け取り、日時を加えてDynamoDBに記録するAPI
    """
    data = request.get_json()
    
    if not data or 'author' not in data or 'message' not in data:
        return jsonify({'status': 'error', 'message': 'Invalid input. "author" and "message" are required.'}), 400

    author = data['author']
    message_text = data['message']
    timestamp = datetime.datetime.now().isoformat()
    message_id = str(uuid.uuid4()) # ユニークなメッセージIDを生成

    try:
        table.put_item(
            Item={
                'chatRoomId': CHAT_ROOM_ID,
                'timestamp': timestamp,
                'messageId': message_id,
                'author': author,
                'message': message_text
            }
        )
        return jsonify({'status': 'success', 'message': 'Message posted successfully to DynamoDB.'}), 200
    except Exception as e:
        app.logger.error(f"Failed to write message to DynamoDB: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

@app.route('/messages', methods=['GET'])
def get_messages():
    """
    DynamoDBからメッセージを読み込み、最新50件を返すAPI
    """
    messages = []
    try:
        # Queryを使って、特定のchatRoomIdのアイテムをtimestampの降順で取得
        # SortKeyの降順 (latest first) で、Limit=50
        response = table.query(
            KeyConditionExpression=boto3.dynamodb.conditions.Key('chatRoomId').eq(CHAT_ROOM_ID),
            Limit=MAX_MESSAGES,
            ScanIndexForward=False # 最新のメッセージから取得（降順）
        )
        # DynamoDBのQuery結果は降順なので、表示用に昇順に戻す
        messages_from_db = response['Items']
        messages = sorted(messages_from_db, key=lambda x: x['timestamp'])

        return jsonify({'status': 'success', 'messages': messages}), 200
    except Exception as e:
        app.logger.error(f"Failed to read messages from DynamoDB: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500

@app.route('/messages/search', methods=['GET'])
def search_messages():
    """
    メッセージを検索するAPI (DynamoDBのScanで簡易的に実装)
    GETパラメータ: author (投稿者名), keyword (メッセージ内のキーワード)
    """
    author_query = request.args.get('author')
    keyword_query = request.args.get('keyword')
    
    filter_expression = None
    expression_attribute_values = {}
    
    if author_query:
        # `author`はPartitionKeyではないため、ScanでFilterExpressionを使用
        # または、GSI (Global Secondary Index) をauthorに作成するとQueryが使える
        # 今回はScanで簡易的にデモ
        filter_expression = boto3.dynamodb.conditions.Attr('author').eq(author_query)
        
    if keyword_query:
        if filter_expression:
            filter_expression = filter_expression & boto3.dynamodb.conditions.Attr('message').contains(keyword_query)
        else:
            filter_expression = boto3.dynamodb.conditions.Attr('message').contains(keyword_query)

    try:
        if filter_expression:
            response = table.scan(
                FilterExpression=filter_expression
            )
        else:
            # 検索条件がない場合は全件スキャン（非効率なので注意）
            response = table.scan()
            
        search_results = sorted(response['Items'], key=lambda x: x['timestamp'])
        
        return jsonify({'status': 'success', 'results': search_results}), 200
    except Exception as e:
        app.logger.error(f"Failed to search messages in DynamoDB: {e}")
        return jsonify({'status': 'error', 'message': f'Server error: {e}'}), 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

