<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom ChatGPT</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #343541;
            color: #ececec;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #202123;
            padding: 15px 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #4a4a4a;
            color: #fff;
        }

        .upload-bar {
            background: #202123;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #4a4a4a;
        }

        .upload-bar input[type="file"] { color: #ececec; font-size: 13px; }

        .upload-btn {
            background: #10a37f;
            color: white;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.2s;
        }

        .upload-btn:hover { background: #0d8f6e; }

        #chatbox {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message-row { display: flex; align-items: flex-start; gap: 12px; }
        .message-row.user { flex-direction: row-reverse; }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .avatar.bot  { background: #10a37f; }
        .avatar.user { background: #5c5c7a; }

        .bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 15px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .bubble.bot  { background: #444654; color: #ececec; border-radius: 2px 12px 12px 12px; }
        .bubble.user { background: #10a37f; color: white;   border-radius: 12px 2px 12px 12px; }

        .typing .bubble { background: #444654; color: #aaa; font-style: italic; }

        .input-area {
            background: #40414f;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-top: 1px solid #4a4a4a;
        }

        #userInput {
            flex: 1;
            background: #40414f;
            border: 1px solid #686878;
            color: #ececec;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            resize: none;
            transition: border 0.2s;
            font-family: inherit;
        }

        #userInput:focus { border-color: #10a37f; }
        #userInput::placeholder { color: #888; }

        .send-btn {
            background: #10a37f;
            border: none;
            color: white;
            width: 42px;
            height: 42px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }

        .send-btn:hover    { background: #0d8f6e; }
        .send-btn:disabled { background: #555; cursor: not-allowed; }

        #chatbox::-webkit-scrollbar       { width: 6px; }
        #chatbox::-webkit-scrollbar-track { background: #343541; }
        #chatbox::-webkit-scrollbar-thumb { background: #666; border-radius: 3px; }

        .welcome { text-align: center; color: #888; margin: auto; padding: 40px; }
        .welcome h3 { font-size: 22px; color: #ececec; margin-bottom: 10px; }
        .welcome p  { font-size: 14px; }

        .upload-status {
            font-size: 13px;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .upload-status.success { color: #10a37f; }
        .upload-status.error   { color: #e55; }
    </style>
</head>
<body>

<?php
// ─── CONFIG ───────────────────────────────────────────────
define('OPENAI_API_KEY', 'sk-your-key-here');
define('OPENAI_MODEL',   'gpt-4o');
define('CHUNK_SIZE',      500);
define('EMBEDDINGS_FILE', 'embeddings.json');
define('UPLOAD_DIR',      'uploads/');

// ─── HELPERS ──────────────────────────────────────────────
function callOpenAI($endpoint, $payload) {
    $ch = curl_init('https://api.openai.com/v1/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return $result;
}

function getEmbedding($text) {
    $res = callOpenAI('embeddings', [
        'model' => 'text-embedding-3-small',
        'input' => $text
    ]);
    return $res['data'][0]['embedding'];
}

function cosineSimilarity($a, $b) {
    $dot = $normA = $normB = 0;
    for ($i = 0; $i < count($a); $i++) {
        $dot   += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    return ($normA && $normB) ? $dot / (sqrt($normA) * sqrt($normB)) : 0;
}

function splitIntoChunks($text) {
    $sentences = preg_split('/(?<=[.!?])\s+/', $text);
    $chunks = [];
    $current = '';
    foreach ($sentences as $sentence) {
        if (strlen($current) + strlen($sentence) < CHUNK_SIZE) {
            $current .= ' ' . $sentence;
        } else {
            if (trim($current)) $chunks[] = trim($current);
            $current = $sentence;
        }
    }
    if (trim($current)) $chunks[] = trim($current);
    return $chunks;
}

function findRelevantChunks($question, $topK = 3) {
    if (!file_exists(EMBEDDINGS_FILE)) return [];
    $allData = json_decode(file_get_contents(EMBEDDINGS_FILE), true);
    $qEmbed  = getEmbedding($question);
    $scores  = [];
    foreach ($allData as $i => $item) {
        $scores[$i] = cosineSimilarity($qEmbed, $item['embedding']);
    }
    arsort($scores);
    $chunks = [];
    foreach (array_slice($scores, 0, $topK, true) as $i => $score) {
        $chunks[] = $allData[$i]['text'];
    }
    return $chunks;
}

// ─── HANDLE PDF UPLOAD ────────────────────────────────────
$uploadStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

    $filepath = UPLOAD_DIR . basename($_FILES['pdf']['name']);
    if (move_uploaded_file($_FILES['pdf']['tmp_name'], $filepath)) {
        require 'vendor/autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($filepath);
        $text   = $pdf->getText();
        $chunks = splitIntoChunks($text);

        $embeddings = [];
        foreach ($chunks as $chunk) {
            $embeddings[] = [
                'text'      => $chunk,
                'embedding' => getEmbedding($chunk)
            ];
        }
        file_put_contents(EMBEDDINGS_FILE, json_encode($embeddings));
        $uploadStatus = 'success:✅ PDF processed! ' . count($chunks) . ' chunks stored.';
    } else {
        $uploadStatus = 'error:❌ Upload failed. Check folder permissions.';
    }
}

// ─── HANDLE CHAT AJAX ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $input       = json_decode(file_get_contents('php://input'), true);
    $question    = trim($input['question'] ?? '');
    $chatHistory = $input['history'] ?? [];

    if (!$question) {
        echo json_encode(['answer' => 'Please ask a question.']);
        exit;
    }

    $context = findRelevantChunks($question);

    $systemPrompt = "You are a helpful, friendly, and professional AI assistant.
Answer questions ONLY based on the context provided below.
If the answer is not found in the context, politely say 'I don't have that information in my knowledge base.'
Always reply in the same language the user writes in.

CONTEXT:
" . implode("\n\n---\n\n", $context);

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($chatHistory as $msg) {
        $messages[] = $msg;
    }
    $messages[] = ['role' => 'user', 'content' => $question];

    $res    = callOpenAI('chat/completions', [
        'model'       => OPENAI_MODEL,
        'messages'    => $messages,
        'temperature' => 0.7,
        'max_tokens'  => 1000
    ]);
    $answer = $res['choices'][0]['message']['content'] ?? 'Error getting response.';

    header('Content-Type: application/json');
    echo json_encode(['answer' => $answer]);
    exit;
}

// ─── UPLOAD STATUS PARSE ──────────────────────────────────
$statusClass = $statusMsg = '';
if ($uploadStatus) {
    [$statusClass, $statusMsg] = explode(':', $uploadStatus, 2);
}
?>

<!-- ─── UI ─────────────────────────────────────────────── -->
<div class="header">🤖 Custom ChatGPT</div>

<form class="upload-bar" action="" method="POST" enctype="multipart/form-data">
    <span>📄</span>
    <input type="file" name="pdf" accept=".pdf" required>
    <button type="submit" class="upload-btn">Upload PDF</button>
    <?php if ($statusMsg): ?>
        <span class="upload-status <?= $statusClass ?>"><?= htmlspecialchars($statusMsg) ?></span>
    <?php endif; ?>
</form>

<div id="chatbox">
    <div class="welcome">
        <h3>👋 Hello! Main tumhara Assistant hoon</h3>
        <p>PDF upload karo aur koi bhi sawaal pucho.</p>
    </div>
</div>

<div class="input-area">
    <textarea id="userInput" rows="1" placeholder="Message likho..."></textarea>
    <button class="send-btn" onclick="sendMessage()" id="sendBtn">➤</button>
</div>

<script>
let chatHistory = [];

async function sendMessage() {
    const input   = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    const question = input.value.trim();
    if (!question) return;

    document.querySelector('.welcome')?.remove();

    addMessage(question, 'user');
    input.value = '';
    input.style.height = 'auto';
    sendBtn.disabled = true;

    const typingEl = addTyping();

    try {
        const res = await fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ question, history: chatHistory })
        });
        const data = await res.json();
        typingEl.remove();
        addMessage(data.answer, 'bot');

        chatHistory.push({ role: 'user',      content: question    });
        chatHistory.push({ role: 'assistant', content: data.answer });

    } catch(e) {
        typingEl.remove();
        addMessage('❌ Error: Could not connect to server.', 'bot');
    }

    sendBtn.disabled = false;
    input.focus();
}

function addMessage(text, type) {
    const chatbox = document.getElementById('chatbox');
    const row     = document.createElement('div');
    row.className = `message-row ${type}`;

    const avatar = document.createElement('div');
    avatar.className = `avatar ${type}`;
    avatar.innerText  = type === 'user' ? '👤' : '🤖';

    const bubble = document.createElement('div');
    bubble.className = `bubble ${type}`;
    bubble.innerText  = text;

    row.appendChild(avatar);
    row.appendChild(bubble);
    chatbox.appendChild(row);
    chatbox.scrollTop = chatbox.scrollHeight;
    return row;
}

function addTyping() {
    const chatbox = document.getElementById('chatbox');
    const row     = document.createElement('div');
    row.className = 'message-row typing';

    const avatar = document.createElement('div');
    avatar.className = 'avatar bot';
    avatar.innerText  = '🤖';

    const bubble = document.createElement('div');
    bubble.className = 'bubble bot';
    bubble.innerText  = 'Typing...';

    row.appendChild(avatar);
    row.appendChild(bubble);
    chatbox.appendChild(row);
    chatbox.scrollTop = chatbox.scrollHeight;
    return row;
}

// Auto resize textarea
document.getElementById('userInput').addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// Enter = send, Shift+Enter = new line
document.getElementById('userInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>

</body>
</html>
