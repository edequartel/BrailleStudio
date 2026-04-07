# ElevenLabs API

Files:
- `index.html`: browser test page
- `tts.php`: server-side text-to-MP3 proxy

Authentication:
- login endpoint lives in `../authentication-api/login.php`
- `tts.php` requires a bearer token for audience `braillestudio-elevenlabs-api`

Bluehost setup:
1. Put the secret outside the public webroot, for example:
   - `~/elevenlabs_api_key.txt`
   - or `~/private/elevenlabs_api_key.txt`
2. Put only the raw API key in that file.
3. Alternative: set env var `ELEVENLABS_API_KEY`.
4. Put auth config outside the public webroot, for example:
   - `~/private/authentication_auth.php`
   - or `~/secrets/authentication_auth.php`
5. Use the structure from `../authentication-api/authentication_auth.example.php`:
   - `jwt_secret`
   - `token_ttl`
   - `users[]` with `username`, `role`, `password_hash`
6. Generate password hashes with PHP `password_hash(...)` on a trusted machine.

This implementation never exposes the API key to the browser.
The TTS endpoint now requires a bearer token from `../authentication-api/login.php`.
