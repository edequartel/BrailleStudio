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

  javascriptGenerator.forBlock['api_get_audio_list'] = function (block) {
    const folder = q(block.getFieldValue('FOLDER') || 'speech');
    const letters = q(block.getFieldValue('LETTERS') || '');
    const klanken = q(block.getFieldValue('KLANKEN') || '');
    const onlyletters = q(block.getFieldValue('ONLYLETTERS') || '');
    const onlyklanken = q(block.getFieldValue('ONLYKLANKEN') || '');
    const onlycombo = block.getFieldValue('ONLYCOMBO') === 'TRUE' ? 'true' : 'false';
    const maxlength = q(block.getFieldValue('MAXLENGTH') || '');
    const length = q(block.getFieldValue('LENGTH') || '');
    const limit = q(block.getFieldValue('LIMIT') || '');
    const randomlimit = q(block.getFieldValue('RANDOMLIMIT') || '');
    const sort = q(block.getFieldValue('SORT') || '');

    const code =
      `await BrailleStudioAPI.getAudioList({` +
      `folder:${folder},letters:${letters},klanken:${klanken},onlyletters:${onlyletters},` +
      `onlyklanken:${onlyklanken},onlycombo:${onlycombo},maxlength:${maxlength},length:${length},` +
      `limit:${limit},randomlimit:${randomlimit},sort:${sort}` +
      `})`;
    return [code, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['list_pick_random'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [`BrailleStudioAPI.pickRandom(${listCode})`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['list_length'] = function (block) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    return [`(Array.isArray(${listCode}) ? ${listCode}.length : 0)`, ORDER_ATOMIC];
  };

  javascriptGenerator.forBlock['math_random_10'] = function (block) {
    const maxCode = valueToCodeOr(block, 'MAX', '10');
    return [`(() => { const max = Math.floor(Number(${maxCode}) || 0); return max <= 0 ? 0 : Math.floor(Math.random() * max); })()`, ORDER_ATOMIC];
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
      `letters:'https://www.tastenbraille.com/braillestudio/sounds/nl/alfabet/',` +
      `instructions:'https://www.tastenbraille.com/braillestudio/sounds/nl/instructions/',` +
      `feedback:'https://www.tastenbraille.com/braillestudio/sounds/nl/feedback/',` +
      `story:'https://www.tastenbraille.com/braillestudio/sounds/nl/stories/',` +
      `general:'https://www.tastenbraille.com/braillestudio/sounds/shared/'` +
      `}; ` +
      `const folder = ${folder}; ` +
      `const file = ${file}; ` +
      `const base = bases[folder] || bases.speech; ` +
      `const name = String(file).toLowerCase().endsWith('.mp3') ? String(file) : String(file) + '.mp3'; ` +
      `return /^https?:\\/\\//i.test(String(file)) ? String(file) : base + encodeURIComponent(name); ` +
      `})())`;
    return `${code};\n`;
  };

  javascriptGenerator.forBlock['controls_for_each_audio_item'] = function (block, generator) {
    const listCode = valueToCodeOr(block, 'LIST', '[]');
    let varName = 'item';
    if (typeof generator.getVariableName === 'function') {
      varName = generator.getVariableName(block.getFieldValue('VAR'));
    } else if (generator.nameDB_ && typeof generator.nameDB_.getName === 'function') {
      varName = generator.nameDB_.getName(block.getFieldValue('VAR'), Blockly.VARIABLE_CATEGORY_NAME);
    }
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
