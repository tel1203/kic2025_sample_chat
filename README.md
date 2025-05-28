# 簡易チャットシステム (Webサーバー & APIサーバー分離)

このリポジトリは、Webアプリケーションのフロントエンド（Webサーバー）とバックエンドAPI（APIサーバー）を分離して動作させる、簡易チャットシステムのサンプルコードです。クラウド環境（AWS EC2など）での多層アプリケーション構築の基礎を学ぶことを目的としています。

## プロジェクトの構成

このシステムは以下の2つの主要なプログラムで構成されています。

-   `api_server.py`:
    * **役割**: 本システムのバックエンドWeb APIサーバーとして動作します。
    * **機能**:
        1.  投稿されたチャットメッセージ（投稿者名、メッセージ本文、投稿日時を含む）をサーバー上のローカルファイルに保存します。
        2.  これまでに投稿されたメッセージの中から、**最新50件**をリストとしてフロントエンドに返答します。
    * **技術**: Python (Flask)

-   `chat.php`:
    * **役割**: チャットシステムのフロントエンド（Webサーバー）として動作します。
    * **機能**:
        1.  ユーザーが投稿者名とメッセージを入力するためのウェブインターフェースを提供します。
        2.  `api_server.py` にアクセスしてメッセージを投稿します。
        3.  `api_server.py` から過去のメッセージのリストを読み出し、画面に表示します。
    * **技術**: PHP (cURL拡張必須)

## 想定されるサーバー構成

このシステムは、通常、以下のように2台のEC2インスタンス（または仮想サーバー）に分けて配置することを想定しています。

1.  **Webサーバー (パブリックサブネット):**
    * `chat.php` を配置。
    * インターネットからのアクセスを受け付けます。
    * Apache や Nginx などのWebサーバーソフトウェアと PHP が必要です。

2.  **APIサーバー (プライベートサブネット):**
    * `api_server.py` を配置。
    * Webサーバーからのアクセスのみを許可し、インターネットからは直接アクセスできません（セキュリティのため）。
    * Python と Flask が必要です。

## 利用準備とセットアップ手順

### 1. APIサーバーのセットアップ

APIサーバーを動作させるEC2インスタンス（または仮想サーバー）上で以下の準備を行います。

1.  **Python 3 と pip のインストール**:
    Amazon Linux 2023 を使用している場合、`dnf` コマンドで `pip3` をインストールします。
    ```bash
    sudo dnf install python3-pip -y
    ```
    (Amazon Linux 2 や Ubuntu の場合は適宜 `yum` や `apt` を使用してください)

