(function () {
  if (!window.Blockly) return;

  [
    'text',
    'text_join'
  ].forEach((type) => {
    const block = Blockly.Blocks[type];
    if (!block || typeof block.init !== 'function') return;
    const originalInit = block.init;
    block.init = function patchedTextInit(...args) {
      originalInit.apply(this, args);
      this.setColour('#0891B2');
    };
  });

  function attachDefaultShadow(block, inputName, shadowType, fieldValues = {}) {
    setTimeout(() => {
      try {
        if (!block || block.isDisposed?.()) return;
        const workspace = block.workspace;
        const input = block.getInput(inputName);
        const connection = input?.connection;
        if (!workspace || !connection || connection.targetBlock()) return;
        const fieldsXml = Object.entries(fieldValues)
          .map(([name, value]) => `<field name="${String(name)}">${String(value)}</field>`)
          .join('');
        const shadowXml = Blockly.utils.xml.textToDom(`<shadow type="${shadowType}">${fieldsXml}</shadow>`);
        const shadowBlock = Blockly.Xml.domToBlock(shadowXml, workspace);
        if (!shadowBlock) return;
        shadowBlock.setShadow(true);
        if (shadowBlock.outputConnection) {
          connection.connect(shadowBlock.outputConnection);
        }
      } catch {}
    }, 0);
  }

  const INSTRUCTIONS_API_LIST_URLS = [
    'https://www.tastenbraille.com/braillestudio/instructions-api/instructions_list.php?status=active'
  ];
  const INSTRUCTION_DROPDOWN_LOADING = '__loading__';
  const INSTRUCTION_DROPDOWN_ERROR = '__error__';
  const INSTRUCTION_DROPDOWN_EMPTY = '__empty__';
  const initialCatalogItems = Array.isArray(window.BrailleStudioInstructionCatalog)
    ? window.BrailleStudioInstructionCatalog
    : [];
  const instructionDropdownState = {
    loading: false,
    loaded: initialCatalogItems.length > 0,
    options: initialCatalogItems.length > 0
      ? normalizeInitialInstructionOptions(initialCatalogItems)
      : [['loading instructions...', INSTRUCTION_DROPDOWN_LOADING]],
    attempts: [],
    lastError: ''
  };

  function normalizeInitialInstructionOptions(items) {
    return items
      .map((item) => {
        const id = String(item?.id ?? '').trim();
        const title = String(item?.title ?? '').trim();
        if (!id) return null;
        return [title ? `${id} - ${title}` : id, id];
      })
      .filter(Boolean);
  }

  function setInstructionDebugState(extra = {}) {
    const state = {
      loading: instructionDropdownState.loading,
      loaded: instructionDropdownState.loaded,
      options: instructionDropdownState.options,
      attempts: instructionDropdownState.attempts,
      lastError: instructionDropdownState.lastError,
      urls: INSTRUCTIONS_API_LIST_URLS,
      ...extra
    };
    window.BrailleBlocklyInstructionDebug = state;
    return state;
  }

  function logInstructionDebug(message, details = null) {
    const text = details == null
      ? `[Instruction block] ${message}`
      : `[Instruction block] ${message} ${typeof details === 'string' ? details : JSON.stringify(details)}`;
    if (typeof window.BrailleBlocklyLog === 'function') {
      window.BrailleBlocklyLog(text);
    }
    try {
      console.log(text);
    } catch {}
  }

  function buildInstructionListCandidates() {
    return [INSTRUCTIONS_API_LIST_URLS[0]];
  }

  function normalizeInstructionDropdownItems(items) {
    if (!Array.isArray(items) || items.length === 0) {
      return [['no instructions found', INSTRUCTION_DROPDOWN_EMPTY]];
    }
    return items
      .map((item) => {
        const id = String(item?.id ?? '').trim();
        const title = String(item?.title ?? '').trim();
        if (!id) return null;
        return [title ? `${id} - ${title}` : id, id];
      })
      .filter(Boolean);
  }

  function getCatalogFallbackItems() {
    return initialCatalogItems;
  }

  function refreshInstructionDropdownBlocks() {
    const workspaces = typeof Blockly.Workspace?.getAll === 'function'
      ? Blockly.Workspace.getAll()
      : [];
    workspaces.forEach((workspace) => {
      const blocks = typeof workspace.getAllBlocks === 'function'
        ? workspace.getAllBlocks(false)
        : [];
      blocks.forEach((block) => {
        if (block.type !== 'sound_play_instruction_by_id') return;
        const field = block.getField('INSTRUCTION_ID');
        if (!field || typeof field.setValue !== 'function') return;
        const currentValue = String(field.getValue?.() ?? '');
        const hasCurrentValue = instructionDropdownState.options.some(([, value]) => value === currentValue);
        if (hasCurrentValue) return;
        const fallbackValue = instructionDropdownState.options[0]?.[1] ?? '';
        field.setValue(fallbackValue);
      });
    });
  }

  async function loadInstructionDropdownOptions() {
    if (instructionDropdownState.loading) return;
    instructionDropdownState.loading = true;
    instructionDropdownState.attempts = [];
    instructionDropdownState.lastError = '';
    const candidateUrls = buildInstructionListCandidates();
    setInstructionDebugState({ stage: 'loading' });
    logInstructionDebug('loading instruction list', { urls: candidateUrls });
    try {
      let lastError = null;
      let data = null;
      for (const url of candidateUrls) {
        try {
          logInstructionDebug('trying list URL', url);
          const response = await fetch(url, { cache: 'no-store' });
          if (!response.ok) {
            throw new Error(`HTTP ${response.status} ${response.statusText}`);
          }
          data = await response.json();
          instructionDropdownState.attempts.push({
            url,
            ok: true,
            count: Array.isArray(data?.items) ? data.items.length : (Array.isArray(data) ? data.length : null)
          });
          logInstructionDebug('loaded list URL', {
            url,
            count: Array.isArray(data?.items) ? data.items.length : (Array.isArray(data) ? data.length : null),
            shape: Array.isArray(data) ? 'array' : typeof data
          });
          break;
        } catch (err) {
          const message = err && err.message ? err.message : String(err);
          instructionDropdownState.attempts.push({ url, ok: false, error: message });
          logInstructionDebug('list URL failed', { url, error: message });
          lastError = err;
        }
      }
      if (!data) {
        const fallbackItems = getCatalogFallbackItems();
        if (fallbackItems.length > 0) {
          instructionDropdownState.options = normalizeInstructionDropdownItems(fallbackItems);
          logInstructionDebug('using local instruction catalog fallback', {
            count: fallbackItems.length,
            ids: fallbackItems.map(item => item?.id).filter(Boolean)
          });
          setInstructionDebugState({ stage: 'fallback', items: fallbackItems });
          return;
        }
        throw lastError || new Error('No instructions API endpoint responded');
      }
      const items = Array.isArray(data) ? data : data?.items;
      instructionDropdownState.options = normalizeInstructionDropdownItems(items);
      logInstructionDebug('instruction list ready', {
        optionCount: instructionDropdownState.options.length,
        firstOption: instructionDropdownState.options[0]?.[0] || ''
      });
      setInstructionDebugState({ stage: 'loaded', items });
    } catch (err) {
      const message = err && err.message ? err.message : String(err);
      instructionDropdownState.lastError = message;
      logInstructionDebug('failed to load instruction list', {
        message,
        attempts: instructionDropdownState.attempts
      });
      console.error('Failed to load Blockly instruction list', {
        message,
        attempts: instructionDropdownState.attempts
      });
      const shortMessage = message.length > 42 ? `${message.slice(0, 42)}...` : message;
      instructionDropdownState.options = [[`failed: ${shortMessage}`, INSTRUCTION_DROPDOWN_ERROR]];
      setInstructionDebugState({ stage: 'failed', error: message });
    } finally {
      instructionDropdownState.loaded = true;
      instructionDropdownState.loading = false;
      setInstructionDebugState({ stage: instructionDropdownState.lastError ? 'failed' : 'loaded' });
      refreshInstructionDropdownBlocks();
    }
  }

  function getInstructionDropdownOptions() {
    return instructionDropdownState.options;
  }

  if (initialCatalogItems.length > 0) {
    logInstructionDebug('using preloaded instruction catalog', {
      count: initialCatalogItems.length,
      ids: initialCatalogItems.map(item => item?.id).filter(Boolean)
    });
    setInstructionDebugState({ stage: 'preloaded', items: initialCatalogItems });
  } else {
    setTimeout(() => {
      loadInstructionDropdownOptions();
    }, 0);
  }

  Blockly.Blocks['event_when_started'] = {
    init() {
      this.appendDummyInput().appendField('when started');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_program_ended'] = {
    init() {
      this.appendDummyInput().appendField('when program ended');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_timer'] = {
    init() {
      this.appendDummyInput()
        .appendField('when timer')
        .appendField(new Blockly.FieldTextInput('timer1'), 'NAME')
        .appendField('ticks');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_thumb_key'] = {
    init() {
      this.appendDummyInput()
        .appendField('when thumb key')
        .appendField(new Blockly.FieldDropdown([
          ['left', 'left'],
          ['right', 'right'],
          ['up', 'up'],
          ['down', 'down']
        ]), 'KEY')
        .appendField('pressed');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_any_thumb_key'] = {
    init() {
      this.appendDummyInput().appendField('when any thumb key pressed');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_cursor_routing'] = {
    init() {
      this.appendValueInput('CELL')
        .setCheck('Number')
        .appendField('when cursor routing cell');
      this.appendStatementInput('DO').appendField('do');
      this.setInputsInline(true);
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
      attachDefaultShadow(this, 'CELL', 'math_number', { NUM: 0 });
    }
  };

  Blockly.Blocks['event_when_cursor_position_changed'] = {
    init() {
      this.appendDummyInput().appendField('when cursor position changed');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['event_when_chord'] = {
    init() {
      this.appendValueInput('DOTS')
        .appendField('when chord')
        .appendField('received');
      this.appendStatementInput('DO').appendField('do');
      this.setInputsInline(true);
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
      attachDefaultShadow(this, 'DOTS', 'text', { TEXT: '1,2,3,7' });
    }
  };

  Blockly.Blocks['event_when_editor_key'] = {
    init() {
      this.appendDummyInput()
        .appendField('when editor key')
        .appendField(new Blockly.FieldDropdown([
          ['Backspace', 'Backspace'],
          ['Delete', 'Delete'],
          ['Enter', 'Enter'],
          ['ArrowLeft', 'ArrowLeft'],
          ['ArrowRight', 'ArrowRight'],
          ['ArrowUp', 'ArrowUp'],
          ['ArrowDown', 'ArrowDown'],
          ['Home', 'Home'],
          ['End', 'End']
        ]), 'KEY')
        .appendField('received');
      this.appendStatementInput('DO').appendField('do');
      this.setColour('#F59E0B');
      this.setPreviousStatement(false);
      this.setNextStatement(false);
    }
  };

  Blockly.Blocks['bb_set_text'] = {
    init() {
      this.appendValueInput('TEXT').appendField('replace text with');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_append_text'] = {
    init() {
      this.appendValueInput('TEXT').appendField('append text');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_send_key'] = {
    init() {
      this.appendDummyInput()
        .appendField('send key')
        .appendField(new Blockly.FieldDropdown([
          ['Backspace', 'Backspace'],
          ['Delete', 'Delete'],
          ['Space', 'Space'],
          ['Enter', 'Enter'],
          ['ArrowLeft', 'ArrowLeft'],
          ['ArrowRight', 'ArrowRight'],
          ['ArrowUp', 'ArrowUp'],
          ['ArrowDown', 'ArrowDown']
        ]), 'KEY');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_move_caret'] = {
    init() {
      this.appendValueInput('DELTA').appendField('move caret by');
      this.appendDummyInput()
        .appendField('unit')
        .appendField(new Blockly.FieldDropdown([
          ['character', 'character'],
          ['cell', 'cell']
        ]), 'UNIT');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_caret'] = {
    init() {
      this.appendValueInput('INDEX').appendField('set caret text index');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_caret_from_cell'] = {
    init() {
      this.appendValueInput('INDEX').appendField('set caret from cell');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_cursor_routing'] = {
    init() {
      this.appendValueInput('INDEX').appendField('simulate cursor routing cell');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_editor_mode'] = {
    init() {
      this.appendDummyInput()
        .appendField('set editor mode')
        .appendField(new Blockly.FieldDropdown([
          ['on', 'on'],
          ['off', 'off']
        ]), 'MODE');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_insert_mode'] = {
    init() {
      this.appendDummyInput()
        .appendField('set insert mode')
        .appendField(new Blockly.FieldDropdown([
          ['on', 'on'],
          ['off', 'off']
        ]), 'MODE');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_caret_visibility'] = {
    init() {
      this.appendDummyInput()
        .appendField('set caret visible')
        .appendField(new Blockly.FieldDropdown([
          ['true', 'true'],
          ['false', 'false']
        ]), 'VISIBLE');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_get_braille_line'] = {
    init() {
      this.appendDummyInput().appendField('get braille line');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_current_text'] = {
    init() {
      this.appendDummyInput().appendField('current braille line text');
      this.setOutput(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_current_braille_unicode'] = {
    init() {
      this.appendDummyInput().appendField('current braille unicode');
      this.setOutput(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_letter_under_cursor'] = {
    init() {
      this.appendDummyInput().appendField('letter under cursor');
      this.setOutput(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_word_under_cursor'] = {
    init() {
      this.appendDummyInput().appendField('word under cursor');
      this.setOutput(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_caret_to_begin'] = {
    init() {
      this.appendDummyInput().appendField('set caret to begin');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_set_caret_to_end'] = {
    init() {
      this.appendDummyInput().appendField('set caret to end');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#2563EB');
    }
  };

  Blockly.Blocks['bb_wait'] = {
    init() {
      this.appendValueInput('SECONDS').appendField('wait seconds');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['bb_wait_ms'] = {
    init() {
      this.appendValueInput('MILLISECONDS').appendField('wait milliseconds');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['controls_while_do'] = {
    init() {
      this.appendValueInput('COND').appendField('while');
      this.appendStatementInput('DO').appendField('do');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#D97706');
    }
  };

  Blockly.Blocks['controls_do_while'] = {
    init() {
      this.appendStatementInput('DO').appendField('do');
      this.appendValueInput('COND').appendField('while');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#D97706');
    }
  };

  Blockly.Blocks['math_random_10'] = {
    init() {
      this.appendValueInput('MAX').appendField('random max');
      this.setOutput(true);
      this.setColour('#C026D3');
    }
  };

  Blockly.Blocks['math_inc_var'] = {
    init() {
      this.appendDummyInput()
        .appendField('inc')
        .appendField(new Blockly.FieldVariable('score'), 'VAR');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#C026D3');
    }
  };

  Blockly.Blocks['math_dec_var'] = {
    init() {
      this.appendDummyInput()
        .appendField('dec')
        .appendField(new Blockly.FieldVariable('score'), 'VAR');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#C026D3');
    }
  };

  Blockly.Blocks['log_value'] = {
    init() {
      this.appendValueInput('VALUE').appendField('log');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#64748B');
    }
  };

  Blockly.Blocks['log_variable'] = {
    init() {
      this.appendDummyInput()
        .appendField('log variable')
        .appendField(new Blockly.FieldVariable('score'), 'VAR');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#64748B');
    }
  };

  Blockly.Blocks['log_clear'] = {
    init() {
      this.appendDummyInput().appendField('clear log');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#64748B');
    }
  };

  [
    'math_number',
    'math_arithmetic',
    'math_single',
    'math_round',
    'math_trig',
    'math_constant',
    'math_number_property',
    'math_on_list',
    'math_modulo',
    'math_constrain',
    'math_random_int',
    'math_random_float'
  ].forEach((type) => {
    const block = Blockly.Blocks[type];
    if (!block || typeof block.init !== 'function') return;
    const originalInit = block.init;
    block.init = function patchedMathInit(...args) {
      originalInit.apply(this, args);
      this.setColour('#C026D3');
    };
  });

  ['controls_repeat_ext'].forEach((type) => {
    const block = Blockly.Blocks[type];
    if (!block || typeof block.init !== 'function') return;
    const originalInit = block.init;
    block.init = function patchedLoopInit(...args) {
      originalInit.apply(this, args);
      this.setColour('#D97706');
    };
  });

  Blockly.Blocks['sound_play_folder_file'] = {
    init() {
      this.appendDummyInput()
        .appendField('play sound from')
        .appendField(new Blockly.FieldDropdown([
          ['speech', 'speech'],
          ['letters', 'letters'],
          ['instructions', 'instructions'],
          ['feedback', 'feedback'],
          ['story', 'story'],
          ['general', 'general']
        ]), 'FOLDER');
      this.appendValueInput('FILE').appendField('file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_speech_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play speech file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_letters_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play letters file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_instructions_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play instructions file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_instruction_by_id'] = {
    init() {
      this.appendDummyInput()
        .appendField('play instruction')
        .appendField(new Blockly.FieldDropdown(getInstructionDropdownOptions), 'INSTRUCTION_ID');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_instruction_by_id_with_phoneme'] = {
    init() {
      this.appendDummyInput()
        .appendField('play instruction')
        .appendField(new Blockly.FieldDropdown(getInstructionDropdownOptions), 'INSTRUCTION_ID');
      this.appendValueInput('PHONEME').appendField('phoneme optional');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['instruction_get_info_by_id'] = {
    init() {
      this.appendDummyInput()
        .appendField('instruction info')
        .appendField(new Blockly.FieldDropdown(getInstructionDropdownOptions), 'INSTRUCTION_ID');
      this.setOutput(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_feedback_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play feedback file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_story_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play story file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_general_file'] = {
    init() {
      this.appendValueInput('FILE').appendField('play general file');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_play_url'] = {
    init() {
      this.appendValueInput('URL').appendField('play sound url');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_pause'] = {
    init() {
      this.appendDummyInput().appendField('pause sound');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_resume'] = {
    init() {
      this.appendDummyInput().appendField('resume sound');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_stop'] = {
    init() {
      this.appendDummyInput().appendField('stop sound');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_wait_stopped'] = {
    init() {
      this.appendDummyInput().appendField('wait for audio stopped');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['sound_set_volume'] = {
    init() {
      this.appendValueInput('VOLUME').appendField('set sound volume %');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#10B981');
    }
  };

  Blockly.Blocks['list_make'] = {
    init() {
      this.itemCount_ = 2;
      this.appendDummyInput('HEAD')
        .appendField('maak lijst met')
        .appendField(new Blockly.FieldNumber(2, 0, 20, 1, (newValue) => {
          const n = Math.max(0, Math.min(20, Math.floor(Number(newValue) || 0)));
          this.itemCount_ = n;
          this.updateShape_();
          return n;
        }), 'COUNT');
      this.updateShape_();
      this.setOutput(true);
      this.setColour('#F97316');
    },
    mutationToDom() {
      const container = Blockly.utils.xml.createElement('mutation');
      container.setAttribute('items', String(this.itemCount_));
      return container;
    },
    domToMutation(xmlElement) {
      this.itemCount_ = Number(xmlElement.getAttribute('items')) || 0;
      this.updateShape_();
    },
    updateShape_() {
      for (let i = 0; this.getInput('ITEM' + i); i++) {
        this.removeInput('ITEM' + i);
      }
      for (let i = 0; i < this.itemCount_; i++) {
        this.appendValueInput('ITEM' + i)
          .appendField(i === 0 ? 'item' : '');
      }
    }
  };

  Blockly.Blocks['list_empty'] = {
    init() {
      this.appendDummyInput().appendField('maak lege lijst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_get_item'] = {
    init() {
      this.appendValueInput('INDEX').appendField('haal item');
      this.appendValueInput('LIST').appendField('uit lijst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_set_item'] = {
    init() {
      this.appendValueInput('INDEX').appendField('zet item');
      this.appendValueInput('LIST').appendField('in lijst');
      this.appendValueInput('VALUE').appendField('naar');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_length'] = {
    init() {
      this.appendValueInput('LIST').appendField('lengte van lijst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_nrof_items'] = {
    init() {
      this.appendValueInput('LIST').appendField('nrof items in list');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_random_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('random item uit lijst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_pick_random'] = {
    init() {
      this.appendValueInput('LIST').appendField('pick random from list');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['list_next_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('volgend item uit lijst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_contains_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('bevat lijst');
      this.appendValueInput('ITEM').appendField('item');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_add_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('voeg toe aan lijst');
      this.appendValueInput('ITEM').appendField('item');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_remove_item'] = {
    init() {
      this.appendValueInput('INDEX').appendField('verwijder item');
      this.appendValueInput('LIST').appendField('uit lijst');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_from_json'] = {
    init() {
      this.appendValueInput('JSON').appendField('laad woordenlijst uit JSON');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_from_text_items'] = {
    init() {
      this.appendValueInput('TEXT').appendField('maak lijst van tekst');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_filter_text_length'] = {
    init() {
      this.appendValueInput('LIST').appendField('filter lijst op tekstlengte');
      this.appendValueInput('MIN').appendField('groter dan');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_filter_phoneme_category'] = {
    init() {
      this.appendValueInput('LIST')
        .appendField('filter lijst')
        .appendField(new Blockly.FieldDropdown([
          ['korte klinker', 'korteKlinker'],
          ['medeklinker', 'medeklinker'],
          ['lange klinker', 'langeKlinker'],
          ['tweetekenklank', 'tweetekenklank'],
          ['drietekenklank', 'drietekenklank'],
          ['medeklinkercluster', 'medeklinkercluster'],
          ['viertekenklank', 'viertekenklank']
        ]), 'CATEGORY');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_filter_phoneme_categories'] = {
    init() {
      this.appendValueInput('LIST').appendField('filter lijst categorieen');
      this.appendDummyInput()
        .appendField('korte')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'KORTEKLINKER')
        .appendField('lange')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'LANGEKLINKER')
        .appendField('tweeteken')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'TWEETEKENKLANK');
      this.appendDummyInput()
        .appendField('drieteken')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'DRIETEKENKLANK')
        .appendField('medeklinker')
        .appendField(new Blockly.FieldCheckbox('TRUE'), 'MEDEKLINKER')
        .appendField('cluster')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'MEDEKLINKERCLUSTER');
      this.appendDummyInput()
        .appendField('vierteken')
        .appendField(new Blockly.FieldCheckbox('FALSE'), 'VIERTEKENKLANK');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_shuffle'] = {
    init() {
      this.appendValueInput('LIST').appendField('shuffle list');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_sort'] = {
    init() {
      this.appendValueInput('LIST')
        .appendField('sort list')
        .appendField(new Blockly.FieldDropdown([
          ['ascending', 'ASC'],
          ['descending', 'DESC']
        ]), 'ORDER');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_sort_by_length'] = {
    init() {
      this.appendValueInput('LIST')
        .appendField('sort list by length')
        .appendField(new Blockly.FieldDropdown([
          ['short to long', 'ASC'],
          ['long to short', 'DESC']
        ]), 'ORDER');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_random_other_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('random ander item uit lijst');
      this.appendValueInput('EXCLUDE').appendField('dan');
      this.setOutput(true);
      this.setColour('#F97316');
    }
  };

  Blockly.Blocks['list_for_each_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('voor elk item in lijst');
      this.appendStatementInput('DO').appendField('doe');
      this.appendDummyInput()
        .appendField('als')
        .appendField(new Blockly.FieldVariable('item'), 'VAR');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#D97706');
    }
  };

  Blockly.Blocks['audio_item_get_word'] = {
    init() {
      this.appendValueInput('ITEM').appendField('word of audio item');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['audio_item_get_url'] = {
    init() {
      this.appendValueInput('ITEM').appendField('url of audio item');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['klanken_get_aanvankelijklijst'] = {
    init() {
      this.appendDummyInput().appendField('haal aanvankelijklijst op');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_set_active_record_index'] = {
    init() {
      this.appendValueInput('INDEX')
        .appendField('set active lesson record index');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_data'] = {
    init() {
      this.appendDummyInput().appendField('lesson data');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_record_count'] = {
    init() {
      this.appendDummyInput().appendField('lesson record count');
      this.setOutput(true, 'Number');
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_active_record'] = {
    init() {
      this.appendDummyInput().appendField('active lesson record');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_active_field'] = {
    init() {
      this.appendDummyInput()
        .appendField('active lesson field')
        .appendField(new Blockly.FieldDropdown([
          ['word', 'word'],
          ['sounds', 'sounds'],
          ['new sounds', 'newSounds'],
          ['known sounds', 'knownSounds'],
          ['categories', 'categories'],
          ['new sound categories', 'newSoundCategories'],
          ['known sound categories', 'knownSoundCategories']
        ]), 'FIELD');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_active_category'] = {
    init() {
      this.appendDummyInput()
        .appendField('active lesson')
        .appendField(new Blockly.FieldDropdown([
          ['categories', 'categories'],
          ['new sound categories', 'newSoundCategories'],
          ['known sound categories', 'knownSoundCategories']
        ]), 'SOURCE')
        .appendField(new Blockly.FieldDropdown([
          ['korte klinkers', 'korteKlinkers'],
          ['lange klinkers', 'langeKlinkers'],
          ['tweetekenklanken', 'tweetekenklanken'],
          ['medeklinkers', 'medeklinkers'],
          ['medeklinkerclusters', 'medeklinkerclusters'],
          ['drietekenklanken', 'drietekenklanken']
        ]), 'CATEGORY');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_get_step_input'] = {
    init() {
      this.appendDummyInput()
        .appendField('lesson step input')
        .appendField(new Blockly.FieldDropdown([
          ['text', 'text'],
          ['word', 'word'],
          ['letters', 'letters']
        ]), 'FIELD');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['lesson_complete_step'] = {
    init() {
      this.appendDummyInput()
        .appendField('complete lesson step as')
        .appendField(new Blockly.FieldDropdown([
          ['completed', 'completed'],
          ['retry', 'retry'],
          ['failed', 'failed']
        ]), 'STATUS');
      this.appendValueInput('OUTPUT').appendField('output');
      this.appendValueInput('SCORE').appendField('score');
      this.appendValueInput('MAX_SCORE').appendField('max score');
      this.appendValueInput('ATTEMPTS').appendField('attempts');
      this.appendValueInput('DURATION_MS').appendField('duration ms');
      this.appendValueInput('ANSWER').appendField('answer');
      this.appendValueInput('EXPECTED_ANSWER').appendField('expected answer');
      this.appendValueInput('FEEDBACK').appendField('feedback');
      this.appendValueInput('METADATA').appendField('metadata');
      this.setPreviousStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_get_speech_audio_by_onlyletters'] = {
    init() {
      this.appendValueInput('ONLYLETTERS')
        .appendField('get speech audio list onlyletters');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['audio_get_speech_audio_by_letters_klanken'] = {
    init() {
      this.appendValueInput('LETTERS')
        .appendField('get speech audio list letters');
      this.appendValueInput('KLANKEN')
        .appendField('with phonemes');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['audio_get_speech_audio_by_onlyletters_klanken_length'] = {
    init() {
      this.appendValueInput('ONLYLETTERS')
        .appendField('get speech audio list only letters');
      this.appendValueInput('KLANKEN')
        .appendField('with phonemes');
      this.appendValueInput('LENGTH')
        .appendField('and length');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['audio_get_speech_audio_by_onlyletters_length'] = {
    init() {
      this.appendValueInput('ONLYLETTERS')
        .appendField('get speech audio list only letters');
      this.appendValueInput('LENGTH')
        .appendField('and length');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['controls_for_each_audio_item'] = {
    init() {
      this.appendValueInput('LIST').appendField('for each audio item in');
      this.appendStatementInput('DO').appendField('do');
      this.appendDummyInput()
        .appendField('as')
        .appendField(new Blockly.FieldVariable('item'), 'VAR');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#D97706');
    }
  };

  Blockly.Blocks['text_join_csv'] = {
    init() {
      this.appendValueInput('A').appendField('join csv');
      this.appendValueInput('B').appendField('with');
      this.appendValueInput('C').appendField('and');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['text_from_list'] = {
    init() {
      this.appendValueInput('LIST').appendField('maak tekst van lijst');
      this.appendValueInput('SEPARATOR').appendField('gescheiden door');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['text_first_letter'] = {
    init() {
      this.appendValueInput('TEXT').appendField('first letter of');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['text_last_letter'] = {
    init() {
      this.appendValueInput('TEXT').appendField('last letter of');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['text_lowercase'] = {
    init() {
      this.appendValueInput('TEXT').appendField('lowercase');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['text_uppercase'] = {
    init() {
      this.appendValueInput('TEXT').appendField('uppercase');
      this.setOutput(true);
      this.setColour('#0891B2');
    }
  };

  Blockly.Blocks['klanken_word_get_sounds'] = {
    init() {
      this.appendValueInput('WORD').appendField('sounds of word');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_word_get_new_sounds'] = {
    init() {
      this.appendValueInput('WORD').appendField('new sounds of word');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_word_get_known_sounds'] = {
    init() {
      this.appendValueInput('WORD').appendField('known sounds of word');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_play_word_sounds'] = {
    init() {
      this.appendValueInput('WORD').appendField('play sounds of word');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_play_word_sounds_with_pause'] = {
    init() {
      this.appendValueInput('WORD').appendField('play sounds of word');
      this.appendValueInput('SECONDS').appendField('wait seconds between');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_split_word_phonemes_nl'] = {
    init() {
      this.appendValueInput('WORD').appendField('split word into phonemes (nl)');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_split_text_phonemes_nl'] = {
    init() {
      this.appendValueInput('TEXT').appendField('split text into phonemes (nl)');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_play_word_phonemes_nl'] = {
    init() {
      this.appendValueInput('WORD').appendField('play phonemes of word (nl)');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_play_word_phonemes_nl_with_pause'] = {
    init() {
      this.appendValueInput('WORD').appendField('play phonemes of word (nl)');
      this.appendValueInput('SECONDS').appendField('wait seconds between');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_item_get_word'] = {
    init() {
      this.appendValueInput('ITEM').appendField('word of phonemes item');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_item_get_sounds'] = {
    init() {
      this.appendValueInput('ITEM')
        .appendField('phonemes from item')
        .appendField(new Blockly.FieldDropdown([
          ['alle', 'ALL'],
          ['nieuw', 'NEW'],
          ['bekend', 'KNOWN']
        ]), 'SOURCE');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['klanken_item_get_category'] = {
    init() {
      this.appendValueInput('ITEM')
        .appendField('categorie uit item')
        .appendField(new Blockly.FieldDropdown([
          ['alle', 'ALL'],
          ['nieuw', 'NEW'],
          ['bekend', 'KNOWN']
        ]), 'SOURCE')
        .appendField(new Blockly.FieldDropdown([
          ['korte klinkers', 'korteKlinkers'],
          ['lange klinkers', 'langeKlinkers'],
          ['tweetekenklanken', 'tweetekenklanken'],
          ['medeklinkers', 'medeklinkers'],
          ['medeklinkerclusters', 'medeklinkerclusters'],
          ['drietekenklanken', 'drietekenklanken']
        ]), 'CATEGORY');
      this.setOutput(true);
      this.setColour('#14B8A6');
    }
  };

  Blockly.Blocks['timer_start'] = {
    init() {
      this.appendDummyInput()
        .appendField('start timer')
        .appendField(new Blockly.FieldTextInput('timer1'), 'NAME')
        .appendField('every');
      this.appendValueInput('SECONDS').appendField('seconds');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['timer_stop'] = {
    init() {
      this.appendDummyInput()
        .appendField('stop timer')
        .appendField(new Blockly.FieldTextInput('timer1'), 'NAME');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['timer_stop_all'] = {
    init() {
      this.appendDummyInput().appendField('stop all timers');
      this.setPreviousStatement(true);
      this.setNextStatement(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['state_text_caret'] = {
    init() {
      this.appendDummyInput().appendField('text caret');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_cell_caret'] = {
    init() {
      this.appendDummyInput().appendField('cell caret');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_last_thumb_key'] = {
    init() {
      this.appendDummyInput().appendField('last thumb key');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_last_cursor_cell'] = {
    init() {
      this.appendDummyInput().appendField('last cursor cell');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_last_chord'] = {
    init() {
      this.appendDummyInput().appendField('last chord');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_last_editor_key'] = {
    init() {
      this.appendDummyInput().appendField('last editor key');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_editor_mode'] = {
    init() {
      this.appendDummyInput().appendField('editor mode');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_insert_mode'] = {
    init() {
      this.appendDummyInput().appendField('insert mode');
      this.setOutput(true);
      this.setColour('#7C3AED');
    }
  };

  Blockly.Blocks['state_last_timer_name'] = {
    init() {
      this.appendDummyInput().appendField('last timer name');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['state_last_timer_tick'] = {
    init() {
      this.appendDummyInput().appendField('last timer tick');
      this.setOutput(true);
      this.setColour('#0EA5E9');
    }
  };

  Blockly.Blocks['state_last_sound'] = {
    init() {
      this.appendDummyInput().appendField('last sound');
      this.setOutput(true);
      this.setColour('#10B981');
    }
  };
})();
