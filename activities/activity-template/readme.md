Below is a copy-paste activity template that fits directly into the existing BrailleServer / BrailleBridge architecture you are already using.
It is intentionally minimal, explicit, and consistent with your current patterns (JSON-driven content, reusable JS, localhost BrailleBridge API).

⸻

1. Folder structure (copy this)

Create a new activity by copying the entire folder and renaming it.

/activities/
  activity-template/
    activity.json
    activity.html
    activity.js
    activity.css

Example after copying:

/activities/
  letter-sound-match/


⸻

2. activity.json (content + configuration)

This file is the only thing teachers or content editors need to change.

{
  “id”: “letter-sound-match”,
  “title”: “Letter & Sound Match”,
  “description”: “Listen to a sound and find the correct braille letter.”,
  “language”: “nl”,

  “braille”: {
    “introText”: “Zoek de juiste letter”,
    “successText”: “Goed gedaan”,
    “failText”: “Probeer opnieuw”
  },

  “audio”: {
    “intro”: “/sounds/shared/ui/start.mp3”,
    “success”: “/sounds/shared/ui/success.mp3”,
    “fail”: “/sounds/shared/ui/fail.mp3”,
    “items”: [
      {
        “label”: “a”,
        “audio”: “/sounds/nl/letters/a.mp3”
      },
      {
        “label”: “b”,
        “audio”: “/sounds/nl/letters/b.mp3”
      }
    ]
  },

  “input”: {
    “type”: “braille-keys”,
    “confirmKey”: “enter”,
    “nextKey”: “right”
  }
}

What this gives you:
	•	Stable activity ID
	•	Text for braille output
	•	Audio references (no hardcoding in JS)
	•	Clear input model for reuse later

⸻

3. activity.html (thin view layer)

<!doctype html>
<html lang=“nl”>
<head>
  <meta charset=“utf-8” />
  <title>Activity</title>

  <link rel=“stylesheet” href=“./activity.css” />
</head>
<body>

  <main class=“activity”>
    <h1 id=“title”></h1>
    <p id=“description”></p>

    <div id=“status” class=“status”></div>
  </main>

  <script src=“./activity.js”></script>
</body>
</html>

No logic here.
No BrailleBridge calls here.
This stays identical for all activities.

⸻

4. activity.js (generic activity engine)

This file should not change per activity unless you add features.

const BRAILLE_API = “http://localhost:5000”;

async function loadActivity() {
  const res = await fetch(“./activity.json”);
  return await res.json();
}

async function braille(text) {
  await fetch(`${BRAILLE_API}/braille`, {
    method: “POST”,
    headers: { “Content-Type”: “application/json” },
    body: JSON.stringify({ text })
  });
}

function playAudio(src) {
  const audio = new Audio(src);
  audio.play();
}

async function start() {
  const activity = await loadActivity();

  document.getElementById(“title”).textContent = activity.title;
  document.getElementById(“description”).textContent = activity.description;

  await braille(activity.braille.introText);
  playAudio(activity.audio.intro);

  let index = 0;
  const item = activity.audio.items[index];

  playAudio(item.audio);

  // Placeholder: hook this into BrailleBridge WebSocket
  window.addEventListener(“keydown”, async (e) => {
    if (e.key === “Enter”) {
      await braille(activity.braille.successText);
      playAudio(activity.audio.success);
    }
  });
}

start();

Later you can replace:
	•	keydown → WebSocket key events
	•	single item → activity flow controller
	•	success logic → shared ActivityRunner

⸻

5. activity.css (isolated styling)

.activity {
  font-family: system-ui, -apple-system, Segoe UI, sans-serif;
  padding: 1.5rem;
}

.status {
  margin-top: 1rem;
  font-weight: 600;
}

Each activity can look different without breaking others.

⸻

6. How you use this in practice

Workflow for you or colleagues:
	1.	Copy /activities/activity-template
	2.	Rename folder and id in activity.json
	3.	Change:
	•	intro text
	•	audio files
	•	braille messages
	4.	Done

No JS changes required for 80–90% of activities.

⸻

7. Why this fits your repo and goals
	•	Matches your component-based thinking
	•	JSON is teacher-friendly
	•	JS is reusable and testable
	•	BrailleBridge stays the single hardware authority
	•	Easy future upgrade to:
	•	activity sequences
	•	scoring
	•	progress tracking
	•	MPOP++ content packs

⸻

Next logical step (recommended)

Create one shared engine:

/js/activity-runner.js

Then activity.js becomes a 10-line wrapper.

If you want, I can:
	•	Refactor this into a shared ActivityRunner
	•	Add WebSocket key handling from BrailleBridge
	•	Add activity chaining (nextActivityId)
	•	Align this exactly with words.json / stories.json

Just say which one you want next.