2.  **Flask のインストール**:
    `api_server.py` が動作するために必要な Python フレームワークである Flask をインストールします。
    ```bash
    pip3 install Flask
    ```
    （もしシステム全体にインストールする場合は `sudo pip3 install Flask` とします。プロジェクトの分離のため、可能であれば [Python 仮想環境](https://docs.python.org/ja/3/library/venv.html) の利用も検討してください。）

3.  **`api_server.py` の配置**:
    `api_server.py` ファイルをサーバー上に配置します（例: `/home/ec2-user/chat_api/api_server.py`）。

4.  **APIサーバーの起動**:
    `api_server.py` を実行します。`api_server.py` はデフォルトでポート `5000` でリッスンします。
    ```bash
    python3 api_server.py
    ```
    （バックグラウンドで実行し続けるには、`nohup python3 api_server.py &` や `tmux` を利用することをお勧めします。）

5.  **セキュリティグループの設定 (重要！)**:
    APIサーバーのEC2インスタンスにアタッチされているセキュリティグループで、WebサーバーのEC2インスタンスの**プライベートIPアドレス（またはセキュリティグループID）**からの**TCPポート5000**へのインバウンドアクセスを許可してください。これにより、WebサーバーのみがAPIサーバーにアクセスできます。

### 2. Webサーバーのセットアップ

Webサーバーを動作させるEC2インスタンス（または仮想サーバー）上で以下の準備を行います。

1.  **Webサーバーソフトウェアのインストール**:
    Apache または Nginx をインストールし、起動します。
    ```bash
    # 例: Amazon Linux 2023 (Apacheの場合)
    sudo dnf install httpd -y
    sudo systemctl start httpd
    sudo systemctl enable httpd
    ```

2.  **PHP と cURL 拡張のインストール**:
    PHP と、PHPからAPIにアクセスするために必要な `cURL` 拡張機能をインストールします。
    ```bash
    # 例: Amazon Linux 2023 (PHP 8.2とcURLの場合)
    sudo dnf install php php-curl -y
    sudo systemctl restart httpd # Webサーバーを再起動してPHPの変更を反映
    ```

3.  **`chat.php` の配置**:
    `chat.php` ファイルをWebサーバーのドキュメントルート（例: Apacheなら `/var/www/html/`）に配置します。

4.  **APIサーバーのIPアドレス設定 (重要！)**:
    `chat.php` ファイル内の以下の行を編集し、`api_server.py` を設置している**APIサーバーのプライベートIPアドレス**に置き換えます。
    ```php
    // chat.php 内の該当箇所
    $apiServerIp = 'YOUR_API_SERVER_PRIVATE_IP_ADDRESS'; // 例: '172.31.X.Y'
    ```
    もしAPIサーバーが**同一のEC2インスタンス上**で動作している場合は、`127.0.0.1` を指定してください。

5.  **セキュリティグループの設定 (重要！)**:
    WebサーバーのEC2インスタンスにアタッチされているセキュリティグループで、**HTTP (TCPポート80)** と **HTTPS (TCPポート443)** のインバウンドアクセスを **`0.0.0.0/0` (インターネット全体)** から許可してください。SSH (TCPポート22) も自分のIPアドレスから許可します。

## 使い方

1.  上記の手順でWebサーバーとAPIサーバーが正しくセットアップされていることを確認します。
2.  Webブラウザを開き、WebサーバーEC2インスタンスの**パブリックIPアドレス**または関連付けられたDNS名にアクセスします。
    例: `http://<WebサーバーのパブリックIPアドレス>/chat.php`
3.  画面が表示されたら、投稿者名とメッセージを入力して「投稿」ボタンをクリックします。
4.  投稿されたメッセージと、以前のメッセージが一覧に表示されることを確認します。

## 発展的な学習 (Optional)

より深く学びたい学生は、以下のテーマに挑戦してみてください。

-   **Docker化**: `api_server.py` をDockerコンテナとして実行してみましょう。APIサーバーEC2にDockerをインストールし、`Dockerfile` を作成してイメージをビルド、コンテナを実行します。
    -   [Dockerize a Python Flask Application](https://docs.docker.com/samples/related-project-examples/flask/) (Docker公式ドキュメントなど参照)
-   **AWS AIサービス連携**: 投稿されたメッセージの感情分析を `api_server.py` で行い、その結果を `chat.php` で表示してみましょう。(Amazon Comprehendなど)
    -   必要なIAM権限: `comprehend:DetectSentiment` (APIサーバーのIAMロールに付与)
    -   Python AWS SDK (boto3) のインストール: `pip3 install boto3`
-   **セキュリティ強化**:
    -   Webサーバーの前に [AWS WAF](https://aws.amazon.com/jp/waf/) を設置し、一般的なWeb攻撃から保護してみましょう。
    -   Webサーバーに簡易的な [BASIC認証](https://httpd.apache.org/docs/current/ja/howto/auth.html) を設定してみましょう。
-   **データ永続化の強化**: 現在はファイルにメッセージを保存していますが、より堅牢なデータベース（[Amazon DynamoDB](https://aws.amazon.com/jp/dynamodb/) や [Amazon RDS](https://aws.amazon.com/jp/rds/)）に置き換えてみましょう。
-   **CI/CDパイプラインの構築**: コードの変更を自動でデプロイする仕組み（[AWS CodePipeline](https://aws.amazon.com/jp/codepipeline/) など）を構築してみましょう。

---

不明な点や問題が発生した場合は、遠慮なく講師にご質問ください。

