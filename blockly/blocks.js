(function () {
  if (!window.Blockly) return;

  if (!Blockly.Blocks['api_get_audio_list']) {
    Blockly.Blocks['api_get_audio_list'] = {
      init: function () {
        this.appendDummyInput()
          .appendField('get audio list');
        this.appendDummyInput()
          .appendField('folder')
          .appendField(new Blockly.FieldDropdown([
            ['woorden', 'woorden'],
            ['letters', 'letters'],
            ['instructies', 'instructies'],
            ['beloningen', 'beloningen'],
            ['story', 'story']
          ]), 'FOLDER');
        this.appendDummyInput()
          .appendField('starts with letters')
          .appendField(new Blockly.FieldTextInput(''), 'LETTERS');
        this.appendDummyInput()
          .appendField('contains klanken')
          .appendField(new Blockly.FieldTextInput(''), 'KLANKEN');
        this.appendDummyInput()
          .appendField('only letters')
          .appendField(new Blockly.FieldTextInput(''), 'ONLYLETTERS');
        this.appendDummyInput()
          .appendField('only klanken')
          .appendField(new Blockly.FieldTextInput(''), 'ONLYKLANKEN');
        this.appendDummyInput()
          .appendField('strict letter+klank combo')
          .appendField(new Blockly.FieldCheckbox('FALSE'), 'ONLYCOMBO');
        this.appendDummyInput()
          .appendField('max length')
          .appendField(new Blockly.FieldTextInput(''), 'MAXLENGTH');
        this.appendDummyInput()
          .appendField('exact length')
          .appendField(new Blockly.FieldTextInput(''), 'LENGTH');
        this.appendDummyInput()
          .appendField('limit')
          .appendField(new Blockly.FieldTextInput(''), 'LIMIT');
        this.appendDummyInput()
          .appendField('random limit')
          .appendField(new Blockly.FieldTextInput(''), 'RANDOMLIMIT');
        this.appendDummyInput()
          .appendField('sort')
          .appendField(new Blockly.FieldDropdown([
            ['(none)', ''],
            ['asc', 'asc'],
            ['desc', 'desc'],
            ['random', 'random']
          ]), 'SORT');
        this.setOutput(true, null);
        this.setColour('#0EA5E9');
        this.setTooltip('Fetch audio items from the BrailleStudio PHP API.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['list_pick_random']) {
    Blockly.Blocks['list_pick_random'] = {
      init: function () {
        this.appendValueInput('LIST')
          .setCheck(null)
          .appendField('pick random item from list');
        this.setOutput(true, null);
        this.setColour('#F97316');
        this.setTooltip('Return one random item from a list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['audio_item_get_word']) {
    Blockly.Blocks['audio_item_get_word'] = {
      init: function () {
        this.appendValueInput('ITEM')
          .setCheck(null)
          .appendField('audio item word');
        this.setOutput(true, 'String');
        this.setColour('#14B8A6');
        this.setTooltip('Get item.word from an audio item object.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['audio_item_get_url']) {
    Blockly.Blocks['audio_item_get_url'] = {
      init: function () {
        this.appendValueInput('ITEM')
          .setCheck(null)
          .appendField('audio item url');
        this.setOutput(true, 'String');
        this.setColour('#14B8A6');
        this.setTooltip('Get item.url from an audio item object.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['controls_for_each_audio_item']) {
    Blockly.Blocks['controls_for_each_audio_item'] = {
      init: function () {
        this.appendValueInput('LIST')
          .setCheck(null)
          .appendField('for each audio item in');
        this.appendDummyInput()
          .appendField('item')
          .appendField(new Blockly.FieldVariable('item'), 'VAR');
        this.appendStatementInput('DO')
          .appendField('do');
        this.setPreviousStatement(true, null);
        this.setNextStatement(true, null);
        this.setColour('#D97706');
        this.setTooltip('Loop over every item in an audio list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['list_length']) {
    Blockly.Blocks['list_length'] = {
      init: function () {
        this.appendValueInput('LIST')
          .setCheck(null)
          .appendField('list length');
        this.setOutput(true, 'Number');
        this.setColour('#F97316');
        this.setTooltip('Return the number of items in a list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['sound_play_url']) {
    Blockly.Blocks['sound_play_url'] = {
      init: function () {
        this.appendValueInput('URL')
          .setCheck(null)
          .appendField('play sound URL');
        this.setPreviousStatement(true, null);
        this.setNextStatement(true, null);
        this.setColour('#10B981');
        this.setTooltip('Play sound from a URL.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['text_join_csv']) {
    Blockly.Blocks['text_join_csv'] = {
      init: function () {
        this.appendValueInput('A')
          .setCheck(null)
          .appendField('join csv part 1');
        this.appendValueInput('B')
          .setCheck(null)
          .appendField('part 2');
        this.appendValueInput('C')
          .setCheck(null)
          .appendField('part 3');
        this.setOutput(true, 'String');
        this.setColour('#0891B2');
        this.setTooltip('Join text parts into comma-separated text and skip empty parts.');
        this.setHelpUrl('');
      }
    };
  }
})();
