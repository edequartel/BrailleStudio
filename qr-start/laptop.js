<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
async function createLessonSession(lessonId = 'default') {
  const res = await fetch('./qr-start/session_create.php?lesson=' + encodeURIComponent(lessonId));
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'Could not create session');
  return data;
}

async function pollLessonSession(session, onStarted) {
  let started = false;

  const timer = setInterval(async () => {
    try {
      const res = await fetch('./qr-start/session_status.php?session=' + encodeURIComponent(session) + '&_=' + Date.now());
      const data = await res.json();
      if (data.ok && data.started && !started) {
        started = true;
        clearInterval(timer);
        onStarted(data);
      }
    } catch (err) {
      console.error(err);
    }
  }, 2000);

  return timer;
}

async function setupQrStart(lessonId = 'default') {
  const sessionData = await createLessonSession(lessonId);

  const qrCanvas = document.getElementById('qrCanvas');
  const qrText = document.getElementById('qrText');

  if (qrText) qrText.textContent = sessionData.qrUrl;
  if (qrCanvas) {
    await QRCode.toCanvas(qrCanvas, sessionData.qrUrl, { width: 240 });
  }

  await pollLessonSession(sessionData.session, async (data) => {
    console.log('Session started', data);

    // Optional: load correct lesson here first

    const runBtn = document.getElementById('runBtn');
    if (runBtn) {
      runBtn.click();
    }
  });
}
</script>