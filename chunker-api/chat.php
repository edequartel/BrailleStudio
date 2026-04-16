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
    white-space: pre-wrap;
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

#inputBar {
    display: flex;
    padding: 10px;
    background: white;
    border-top: 1px solid #ccc;
    gap: 10px;
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
const token = "<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>";

function addMessage(text, type) {
    const chat = document.getElementById('chat');

    const div = document.createElement('div');
    div.className = 'message ' + type;
    div.innerText = text;

    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
}

async function send() {
    const input = document.getElementById('question');
    const question = input.value.trim();

    if (!question) return;

    addMessage(question, 'user');
    input.value = '';

    const chat = document.getElementById('chat');
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'message bot loading';
    loadingMsg.innerText = 'Thinking...';
    chat.appendChild(loadingMsg);
    chat.scrollTop = chat.scrollHeight;

    try {
        const res = await fetch('ask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `token=${encodeURIComponent(token)}&question=${encodeURIComponent(question)}`
        });

        let data;
        try {
            data = await res.json();
        } catch {
            loadingMsg.remove();
            addMessage('Invalid JSON response from server.', 'bot');
            return;
        }

        loadingMsg.remove();

        if (!data.ok) {
            addMessage("Error: " + (data.error || 'Unknown error'), 'bot');
            return;
        }

        addMessage(data.answer || 'No answer returned.', 'bot');

    } catch (err) {
        loadingMsg.remove();
        addMessage('Network error', 'bot');
    }
}

document.getElementById('question').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        send();
    }
});
</script>

</body>
</html>