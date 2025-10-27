<!-- chatbot.php -->
<style>
#chatbot { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
#chatbot-toggle { 
    background-color: #0293a1; color: white; font-size: 28px; width: 60px; height: 60px;
    text-align: center; line-height: 60px; border-radius: 50%; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
#chatbot-window { 
    display: none; width: 300px; max-height: 400px; background: white; border-radius: 10px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.3); flex-direction: column; overflow: hidden; 
    position: fixed; bottom: 100px; right: 20px;
}
#chatbot-header { 
    background-color: #007bff; color: white; padding: 10px; display: flex; justify-content: space-between; 
    font-weight: bold; cursor: grab; user-select: none;
}
#chatbot-body { padding: 10px; overflow-y: auto; max-height: 350px; display: flex; flex-direction: column; }
.bot-msg, .user-msg { border-radius: 10px; padding: 8px 10px; margin-bottom: 10px; max-width: 90%; word-wrap: break-word; }
.bot-msg { background-color: #f1f1f1; align-self: flex-start; }
.user-msg { background-color: #007bff; color: white; align-self: flex-end; }
#chatbot-options button { display: block; width: 100%; margin: 5px 0; padding: 8px; background: #e9f3ff; border: none; border-radius: 8px; cursor: pointer; transition: 0.3s; }
#chatbot-options button:hover { background: #007bff; color: white; }
#chatbot-close { cursor: pointer; }
</style>

<div id="chatbot">
    <div id="chatbot-toggle" class="fa-solid fa-circle-info"></div>

    <div id="chatbot-window">
        <div id="chatbot-header">
            <span>Servify Help</span>
            <span id="chatbot-close">✖</span>
        </div>
        <div id="chatbot-body">
            <div class="bot-msg">👋 Hi! How can I help you today?</div>
            <div id="chatbot-options">
                <button onclick="showAnswer('hire')">How do I hire a laborer?</button>
                <button onclick="showAnswer('verify')">How do I verify my account?</button>
                <button onclick="showAnswer('rating')">How do I rate a laborer?</button>
                <button onclick="showAnswer('contact')">Contact support</button>
            </div>
        </div>
    </div>
</div>

<script>
const toggle = document.getElementById('chatbot-toggle');
const windowDiv = document.getElementById('chatbot-window');
const closeBtn = document.getElementById('chatbot-close');

toggle.onclick = () => { 
    windowDiv.style.display = windowDiv.style.display === 'flex' ? 'none' : 'flex'; 
};

closeBtn.onclick = () => { windowDiv.style.display = 'none'; };

// Dragging
let isDragging = false, offsetX, offsetY;

const header = document.getElementById('chatbot-header');
header.addEventListener('mousedown', e => {
    isDragging = true;
    const rect = windowDiv.getBoundingClientRect();
    offsetX = e.clientX - rect.left;
    offsetY = e.clientY - rect.top;
    header.style.cursor = 'grabbing';
});

document.addEventListener('mouseup', () => { isDragging = false; header.style.cursor = 'grab'; });

document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    windowDiv.style.left = (e.clientX - offsetX) + 'px';
    windowDiv.style.top = (e.clientY - offsetY) + 'px';
    windowDiv.style.bottom = 'auto';
    windowDiv.style.right = 'auto';
});

// Show answer
function showAnswer(type){
    const body = document.getElementById('chatbot-body');
    const optionsDiv = document.getElementById('chatbot-options');
    let answer = '';
    switch(type){
        case 'hire': answer = "To hire a laborer, visit their profile, click 'Hire Now', enter location & message, then wait for acceptance."; break;
        case 'verify': answer = "Go to Profile > Verification, upload a Barangay ID and supporting documents. Wait for Barangay staff approval."; break;
        case 'rating': answer = "After a completed job, go to the laborer's profile and submit a star rating and review. You must be a verified user before you can rate"; break;
        case 'contact': answer = "Reach Servify Support at servify.support@gmail.com or +63 912 345 6789."; break;
    }

    const userMsg = document.createElement('div');
    userMsg.className = 'user-msg';
    userMsg.textContent = optionsDiv.querySelector(`button[onclick="showAnswer('${type}')"]`).innerText;
    body.appendChild(userMsg);

    const botMsg = document.createElement('div');
    botMsg.className = 'bot-msg';
    botMsg.textContent = answer;
    body.appendChild(botMsg);

    body.scrollTop = body.scrollHeight;
}
</script>
