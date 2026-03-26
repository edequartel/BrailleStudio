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
    return [`await fetch('../klanken/aanvankelijklijst.json', { cache: 'no-store' }).then(res => res.json())`, ORDER_ATOMIC];
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
      `(() => {\n` +
      `  const list = Array.isArray(window.aanvankelijkData) ? window.aanvankelijkData : [];\n` +
      `  const index = Math.floor(Number(${indexCode}) || 0);\n` +
      `  const record = index >= 0 && index < list.length ? list[index] : null;\n` +
      `  window.currentRecord = record;\n` +
      `  window.currentRecordIndex = record ? index : -1;\n` +
      `})();\n`
    );
  };

  javascriptGenerator.forBlock['lesson_get_data'] = function () {
    return [`(Array.isArray(window.aanvankelijkData) ? window.aanvankelijkData : [])`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['lesson_get_active_record'] = function () {
    return [`((window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['lesson_get_active_field'] = function (block) {
    const field = q(block.getFieldValue('FIELD') || 'word');
    return [
      `(() => { const record = (window.currentRecord && typeof window.currentRecord === 'object') ? window.currentRecord : null; const key = ${field}; const value = record ? record[key] : undefined; if (value != null) return value; if (key === 'word') return ''; if (key === 'categories' || key === 'newSoundCategories' || key === 'knownSoundCategories') return {}; return []; })()`,
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

  javascriptGenerator.forBlock['klanken_word_get_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`(await fetch('../klanken/aanvankelijklijst.json', { cache: 'no-store' }).then(res => res.json()).then(list => (Array.isArray(list) ? list : [])).then(list => (list.find(item => String(item?.word ?? '').trim().toLowerCase() === String(${wordCode} ?? '').trim().toLowerCase())?.sounds ?? [])))`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_word_get_new_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`(await fetch('../klanken/aanvankelijklijst.json', { cache: 'no-store' }).then(res => res.json()).then(list => (Array.isArray(list) ? list : [])).then(list => (list.find(item => String(item?.word ?? '').trim().toLowerCase() === String(${wordCode} ?? '').trim().toLowerCase())?.newSounds ?? [])))`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_word_get_known_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return [`(await fetch('../klanken/aanvankelijklijst.json', { cache: 'no-store' }).then(res => res.json()).then(list => (Array.isArray(list) ? list : [])).then(list => (list.find(item => String(item?.word ?? '').trim().toLowerCase() === String(${wordCode} ?? '').trim().toLowerCase())?.knownSounds ?? [])))`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['klanken_play_word_sounds'] = function (block) {
    const wordCode = valueToCodeOr(block, 'WORD', "''");
    return (
      `for (const sound of (await fetch('../klanken/aanvankelijklijst.json', { cache: 'no-store' })` +
      `.then(res => res.json())` +
      `.then(list => (Array.isArray(list) ? list : []))` +
      `.then(list => (list.find(item => String(item?.word ?? '').trim().toLowerCase() === String(${wordCode} ?? '').trim().toLowerCase())?.sounds ?? [])))) {\n` +
      `  await BrailleStudioAPI.playUrl('https://www.tastenbraille.com/braillestudio/sounds/nl/letters/' + encodeURIComponent(String(sound).toLowerCase().endsWith('.mp3') ? String(sound) : String(sound) + '.mp3'));\n` +
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
    const file = q(block.getFieldValue('FILE') || 'voorbeeld');
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

  javascriptGenerator.forBlock['controls_for_each_audio_item'] = function (block, generator) {
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
})();
