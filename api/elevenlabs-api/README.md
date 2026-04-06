# ElevenLabs API

Files:
- `index.html`: browser test page
- `voices.php`: server-side voice list proxy
- `tts.php`: server-side text-to-MP3 proxy

Bluehost setup:
1. Put the secret outside the public webroot, for example:
   - `~/elevenlabs_api_key.txt`
   - or `~/private/elevenlabs_api_key.txt`
2. Put only the raw API key in that file.
3. Alternative: set env var `ELEVENLABS_API_KEY`.

This implementation never exposes the API key to the browser.
