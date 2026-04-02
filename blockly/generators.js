(function () {
  if (!window.Blockly || !Blockly.JavaScript) return;

  const javascriptGenerator = Blockly.JavaScript;
  const ORDER_ATOMIC = javascriptGenerator.ORDER_ATOMIC || 0;
  const ORDER_NONE = javascriptGenerator.ORDER_NONE || 99;

  function valueToCodeOr(block, inputName, fallbackCode = "''") {
    const code = javascriptGenerator.valueToCode(block, inputName, ORDER_NONE);
    return code || fallbackCode;
  }

  function q(value) {
    return JSON.stringify(String(value ?? ''));
  }

  javascriptGenerator.forBlock['list_pick_random'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [`BrailleStudioAPI.pickRandom(${listCode})`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['list_length'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [`(Array.isArray(${listCode}) ? ${listCode}.length : 0)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['list_nrof_items'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [`(Array.isArray(${listCode}) ? ${listCode}.length : 0)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['list_from_text_items'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [
      `(() => String(${textCode} ?? '').split(/[\\s,;]+/).map(item => item.trim()).filter(Boolean))()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_filter_text_length'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const minCode = valueToCodeOr(block, 'MIN', '1');
    return [
      `((Array.isArray(${listCode}) ? ${listCode} : []).filter(item => Array.from(String(item ?? '')).length > (Math.floor(Number(${minCode}) || 0))))`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_filter_phoneme_category'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const category = q(block.getFieldValue('CATEGORY') || 'medeklinker');
    return [
      `(await (async () => {
        const __list = Array.isArray(${listCode}) ? ${listCode} : [];
        const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));
        const __category = ${category};
        const __allowed = new Set((Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])
          .filter(item => String(item?.category ?? '').trim().toLowerCase() === __category.toLowerCase())
          .map(item => String(item?.phoneme ?? '').trim().toLowerCase())
          .filter(Boolean));
        return __list.filter(item => __allowed.has(String(item ?? '').trim().toLowerCase()));
      })())`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_filter_phoneme_categories'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const selected = [
      ['KORTEKLINKER', 'korteKlinker'],
      ['LANGEKLINKER', 'langeKlinker'],
      ['TWEETEKENKLANK', 'tweetekenklank'],
      ['DRIETEKENKLANK', 'drietekenklank'],
      ['MEDEKLINKER', 'medeklinker'],
      ['MEDEKLINKERCLUSTER', 'medeklinkercluster'],
      ['VIERTEKENKLANK', 'viertekenklank']
    ]
      .filter(([field]) => String(block.getFieldValue(field)) === 'TRUE')
      .map(([, value]) => value);
    return [
      `(await (async () => {
        const __list = Array.isArray(${listCode}) ? ${listCode} : [];
        const __selected = ${JSON.stringify(selected)};
        if (!__selected.length) return [];
        const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));
        const __allowed = new Set((Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])
          .filter(item => __selected.includes(String(item?.category ?? '').trim()))
          .map(item => String(item?.phoneme ?? '').trim().toLowerCase())
          .filter(Boolean));
        return __list.filter(item => __allowed.has(String(item ?? '').trim().toLowerCase()));
      })())`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_shuffle'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [
      `(() => {
        const __list = Array.isArray(${listCode}) ? [...${listCode}] : [];
        for (let __i = __list.length - 1; __i > 0; __i--) {
          const __j = Math.floor(Math.random() * (__i + 1));
          [__list[__i], __list[__j]] = [__list[__j], __list[__i]];
        }
        return __list;
      })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_sort'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const order = q(block.getFieldValue('ORDER') || 'ASC');
    return [
      `(() => {
        const __order = ${order};
        const __list = Array.isArray(${listCode}) ? [...${listCode}] : [];
        __list.sort((a, b) => String(a ?? '').localeCompare(String(b ?? ''), undefined, { numeric: true, sensitivity: 'base' }));
        return __order === 'DESC' ? __list.reverse() : __list;
      })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['list_sort_by_length'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const order = q(block.getFieldValue('ORDER') || 'ASC');
    return [
      `(() => {
        const __order = ${order};
        const __list = Array.isArray(${listCode}) ? [...${listCode}] : [];
        __list.sort((a, b) => Array.from(String(a ?? '')).length - Array.from(String(b ?? '')).length);
        return __order === 'DESC' ? __list.reverse() : __list;
      })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['math_random_10'] = function (block) {
    const maxCode = valueToCodeOr(block, 'MAX', '10');
    return [`(() => { const max = Math.floor(Number(${maxCode}) || 0); return max <= 0 ? 0 : Math.floor(Math.random() * max); })()`, ORDER_ATOMIC];
  };

  function getGeneratorVariableName(block, generator, fieldName = 'VAR', fallback = 'value') {
    if (typeof generator.getVariableName === 'function') {
      return generator.getVariableName(block.getFieldValue(fieldName));
    }
    if (generator.nameDB_ && typeof generator.nameDB_.getName === 'function') {
      return generator.nameDB_.getName(block.getFieldValue(fieldName), Blockly.VARIABLE_CATEGORY_NAME);
    }
    return fallback;
  }

  javascriptGenerator.forBlock['math_inc_var'] = function (block, generator) {
    const varName = getGeneratorVariableName(block, generator, 'VAR', 'value');
    return `${varName} += 1;\n`;
  };

  javascriptGenerator.forBlock['math_dec_var'] = function (block, generator) {
    const varName = getGeneratorVariableName(block, generator, 'VAR', 'value');
    return `${varName} -= 1;\n`;
  };

  javascriptGenerator.forBlock['bb_current_text'] = function () {
    return [`runtime.text`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['bb_current_braille_unicode'] = function () {
    return [`runtime.brailleUnicode`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['bb_letter_under_cursor'] = function () {
    return [
      `(() => { const text = String(runtime.text ?? ''); const chars = Array.from(text); const index = Math.max(0, Math.floor(Number(runtime.textCaret) || 0)); return chars[index] ?? ''; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['bb_word_under_cursor'] = function () {
    return [
      `(() => {
        const text = String(runtime.text ?? '');
        const chars = Array.from(text);
        if (!chars.length) return '';
        let index = Math.max(0, Math.floor(Number(runtime.textCaret) || 0));
        if (index >= chars.length) index = chars.length - 1;
        const isWordChar = (ch) => /[\\p{L}\\p{N}_-]/u.test(String(ch ?? ''));
        if (!isWordChar(chars[index]) && index > 0 && isWordChar(chars[index - 1])) {
          index -= 1;
        }
        if (!isWordChar(chars[index])) return '';
        let start = index;
        let end = index;
        while (start > 0 && isWordChar(chars[start - 1])) start -= 1;
        while (end < chars.length - 1 && isWordChar(chars[end + 1])) end += 1;
        return chars.slice(start, end + 1).join('');
      })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['log_value'] = function (block) {
    const valueCode = valueToCodeOr(block, 'VALUE', "''");
    return `console.log(${valueCode});\n`;
  };

  javascriptGenerator.forBlock['log_variable'] = function (block, generator) {
    const varName = getGeneratorVariableName(block, generator, 'VAR', 'value');
    return `console.log(${JSON.stringify(varName + ' = ')}, ${varName});\n`;
  };

  javascriptGenerator.forBlock['log_clear'] = function () {
    return `(() => { const box = document.getElementById('logBox'); if (box) box.value = ''; })();\n`;
  };

  javascriptGenerator.forBlock['audio_item_get_word'] = function (block) {
    const itemCode = valueToCodeOr(block, 'ITEM', 'null');
    return [`((${itemCode})?.word ?? '')`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['audio_item_get_url'] = function (block) {
    const itemCode = valueToCodeOr(block, 'ITEM', 'null');
    return [`((${itemCode})?.url ?? '')`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_get_aanvankelijklijst'] = function () {
    return [`await window.BrailleBlocklyApp.getLessonData()`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_get_speech_audio_by_onlyletters'] = function (block) {
    const onlylettersCode = valueToCodeOr(block, 'ONLYLETTERS', "''");
    return [
      `await BrailleStudioAPI.getAudioList({folder:'speech',letters:'',klanken:'',onlyletters:String(${onlylettersCode} ?? ''),onlyklanken:'',onlycombo:false,maxlength:'',length:'',limit:'',randomlimit:'',sort:''})`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['audio_get_speech_audio_by_letters_klanken'] = function (block) {
    const lettersCode = valueToCodeOr(block, 'LETTERS', "''");
    const klankenCode = valueToCodeOr(block, 'KLANKEN', "''");
    return [
      `await BrailleStudioAPI.getAudioList({folder:'speech',letters:String(${lettersCode} ?? ''),klanken:String(${klankenCode} ?? ''),onlyletters:'',onlyklanken:'',onlycombo:false,maxlength:'',length:'',limit:'',randomlimit:'',sort:''})`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['audio_get_speech_audio_by_onlyletters_klanken_length'] = function (block) {
    const onlylettersCode = valueToCodeOr(block, 'ONLYLETTERS', "''");
    const klankenCode = valueToCodeOr(block, 'KLANKEN', "''");
    const lengthCode = valueToCodeOr(block, 'LENGTH', '0');
    return [
      `await BrailleStudioAPI.getAudioList({folder:'speech',letters:'',klanken:String(${klankenCode} ?? ''),onlyletters:String(${onlylettersCode} ?? ''),onlyklanken:'',onlycombo:false,maxlength:'',length:String(${lengthCode} ?? ''),limit:'',randomlimit:'',sort:''})`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_set_active_record_index'] = function (block) {
    const indexCode = valueToCodeOr(block, 'INDEX', '0');
    return (
      `await (async () => {\n` +
      `  let list = Array.isArray(window.aanvankelijkData) ? window.aanvankelijkData : [];\n` +
      `  if (!list.length) {\n` +
      `    list = await window.BrailleBlocklyApp.getLessonData();\n` +
      `    window.aanvankelijkData = list;\n` +
      `  }\n` +
      `  const index = Math.floor(Number(${indexCode}) || 0);\n` +
      `  const record = index >= 0 && index < list.length ? list[index] : null;\n` +
      `  window.currentRecord = record;\n` +
      `  window.currentRecordIndex = record ? index : -1;\n` +
      `})();\n`
    );
  };

  javascriptGenerator.forBlock['lesson_get_data'] = function () {
    return [
      `(Array.isArray(window.aanvankelijkData) && window.aanvankelijkData.length ? window.aanvankelijkData : await window.BrailleBlocklyApp.getLessonData().then(list => { window.aanvankelijkData = list; return list; }))`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_record_count'] = function () {
    return [
      `((Array.isArray(window.aanvankelijkData) && window.aanvankelijkData.length) ? window.aanvankelijkData.length : await window.BrailleBlocklyApp.getLessonData().then(list => { window.aanvankelijkData = list; return list.length; }))`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_active_record'] = function () {
    return [`((window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['lesson_get_active_record_index'] = function () {
    return [`(Number.isInteger(window.currentRecordIndex) ? window.currentRecordIndex : -1)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['lesson_get_active_word'] = function () {
    return [`(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; return String(record?.word ?? ''); })()`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['lesson_get_active_field'] = function (block) {
    const field = q(block.getFieldValue('FIELD') || 'word');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const key = ${field}; const value = record ? record[key] : undefined; if (value != null) return value; if (key === 'word') return ''; if (key === 'categories' || key === 'newSoundCategories' || key === 'knownSoundCategories') return {}; return []; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_active_sounds'] = function (block) {
    const source = q(block.getFieldValue('SOURCE') || 'ALL');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const src = ${source}; if (!record) return []; if (src === 'NEW') return Array.isArray(record.newSounds) ? record.newSounds : []; if (src === 'KNOWN') return Array.isArray(record.knownSounds) ? record.knownSounds : []; return Array.isArray(record.sounds) ? record.sounds : []; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_active_sound_count'] = function (block) {
    const source = q(block.getFieldValue('SOURCE') || 'ALL');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const src = ${source}; if (!record) return 0; const value = src === 'NEW' ? record.newSounds : src === 'KNOWN' ? record.knownSounds : record.sounds; return Array.isArray(value) ? value.length : 0; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_active_category'] = function (block) {
    const source = q(block.getFieldValue('SOURCE') || 'categories');
    const category = q(block.getFieldValue('CATEGORY') || 'medeklinkers');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const root = record && typeof record[${source}] === 'object' && record[${source}] ? record[${source}] : null; const value = root ? root[${category}] : undefined; return Array.isArray(value) ? value : []; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_active_category_count'] = function (block) {
    const source = q(block.getFieldValue('SOURCE') || 'categories');
    const category = q(block.getFieldValue('CATEGORY') || 'medeklinkers');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const root = record && typeof record[${source}] === 'object' && record[${source}] ? record[${source}] : null; const value = root ? root[${category}] : undefined; return Array.isArray(value) ? value.length : 0; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_get_step_input'] = function (block) {
    const field = q(block.getFieldValue('FIELD') || 'text');
    return [
      `(() => { const inputs = (window.lessonStepInputs && typeof window.lessonStepInputs === 'object') ? window.lessonStepInputs : {}; const key = ${field}; const value = inputs[key]; if (key === 'letters') return Array.isArray(value) ? value : []; return value != null ? value : ''; })()`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['lesson_complete_step'] = function (block) {
    const status = q(block.getFieldValue('STATUS') || 'completed');
    const outputCode = valueToCodeOr(block, 'OUTPUT', 'null');
    const scoreCode = valueToCodeOr(block, 'SCORE', 'null');
    const maxScoreCode = valueToCodeOr(block, 'MAX_SCORE', 'null');
    const attemptsCode = valueToCodeOr(block, 'ATTEMPTS', 'null');
    const durationCode = valueToCodeOr(block, 'DURATION_MS', 'null');
    const answerCode = valueToCodeOr(block, 'ANSWER', 'null');
    const expectedAnswerCode = valueToCodeOr(block, 'EXPECTED_ANSWER', 'null');
    const feedbackCode = valueToCodeOr(block, 'FEEDBACK', 'null');
    const metadataCode = valueToCodeOr(block, 'METADATA', 'null');
    return (
      `await window.BrailleBlocklyApp.signalLessonStepCompletion({ ` +
      `status: ${status}, ` +
      `output: ${outputCode}, ` +
      `score: ${scoreCode}, ` +
      `maxScore: ${maxScoreCode}, ` +
      `attempts: ${attemptsCode}, ` +
      `durationMs: ${durationCode}, ` +
      `answer: ${answerCode}, ` +
      `expectedAnswer: ${expectedAnswerCode}, ` +
      `feedback: ${feedbackCode}, ` +
      `metadata: ${metadataCode} ` +
      `});\n`
    );
  };

  javascriptGenerator.forBlock['klanken_word_get_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`((await window.BrailleBlocklyApp.findLessonItemByWord(${wordCode}))?.sounds ?? [])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_word_get_new_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`((await window.BrailleBlocklyApp.findLessonItemByWord(${wordCode}))?.newSounds ?? [])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_word_get_known_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`((await window.BrailleBlocklyApp.findLessonItemByWord(${wordCode}))?.knownSounds ?? [])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_play_word_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return (
      `for (const sound of ((await window.BrailleBlocklyApp.findLessonItemByWord(${wordCode}))?.sounds ?? [])) {\n` +
      `  await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/nl/letters/' + encodeURIComponent(String(sound).toLowerCase().endsWith('.mp3') ? String(sound) : String(sound) + '.mp3'));\n` +
      `}\n`
    );
  };

  javascriptGenerator.forBlock['klanken_play_word_sounds_with_pause'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    const secondsCode = valueToCodeOr(block, 'SECONDS', '0');
    return (
      `{\n` +
      `  const __pauseMs = Math.max(0, Math.round((Number(${secondsCode}) || 0) * 1000));\n` +
      `  const __sounds = ((await window.BrailleBlocklyApp.findLessonItemByWord(${wordCode}))?.sounds ?? []);\n` +
      `  for (let __i = 0; __i < __sounds.length; __i++) {\n` +
      `    const sound = __sounds[__i];\n` +
      `    await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/nl/letters/' + encodeURIComponent(String(sound).toLowerCase().endsWith('.mp3') ? String(sound) : String(sound) + '.mp3'));\n` +
      `    if (__pauseMs > 0 && __i < __sounds.length - 1) await new Promise(resolve => setTimeout(resolve, __pauseMs));\n` +
      `  }\n` +
      `}\n`
    );
  };

  javascriptGenerator.forBlock['klanken_play_word_phonemes_nl'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return (
      `{\n` +
      `  const __word = String(${wordCode} ?? '').trim().toLowerCase();\n` +
      `  const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));\n` +
      `  const __tokens = (Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])\n` +
      `    .map(p => String(p?.phoneme ?? '').trim().toLowerCase())\n` +
      `    .filter(Boolean)\n` +
      `    .sort((a, b) => b.length - a.length);\n` +
      `  const __fonemen = [];\n` +
      `  for (let __i = 0; __i < __word.length;) {\n` +
      `    const __ch = __word[__i];\n` +
      `    if (!/[a-z]/.test(__ch)) { __i += 1; continue; }\n` +
      `    let __m = '';\n` +
      `    for (const __t of __tokens) { if (__t && __word.startsWith(__t, __i)) { __m = __t; break; } }\n` +
      `    if (__m) { __fonemen.push(__m); __i += __m.length; } else { __fonemen.push(__ch); __i += 1; }\n` +
      `  }\n` +
      `  for (const __f of __fonemen) {\n` +
      `    await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/nl/letters/' + encodeURIComponent(String(__f).toLowerCase().endsWith('.mp3') ? String(__f) : String(__f) + '.mp3'));\n` +
      `  }\n` +
      `}\n`
    );
  };

  javascriptGenerator.forBlock['klanken_split_word_phonemes_nl'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [
      `(await (async () => {\n` +
      `  const __word = String(${wordCode} ?? '').trim().toLowerCase();\n` +
      `  const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));\n` +
      `  const __tokens = (Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])\n` +
      `    .map(p => String(p?.phoneme ?? '').trim().toLowerCase())\n` +
      `    .filter(Boolean)\n` +
      `    .sort((a, b) => b.length - a.length);\n` +
      `  const __fonemen = [];\n` +
      `  for (let __i = 0; __i < __word.length;) {\n` +
      `    const __ch = __word[__i];\n` +
      `    if (!/[a-z]/.test(__ch)) { __i += 1; continue; }\n` +
      `    let __m = '';\n` +
      `    for (const __t of __tokens) { if (__t && __word.startsWith(__t, __i)) { __m = __t; break; } }\n` +
      `    if (__m) { __fonemen.push(__m); __i += __m.length; } else { __fonemen.push(__ch); __i += 1; }\n` +
      `  }\n` +
      `  return __fonemen;\n` +
      `})())`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['klanken_split_text_phonemes_nl'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [
      `(await (async () => {\n` +
      `  const __text = String(${textCode} ?? '').toLowerCase();\n` +
      `  const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));\n` +
      `  const __tokens = (Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])\n` +
      `    .map(p => String(p?.phoneme ?? '').trim().toLowerCase())\n` +
      `    .filter(Boolean)\n` +
      `    .sort((a, b) => b.length - a.length);\n` +
      `  const __words = __text.match(/[a-z]+/g) || [];\n` +
      `  const __out = [];\n` +
      `  for (const __word of __words) {\n` +
      `    for (let __i = 0; __i < __word.length;) {\n` +
      `      const __ch = __word[__i];\n` +
      `      let __m = '';\n` +
      `      for (const __t of __tokens) { if (__t && __word.startsWith(__t, __i)) { __m = __t; break; } }\n` +
      `      if (__m) { __out.push(__m); __i += __m.length; } else { __out.push(__ch); __i += 1; }\n` +
      `    }\n` +
      `  }\n` +
      `  return __out;\n` +
      `})())`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['klanken_play_word_phonemes_nl_with_pause'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    const secondsCode = valueToCodeOr(block, 'SECONDS', '0');
    return (
      `{\n` +
      `  const __pauseMs = Math.max(0, Math.round((Number(${secondsCode}) || 0) * 1000));\n` +
      `  const __word = String(${wordCode} ?? '').trim().toLowerCase();\n` +
      `  const __nl = await fetch('../klanken/fonemen_nl_standaard.json', { cache: 'no-store' }).then(res => res.json()).catch(() => ({ phonemes: [] }));\n` +
      `  const __tokens = (Array.isArray(__nl?.phonemes) ? __nl.phonemes : [])\n` +
      `    .map(p => String(p?.phoneme ?? '').trim().toLowerCase())\n` +
      `    .filter(Boolean)\n` +
      `    .sort((a, b) => b.length - a.length);\n` +
      `  const __fonemen = [];\n` +
      `  for (let __i = 0; __i < __word.length;) {\n` +
      `    const __ch = __word[__i];\n` +
      `    if (!/[a-z]/.test(__ch)) { __i += 1; continue; }\n` +
      `    let __m = '';\n` +
      `    for (const __t of __tokens) { if (__t && __word.startsWith(__t, __i)) { __m = __t; break; } }\n` +
      `    if (__m) { __fonemen.push(__m); __i += __m.length; } else { __fonemen.push(__ch); __i += 1; }\n` +
      `  }\n` +
      `  for (let __k = 0; __k < __fonemen.length; __k++) {\n` +
      `    const __f = __fonemen[__k];\n` +
      `    await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/nl/letters/' + encodeURIComponent(String(__f).toLowerCase().endsWith('.mp3') ? String(__f) : String(__f) + '.mp3'));\n` +
      `    if (__pauseMs > 0 && __k < __fonemen.length - 1) await new Promise(resolve => setTimeout(resolve, __pauseMs));\n` +
      `  }\n` +
      `}\n`
    );
  };

  javascriptGenerator.forBlock['klanken_item_get_word'] = function (block) {
    const itemCode = valueToCodeOr(block, 'ITEM', 'null');
    return [`((${itemCode})?.word ?? '')`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_item_get_sounds'] = function (block) {
    const itemCode = valueToCodeOr(block, 'ITEM', 'null');
    const source = q(block.getFieldValue('SOURCE') || 'ALL');
    return [`(${source} === 'NEW' ? ((${itemCode})?.newSounds ?? []) : ${source} === 'KNOWN' ? ((${itemCode})?.knownSounds ?? []) : ((${itemCode})?.sounds ?? []))`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_item_get_category'] = function (block) {
    const itemCode = valueToCodeOr(block, 'ITEM', 'null');
    const source = q(block.getFieldValue('SOURCE') || 'ALL');
    const category = q(block.getFieldValue('CATEGORY') || 'medeklinkers');
    const categoryRoot = `(${source} === 'NEW' ? ((${itemCode})?.newSoundCategories ?? {}) : ${source} === 'KNOWN' ? ((${itemCode})?.knownSoundCategories ?? {}) : ((${itemCode})?.categories ?? {}))`;
    return [`(${categoryRoot}[${category}] ?? [])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['sound_play_url'] = function (block) {
    const urlCode = valueToCodeOr(block, 'URL', "''");
    return `await BrailleStudioAPI.playUrl(${urlCode});\n`;
  };

  javascriptGenerator.forBlock['sound_play_folder_file'] = function (block) {
    const folder = q(block.getFieldValue('FOLDER') || 'speech');
    const file = valueToCodeOr(block, 'FILE', "'voorbeeld'");
    const code =
      `await BrailleStudioAPI.playUrl((() => { ` +
      `const bases = {` +
      `speech:'https://www.tastenbraille.com/braillestudio/sounds/nl/speech/',` +
      `letters:'https://www.tastenbraille.com/braillestudio/sounds/nl/letters/',` +
      `instructions:'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/',` +
      `feedback:'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback/',` +
      `story:'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',` +
      `general:'https://www.tastenbraille.com/braillestudio/sounds/general/'` +
      `}; ` +
      `const folder = ${folder}; ` +
      `const file = ${file}; ` +
      `const base = bases[folder] || bases.speech; ` +
      `const name = String(file).toLowerCase().endsWith('.mp3') ? String(file) : String(file) + '.mp3'; ` +
      `return /^https?:\\/\\//i.test(String(file)) ? String(file) : base + encodeURIComponent(name); ` +
      `})())`;
    return `${code};\n`;
  };

  function soundFolderPlayGenerator(folder) {
    return function (block) {
      const file = valueToCodeOr(block, 'FILE', "'voorbeeld'");
      const code =
        `await BrailleStudioAPI.playUrl((() => { ` +
        `const bases = {` +
        `speech:'https://www.tastenbraille.com/braillestudio/sounds/nl/speech/',` +
        `letters:'https://www.tastenbraille.com/braillestudio/sounds/nl/letters/',` +
        `instructions:'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/',` +
        `feedback:'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback/',` +
        `story:'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',` +
        `general:'https://www.tastenbraille.com/braillestudio/sounds/general/'` +
        `}; ` +
        `const file = ${file}; ` +
        `const base = bases[${q(folder)}] || bases.speech; ` +
        `const name = String(file).toLowerCase().endsWith('.mp3') ? String(file) : String(file) + '.mp3'; ` +
        `return /^https?:\\/\\//i.test(String(file)) ? String(file) : base + encodeURIComponent(name); ` +
        `})())`;
      return `${code};\n`;
    };
  }

  javascriptGenerator.forBlock['sound_play_speech_file'] = soundFolderPlayGenerator('speech');
  javascriptGenerator.forBlock['sound_play_letters_file'] = soundFolderPlayGenerator('letters');
  javascriptGenerator.forBlock['sound_play_instructions_file'] = soundFolderPlayGenerator('instructions');
  javascriptGenerator.forBlock['sound_play_feedback_file'] = soundFolderPlayGenerator('feedback');
  javascriptGenerator.forBlock['sound_play_story_file'] = soundFolderPlayGenerator('story');
  javascriptGenerator.forBlock['sound_play_general_file'] = soundFolderPlayGenerator('general');
  javascriptGenerator.forBlock['sound_play_ux_file'] = function (block) {
    const file = valueToCodeOr(block, 'FILE', "'bounce'");
    const code =
      `await BrailleStudioAPI.playUrl((() => { ` +
      `const file = ${file}; ` +
      `const base = 'https://www.tastenbraille.com/braillestudio/sounds/ux/'; ` +
      `const name = String(file).toLowerCase().endsWith('.mp3') ? String(file) : String(file) + '.mp3'; ` +
      `return /^https?:\\/\\//i.test(String(file)) ? String(file) : base + encodeURIComponent(name); ` +
      `})())`;
    return `${code};\n`;
  };
  javascriptGenerator.forBlock['sound_play_ux_success'] = function () {
    return `await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/ux/success.mp3');\n`;
  };
  javascriptGenerator.forBlock['sound_play_ux_failure'] = function () {
    return `await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/ux/failure.mp3');\n`;
  };
  javascriptGenerator.forBlock['sound_play_instruction_by_id'] = function (block) {
    const instructionId = q(block.getFieldValue('INSTRUCTION_ID') || '');
    return `await BrailleStudioAPI.playInstructionById(${instructionId});\n`;
  };
  javascriptGenerator.forBlock['sound_play_instruction_by_id_with_phoneme'] = function (block) {
    const instructionId = q(block.getFieldValue('INSTRUCTION_ID') || '');
    const phonemeCode = valueToCodeOr(block, 'PHONEME', "''");
    return `await BrailleStudioAPI.playInstructionById(${instructionId}, { phoneme: ${phonemeCode} });\n`;
  };
  javascriptGenerator.forBlock['instruction_get_info_by_id'] = function (block) {
    const instructionId = q(block.getFieldValue('INSTRUCTION_ID') || '');
    return [`({ ok: true, item: await BrailleStudioAPI.getInstructionById(${instructionId}) })`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['controls_for_each_audio_item'] = function (block, generator) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const varName = getGeneratorVariableName(block, generator, 'VAR', 'item');
    const branch = generator.statementToCode(block, 'DO');
    return `for (const ${varName} of (Array.isArray(${listCode}) ? ${listCode} : [])) {\n${branch}}\n`;
  };

  javascriptGenerator.forBlock['list_for_each_item'] = function (block, generator) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const varName = getGeneratorVariableName(block, generator, 'VAR', 'item');
    const branch = generator.statementToCode(block, 'DO');
    return `for (const ${varName} of (Array.isArray(${listCode}) ? ${listCode} : [])) {\n${branch}}\n`;
  };

  javascriptGenerator.forBlock['text_join_csv'] = function (block) {
    const a = valueToCodeOr(block, 'A', "''");
    const b = valueToCodeOr(block, 'B', "''");
    const c = valueToCodeOr(block, 'C', "''");
    return [`BrailleStudioAPI.joinCsv([${a}, ${b}, ${c}])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['text_from_list'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    const separatorCode = valueToCodeOr(block, 'SEPARATOR', "' '");
    return [
      `((Array.isArray(${listCode}) ? ${listCode} : []).map(item => String(item ?? '')).join(String(${separatorCode} ?? ' ')))`,
      ORDER_ATOMIC
    ];
  };

  javascriptGenerator.forBlock['text_first_letter'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [`(() => { const chars = Array.from(String(${textCode} ?? '')); return chars[0] ?? ''; })()`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['text_last_letter'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [`(() => { const chars = Array.from(String(${textCode} ?? '')); return chars.length ? chars[chars.length - 1] : ''; })()`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['text_lowercase'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [`String(${textCode} ?? '').toLowerCase()`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['text_uppercase'] = function (block) {
    const textCode = valueToCodeOr(block, 'TEXT', "''");
    return [`String(${textCode} ?? '').toUpperCase()`, ORDER_ATOMIC];
  };
})();
