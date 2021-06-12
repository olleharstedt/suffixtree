<?php

if ($hasSubqs) {
    $subqs = $qinfo['subqs'];
    $sq_names = [];
    foreach ($subqs as $sq) {
        $sq_name = null;
        switch ($type) {
        case Question::QT_1_ARRAY_MULTISCALE:   //Array (Flexible Labels) dual scale
            if (substr($sq['varName'], -1, 1) == '0') {
                if ($this->sgqaNaming) {
                    $base = $sq['rowdivid'] . "#";
                    $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                } else {
                    $base = (string)substr($sq['varName'], 0, -1);
                    $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                }
            }
            break;
        case Question::QT_COLON_ARRAY_MULTI_FLEX_NUMBERS: //ARRAY (Multi Flexi) 1 to 10
        case Question::QT_SEMICOLON_ARRAY_MULTI_FLEX_TEXT: //ARRAY (Multi Flexi) Text
        case Question::QT_A_ARRAY_5_CHOICE_QUESTIONS: //ARRAY (5 POINT CHOICE) radio-buttons
        case Question::QT_B_ARRAY_10_CHOICE_QUESTIONS: //ARRAY (10 POINT CHOICE) radio-buttons
        case Question::QT_C_ARRAY_YES_UNCERTAIN_NO: //ARRAY (YES/UNCERTAIN/NO) radio-buttons
        case Question::QT_E_ARRAY_OF_INC_SAME_DEC_QUESTIONS: //ARRAY (Increase/Same/Decrease) radio-buttons
        case Question::QT_F_ARRAY_FLEXIBLE_ROW: //ARRAY (Flexible) - Row Format
        case Question::QT_K_MULTIPLE_NUMERICAL_QUESTION: //MULTIPLE NUMERICAL QUESTION
        case Question::QT_Q_MULTIPLE_SHORT_TEXT: //MULTIPLE SHORT TEXT
        case Question::QT_M_MULTIPLE_CHOICE: //Multiple choice checkbox
        case Question::QT_R_RANKING_STYLE: //RANKING STYLE
            if ($this->sgqaNaming) {
                $sq_name = (string)substr($sq['jsVarName'], 4) . '.NAOK';
            } else {
                $sq_name = $sq['varName'] . '.NAOK';
            }
            break;
        case Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS: //Multiple choice with comments checkbox + text
            if (!preg_match('/comment$/', $sq['varName'])) {
                if ($this->sgqaNaming) {
                    $sq_name = $sq['rowdivid'] . '.NAOK';
                } else {
                    $sq_name = $sq['rowdivid'] . '.NAOK';
                }
            }
            break;
        default:
            break;
        }
        if (!is_null($sq_name)) {
            $sq_names[] = $sq_name;
        }
    }
    if (count($sq_names) > 0) {
        if (!isset($validationEqn[$questionNum])) {
            $validationEqn[$questionNum] = [];
        }
        $validationEqn[$questionNum][] = [
            'qtype' => $type,
            'type'  => 'min_answers',
            'class' => 'num_answers',
            'eqn'   => 'if(is_empty(' . $min_answers . '),1,(count(' . implode(', ', $sq_names) . ') >= (' . $min_answers . ')))',
            'qid'   => $questionNum,
        ];
    }
}
} else {
    $min_answers = '';
}
