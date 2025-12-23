const BRAILLE_API = "http://localhost:5000";

async function loadActivity() {
  const res = await fetch("./activity.json");
  return await res.json();
}

async function braille(text) {
  await fetch(`${BRAILLE_API}/braille`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ text })
  });
}

function playAudio(src) {
  const audio = new Audio(src);
  audio.play();
}

async function start() {
  const activity = await loadActivity();

  document.getElementById("title").textContent = activity.title;
  document.getElementById("description").textContent = activity.description;

  await braille(activity.braille.introText);
  playAudio(activity.audio.intro);

  let index = 0;
  const item = activity.audio.items[index];

  playAudio(item.audio);

  // Placeholder: hook this into BrailleBridge WebSocket
  window.addEventListener("keydown", async (e) => {
    if (e.key === "Enter") {
      await braille(activity.braille.successText);
      playAudio(activity.audio.success);
    }
  });
}

start();