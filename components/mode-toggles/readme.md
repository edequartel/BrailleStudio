# Mode Toggles Component

Reusable status pills for keyboard-driven modes.

## Files
- `mode-toggles.js`
- `mode-toggles.css`

## Usage

```html
<link rel="stylesheet" href="../components/mode-toggles/mode-toggles.css" />
<div id="modeToggles"></div>
<script src="../components/mode-toggles/mode-toggles.js"></script>
<script>
  const toggles = new ModeToggles({ containerId: "modeToggles" });
  toggles.setEditorMode(true);
  toggles.setInsertMode(false);
  toggles.setBlink(false);
  toggles.setCaret(true);
</script>
```

## API
- `setState(key, enabled)`
- `getState(key)`
- `setEditorMode(enabled)`
- `setInsertMode(enabled)`
- `setBlink(enabled)`
- `setCaret(enabled)`
