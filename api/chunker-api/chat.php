<?php
$token = 'CHANGE_THIS_TO_A_LONG_SECRET_TOKEN';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chatbot</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    margin: 0;
    display: flex;
    flex-direction: column;
    height: 100vh;
}

header {
    background: #2c3e50;
    color: white;
    padding: 15px;
    font-size: 18px;
}

#chat {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

.message {
    margin-bottom: 15px;
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 10px;
    line-height: 1.4;
}

.user {
    background: #3498db;
    color: white;
    margin-left: auto;
}

.bot {
    background: #ecf0f1;
    color: #333;
}

.sources {
    font-size: 12px;
    margin-top: 6px;
    color: #666;
}

#inputBar {
    display: flex;
    padding: 10px;
    background: white;
    border-top: 1px solid #ccc;
}

#question {
    flex: 1;
    padding: 10px;
    font-size: 16px;
}

button {
    padding: 10px 15px;
    font-size: 16px;
    cursor: pointer;
}

.loading {
    font-style: italic;
    color: #888;
}
</style>
</head>

<body>

<header>Chatbot</header>

<div id="chat"></div>

<div id="inputBar">
    <input id="question" type="text" placeholder="Ask a question..." />
    <button onclick="send()">Send</button>
</div>

<script>
const token = "<?php echo $token; ?>";

function addMessage(text, type, sources = []) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = 'message ' + type;
    div.innerText = text;

    if (type === 'bot' && sources.length > 0) {
        const s = document.createElement('div');
        s.className = 'sources';

        s.innerHTML = sources.map(src => {
            return `${src.title || src.file_name}`;
        }).join('<br>');

        div.appendChild(s);
    }

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

async function send() {
    const input = document.getElementById('question');
    const question = input.value.trim();

    if (!question) return;

    addMessage(question, 'user');
    input.value = '';

    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'message bot loading';
    loadingMsg.innerText = 'Thinking...';
    document.getElementById('chat').appendChild(loadingMsg);

    try {
        const res = await fetch('ask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `token=${encodeURIComponent(token)}&question=${encodeURIComponent(question)}`
        });

        const data = await res.json();

        loadingMsg.remove();

        if (!data.ok) {
            addMessage("Error: " + (data.error || 'Unknown error'), 'bot');
            return;
        }

        addMessage(data.answer, 'bot', data.sources || []);

    } catch (err) {
        loadingMsg.remove();
        addMessage("Network error", 'bot');
    }
}

// Enter key support
document.getElementById('question').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        send();
    }
});
</script>

</body>
</html>