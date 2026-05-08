<?php
declare(strict_types=1);

return [
    'builtin_core_block_types' => [
        'controls_if',
        'logic_compare',
        'logic_operation',
        'logic_boolean',
        'logic_negate',
        'math_number',
        'math_arithmetic',
        'text',
        'text_join',
        'variables_get',
        'variables_set',
        'procedures_defnoreturn',
        'procedures_defreturn',
        'procedures_callnoreturn',
        'procedures_callreturn',
        'lists_create_with',
    ],
    'builtin_output_block_types' => [
        'logic_compare',
        'logic_operation',
        'logic_boolean',
        'logic_negate',
        'math_number',
        'math_arithmetic',
        'text',
        'text_join',
        'variables_get',
        'procedures_callreturn',
        'lists_create_with',
    ],
    'top_level_allowed_types' => [
        'procedures_defnoreturn',
        'procedures_defreturn',
    ],
    'required_fields_by_type' => [
        'event_when_thumb_key' => ['KEY'],
        'event_when_editor_key' => ['KEY'],
        'event_when_key_name' => ['KEY'],
        'bb_send_key' => ['KEY'],
        'bb_move_caret' => ['UNIT'],
        'sound_play_folder_file' => ['FOLDER'],
        'math_arithmetic' => ['OP'],
        'logic_compare' => ['OP'],
    ],
    'allowed_field_values_by_type' => [
        'event_when_thumb_key' => [
            'KEY' => ['left', 'left-middle', 'right-middle', 'right', 'up', 'down'],
        ],
        'event_when_editor_key' => [
            'KEY' => ['Backspace', 'Delete', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'],
        ],
        'event_when_key_name' => [
            'KEY' => ['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12', 'Enter', 'Escape', ' ', 'Tab', 'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'],
        ],
        'bb_send_key' => [
            'KEY' => ['Backspace', 'Delete', 'Space', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'],
        ],
        'bb_move_caret' => [
            'UNIT' => ['character', 'cell'],
        ],
        'sound_play_folder_file' => [
            'FOLDER' => ['speech', 'letters', 'instructions', 'feedback', 'story', 'general', 'ux'],
        ],
        'math_arithmetic' => [
            'OP' => ['ADD', 'MINUS', 'MULTIPLY', 'DIVIDE', 'POWER'],
        ],
        'logic_compare' => [
            'OP' => ['EQ', 'NEQ', 'LT', 'LTE', 'GT', 'GTE'],
        ],
    ],
    'default_fields_by_type' => [
        'event_when_thumb_key' => [
            'KEY' => 'left',
        ],
        'event_when_key_name' => [
            'KEY' => 'F1',
        ],
        'event_when_editor_key' => [
            'KEY' => 'Enter',
        ],
    ],
    'normalizers' => [
        'controls_if_input_aliases' => [
            'IF' => 'IF0',
            'DO' => 'DO0',
        ],
        'operator_to_field_op_types' => [
            'math_arithmetic',
            'logic_compare',
        ],
    ],
    'prompt_rules' => [
        'Gebruik voor if/else-logica het bestaande Blockly blocktype controls_if.',
        'Gebruik dus nooit een pseudoblok zoals if_else.',
        'Voor controls_if gebruik je de echte Blockly inputnamen zoals IF0 en DO0.',
        'Gebruik dus niet IF en DO bij controls_if.',
        'Outputblokken zoals list_get_item, variables_get, text en math_number mogen nooit direct in DO, DO0 of STACK staan.',
        'Als je een waarde uit list_get_item wilt gebruiken, zet die in een value-input of sla die eerst op met variables_set.',
        'Als je een volgend woord of item uit een lijst haalt, doe dat patroon als: variables_set(current_word = list_get_item(...)) -> bb_set_text(variables_get(current_word)).',
        'Top-level blocks moeten entry points of definities zijn, dus bijvoorbeeld event_when_* of procedures_defnoreturn.',
        'Zet geen losse logica zoals controls_if als top-level block in blocks.blocks[].',
        'Event-blokken mogen nooit genest staan in inputs.STACK.block, inputs.DO.block, procedures, loops of andere statement bodies.',
    ],
];
