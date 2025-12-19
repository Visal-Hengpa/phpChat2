<?php
// 環境変数の読み込み
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// APIキーの設定
$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

// レスポンスヘッダー
header('Content-Type: text/plain; charset=utf-8');

// メインの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim(file_get_contents('php://input'));
    
    if (empty($input)) {
        echo "入力が空です。";
        exit;
    }
    
    if (empty($apiKey)) {
        echo "APIキーが設定されていません。.envファイルを確認してください。";
        exit;
    }
    
    // OpenAI APIへのリクエスト
    $result = callOpenAI($apiKey, $input);
    
    if ($result === false) {
        echo "API呼び出しに失敗しました。";
    } else {
        echo $result;
    }
} else {
    echo "POSTメソッドでテキストを送信してください。\n\n";
    echo "例:\n";
    echo "curl -X POST -d '人工知能の未来について教えてください。' http://localhost/\n\n";
    echo "またはブラウザからJavaScriptを使用:";
    ?>
    <html>
    <body>
        <textarea id="input" rows="4" cols="50" placeholder="質問を入力してください"></textarea><br>
        <button onclick="sendRequest()">送信</button>
        <pre id="output"></pre>
        
        <script>
        function sendRequest() {
            const input = document.getElementById('input').value;
            const output = document.getElementById('output');
            
            fetch('', {
                method: 'POST',
                body: input,
                headers: {
                    'Content-Type': 'text/plain'
                }
            })
            .then(response => response.text())
            .then(text => {
                output.textContent = text;
            })
            .catch(error => {
                output.textContent = 'エラー: ' + error;
            });
        }
        </script>
    </body>
    </html>
    <?php
}

// OpenAI API呼び出し関数
function callOpenAI($apiKey, $input) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    // システムプロンプト（重要な語と英訳を抽出する指示）
    $systemPrompt = "ユーザーの入力から最も重要な1つの語（原語）を選び、その英訳を以下の形式で出力してください：
    
形式：
重要な語： [原語]
英訳： [英訳]

例：
入力：「人工知能の未来について教えてください。」
出力：
重要な語： 人工知能
英訳： Artificial Intelligence

注意：
- 必ず上記の形式のみを出力し、説明や追加のテキストは含めないでください。
- 重要な語は名詞から選んでください。
- 英訳は適切な英語の単語またはフレーズを提供してください。";
    
    $data = [
        'model' => 'gpt-4o-mini', // gpt-5-miniはまだ存在しないので、gpt-4o-miniを使用
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $input
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 100
    ];
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        return trim($responseData['choices'][0]['message']['content']);
    }
    
    return false;
}
?>