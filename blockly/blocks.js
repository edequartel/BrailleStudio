(function () {
  if (!window.Blockly) return;

  if (!Blockly.Blocks['api_get_audio_list']) {
    Blockly.Blocks['api_get_audio_list'] = {
      init: function () {
        this.appendDummyInput()
          .appendField('get audio list');
        this.appendDummyInput()
          .appendField('audio folder')
          .appendField(new Blockly.FieldDropdown([
            ['spoken words', 'speech'],
            ['letters', 'letters'],
            ['instructions', 'instructions'],
            ['feedback', 'feedback'],
            ['stories', 'story'],
            ['general sounds', 'general']
          ]), 'FOLDER');
        this.appendDummyInput()
          .appendField('starts with letters')
          .appendField(new Blockly.FieldTextInput(''), 'LETTERS');
        this.appendDummyInput()
          .appendField('contains sounds')
          .appendField(new Blockly.FieldTextInput(''), 'KLANKEN');
        this.appendDummyInput()
          .appendField('use only these letters')
          .appendField(new Blockly.FieldTextInput(''), 'ONLYLETTERS');
        this.appendDummyInput()
          .appendField('use only these sounds')
          .appendField(new Blockly.FieldTextInput(''), 'ONLYKLANKEN');
        this.appendDummyInput()
          .appendField('match letters and sounds exactly')
          .appendField(new Blockly.FieldCheckbox('FALSE'), 'ONLYCOMBO');
        this.appendDummyInput()
          .appendField('maximum word length')
          .appendField(new Blockly.FieldTextInput(''), 'MAXLENGTH');
        this.appendDummyInput()
          .appendField('exact word length')
          .appendField(new Blockly.FieldTextInput(''), 'LENGTH');
        this.appendDummyInput()
          .appendField('maximum items')
          .appendField(new Blockly.FieldTextInput(''), 'LIMIT');
        this.appendDummyInput()
          .appendField('random items')
          .appendField(new Blockly.FieldTextInput(''), 'RANDOMLIMIT');
        this.appendDummyInput()
          .appendField('sort order')
          .appendField(new Blockly.FieldDropdown([
            ['default', ''],
            ['A to Z', 'asc'],
            ['Z to A', 'desc'],
            ['random', 'random']
          ]), 'SORT');
        this.setOutput(true, null);
        this.setColour('#0EA5E9');
        this.setTooltip('Get a filtered list of audio items for classroom activities.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['list_pick_random']) {
    Blockly.Blocks['list_pick_random'] = {
      init: function () {
        this.appendValueInput('LIST')
          .setCheck(null)
          .appendField('pick a random audio item from');
        this.setOutput(true, null);
        this.setColour('#F97316');
        this.setTooltip('Choose one random audio item from a list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['audio_item_get_word']) {
    Blockly.Blocks['audio_item_get_word'] = {
      init: function () {
        this.appendValueInput('ITEM')
          .setCheck(null)
          .appendField('word from audio item');
        this.setOutput(true, 'String');
        this.setColour('#14B8A6');
        this.setTooltip('Get the word or filename label from an audio item.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['audio_item_get_url']) {
    Blockly.Blocks['audio_item_get_url'] = {
      init: function () {
        this.appendValueInput('ITEM')
          .setCheck(null)
          .appendField('link from audio item');
        this.setOutput(true, 'String');
        this.setColour('#14B8A6');
        this.setTooltip('Get the audio file link from an audio item.');
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
          .appendField('store item in')
          .appendField(new Blockly.FieldVariable('item'), 'VAR');
        this.appendStatementInput('DO')
          .appendField('do');
        this.setPreviousStatement(true, null);
        this.setNextStatement(true, null);
        this.setColour('#D97706');
        this.setTooltip('Repeat the steps for every audio item in the list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['list_length']) {
    Blockly.Blocks['list_length'] = {
      init: function () {
        this.appendValueInput('LIST')
          .setCheck(null)
          .appendField('number of items in');
        this.setOutput(true, 'Number');
        this.setColour('#F97316');
        this.setTooltip('Count how many items are in the list.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['sound_play_url']) {
    Blockly.Blocks['sound_play_url'] = {
      init: function () {
        this.appendValueInput('URL')
          .setCheck(null)
          .appendField('play sound from link');
        this.setPreviousStatement(true, null);
        this.setNextStatement(true, null);
        this.setColour('#10B981');
        this.setTooltip('Play a sound file from a web link.');
        this.setHelpUrl('');
      }
    };
  }

  if (!Blockly.Blocks['text_join_csv']) {
    Blockly.Blocks['text_join_csv'] = {
      init: function () {
        this.appendValueInput('A')
          .setCheck(null)
          .appendField('make comma list from');
        this.appendValueInput('B')
          .setCheck(null)
          .appendField('and');
        this.appendValueInput('C')
          .setCheck(null)
          .appendField('and');
        this.setOutput(true, 'String');
        this.setColour('#0891B2');
        this.setTooltip('Combine text parts into one comma-separated list and skip empty parts.');
        this.setHelpUrl('');
      }
    };
  }
})();
