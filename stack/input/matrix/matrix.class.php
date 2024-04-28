<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A basic text-field input.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_matrix_input extends stack_input {

    protected $width;
    protected $height;

    protected function get_size(){
        switch ($this->parameters['matrixSize']){
            case 0: return 'var'; 
            case 1: return 'fix';
            default: echo 'Error: unknown type.'; break;
        }
    }

    protected $extraoptions = array(
        'hideanswer' => false,
        'allowempty' => false,
        'nounits' => false,
        'simp' => false,
        'rationalized' => false,
        'consolidatesubscripts' => false,
        'checkvars' => 0,
        'validator' => false,
        'feedback' => false,
    );

    public function adapt_to_model_answer($teacheranswer) {
        if ($this->get_size()=='fix'){
            // Work out how big the matrix should be from the INSTANTIATED VALUE of the teacher's answer.
            $cs = stack_ast_container::make_from_teacher_source('matrix_size(' . $teacheranswer . ')');
            $cs->get_valid();
            $at1 = new stack_cas_session2(array($cs), null, 0);
            $at1->instantiate();

            if ('' != $at1->get_errors()) {
                $this->errors[] = $at1->get_errors();
                return;
            }

            // These are ints...
            $this->height = $cs->get_list_element(0, true)->value;
            $this->width = $cs->get_list_element(1, true)->value;
        }
        // By default, do nothing.
    }

    public function get_expected_data() {
        $expected = array();

        if ($this->get_size()=='fix'){
            // All the matrix elements.
            for ($i = 0; $i < $this->height; $i++) {
                for ($j = 0; $j < $this->width; $j++) {
                    $expected[$this->name . '_sub_' . $i . '_' . $j] = PARAM_RAW;
                }
            }
        } else {
            // Default
            $expected[$this->name] = PARAM_RAW; 
        }
        // The valdiation will write one CAS string in a
        // hidden input, that is the combination of all the separate inputs.
        if ($this->requires_validation()) {
            $expected[$this->name . '_val'] = PARAM_RAW;
        }
        return $expected;
    }

    /**
     * Decide if the contents of this attempt is blank.
     *
     * @param array $contents a non-empty array of the student's input as a split array of raw strings.
     * @return string any error messages describing validation failures. An empty
     *      string if the input is valid - at least according to this test.
     */
    protected function is_blank_response($contents) {
        if ($contents == array('EMPTYANSWER')) {
            return true;
        }
        $allblank = true;
        foreach ($contents as $row) {
            foreach ($row as $val) {
                if (!('' == trim($val) || '?' == $val || 'null' == $val)) {
                    $allblank = false;
                }
            }
        }
        return $allblank;
    }

    /**
     * Converts the input passed in via many input elements into a raw Maxima matrix for fixed size
     *
     * Transforms the student's response input into an array. For variable size
     * Most return the same as went in.
     * 
     * @param array|string $in
     * @return string
     * @access public
     */
    public function response_to_contents($response) {
        $allblank = true;
        $matrix = array();

        if ($this->get_size()=='fix') {
            // At the start of an attempt we will have a completely blank matrix.
            // This must be spotted and a blank attempt returned.
            for ($i = 0; $i < $this->height; $i++) {
                $row = array();
                for ($j = 0; $j < $this->width; $j++) {
                    $element = '';
                    if (array_key_exists($this->name . '_sub_' . $i . '_' . $j, $response)) {
                        $element = trim($response[$this->name . '_sub_' . $i . '_' . $j]);
                    }
                    if ('' == $element) {
                        $element = '?';  // Ensures all matrix elements have something non-empty.
                    } else {
                        $allblank = false;
                    }
                    $row[] = $element;
                }
                $matrix[] = $row;
            }

            // We need to build a special definitely blank matrix of the correct shape.
            if ($allblank && $this->get_extra_option('allowempty')) {
                $matrix = array();
                for ($i = 0; $i < $this->height; $i++) {
                    $row = array();
                    for ($j = 0; $j < $this->width; $j++) {
                        $row[] = 'null';
                    }
                    $matrix[] = $row;
                }
            }
        } 
        if ($this->get_size()=='var') {
            if (array_key_exists($this->name, $response)) {
                $sans = $response[$this->name];
                $rowsin = explode("\n", $sans);
                foreach ($rowsin as $key => $row) {
                    $cleanrow = trim($row);
                    if ($cleanrow !== '') {
                        $matrix[] = $cleanrow;
                    }
                }
            }
            // Transform into lists.
            $maxlen = 0;
            foreach ($matrix as $key => $row) {
                $entries = preg_split('/\s+/', $row);
                $maxlen = max(count($entries), $maxlen);
                $matrix[$key] = $entries;
            }

            foreach ($matrix as $key => $row) {
                // Pad out short rows.
                $padrow = array();
                for ($i = 0; $i < ($maxlen - count($row)); $i++) {
                    $row[] = '?';
                }
                $matrix[$key] = array_merge($row, $padrow);
            }
            if ($matrix == array() && $this->get_extra_option('allowempty')) {
                $matrix = array('EMPTYANSWER');
            }
        }
        return $matrix;
    }

    public function contents_to_maxima($contents) {
        if ($contents == array('EMPTYANSWER')) {
            return 'matrix(EMPTYCHAR)';
        }
        $matrix = array();
        foreach ($contents as $row) {
            $matrix[] = '['.implode(',', $row).']';
        }
        return 'matrix('.implode(',', $matrix).')';
    }

    /**
     * Takes a Maxima matrix object and returns an array of values.
     * @return array
     */
    private function maxima_to_array($in) {

        // Build an empty array.
        $firstrow = array_fill(0, $this->width, '');
        $tc       = array_fill(0, $this->height, $firstrow);

        // Turn the student's answer, syntax hint, etc., into a PHP array.
        $t = trim($in);
        if ('matrix(' == substr($t, 0, 7)) {
            // @codingStandardsIgnoreStart
            // E.g. array("[a,b]","[c,d]").
            // @codingStandardsIgnoreEnd
            $rows = $this->modinput_tokenizer(substr($t, 7, -1));
            for ($i = 0; $i < count($rows); $i++) {
                $row = $this->modinput_tokenizer(substr(trim($rows[$i]), 1, -1));
                $tc[$i] = $row;
            }
        }

        return $tc;
    }

    /**
     * This is the basic validation of the student's "answer".
     * This method is only called in the input is not blank.
     *
     * Only a few input methods need to modify this method.
     * For example, Matrix types have two dimensional arrays to loop over.
     *
     * @param array $contents the content array of the student's input.
     * @return array of the validity, errors strings and modified contents.
     */
    protected function validate_contents($contents, $basesecurity, $localoptions) {

        $errors = array();
        $notes = array();
        $valid = true;

        list ($secrules, $filterstoapply) = $this->validate_contents_filters($basesecurity);
        // Separate rules for inert display logic, which wraps floats with certain functions.
        $secrulesd = clone $secrules;
        $secrulesd->add_allowedwords('dispdp,displaysci');

        // Now validate the input as CAS code.
        $modifiedcontents = array();
        if ($contents == array('EMPTYANSWER')) {
            $modifiedcontents = $contents;
        } else {
            foreach ($contents as $row) {
                $modifiedrow = array();
                $inertrow = array();
                foreach ($row as $val) {
                    $answer = stack_ast_container::make_from_student_source($val, '', $secrules, $filterstoapply,
                        array(), 'Root', $this->options->get_option('decimals'));
                    if ($answer->get_valid()) {
                        $modifiedrow[] = $answer->get_inputform();
                    } else {
                        $modifiedrow[] = 'EMPTYCHAR';
                    }
                    $valid = $valid && $answer->get_valid();
                    $errors[] = $answer->get_errors();
                    $note = $answer->get_answernote(true);
                    if ($note) {
                        foreach ($note as $n) {
                            $notes[$n] = true;
                        }
                    }
                }
                $modifiedcontents[] = $modifiedrow;
            }
        }
        // Construct one final "answer" as a single maxima object.
        // In the case of matrices (where $caslines are empty) create the object directly here.
        // As this will create a matrix we need to check that 'matrix' is not a forbidden word.
        // Should it be a forbidden word it gets still applied to the cells.
        if (isset(stack_cas_security::list_to_map($this->get_parameter('forbidWords', ''))['matrix'])) {
            $modifiedforbid = str_replace('\,', 'COMMA_TAG', $this->get_parameter('forbidWords', ''));
            $modifiedforbid = explode(',', $modifiedforbid);
            array_map('trim', $modifiedforbid);
            unset($modifiedforbid[array_search('matrix', $modifiedforbid)]);
            $modifiedforbid = implode(',', $modifiedforbid);
            $modifiedforbid = str_replace('COMMA_TAG', '\,', $modifiedforbid);
            $secrules->set_forbiddenwords($modifiedforbid);
            // Cumbersome, and cannot deal with matrix being within an alias...
            // But first iteration and so on.
        }
        $value = $this->contents_to_maxima($modifiedcontents);
        // Sanitised above.
        $answer = stack_ast_container::make_from_teacher_source($value, '', $secrules);
        $answer->get_valid();

        // We don't use the decimals option below, because we've already used it above.
        $inertform = stack_ast_container::make_from_student_source($value, '', $secrulesd,
            array_merge($filterstoapply, ['910_inert_float_for_display', '912_inert_string_for_display']),
            array(), 'Root', '.');
        $inertform->get_valid();

        $caslines = array();
        return array($valid, $errors, $notes, $answer, $caslines, $inertform, $caslines);
    }

    public function render(stack_input_state $state, $fieldname, $readonly, $tavalue) {

        if ($this->errors) {
            return $this->render_error($this->errors);
        }

        // Note that at the moment, $this->boxHeight and $this->boxWidth are only
        // used as minimums. If the current input is bigger, the box is expanded.
        $size = $this->parameters['boxWidth'] * 0.9 + 0.1;
        $attributes = array(
            'name'           => $fieldname,
            'id'             => $fieldname,
            'autocapitalize' => 'none',
            'spellcheck'     => 'false',
            'class'          => 'varmatrixinput',
            'size'           => $this->parameters['boxWidth'] * 1.1,
            'style'          => 'width: '.$size.'em',
        );

        $attr='';
        if ($readonly) {
            $attributes['readonly'] = 'readonly';
            $attr = ' readonly';
        }

        $tc = $state->contents;
        $blank = $this->is_blank_response($state->contents);
        $useplaceholder = false;
        if ($blank) {
            if ($this->get_size()=='fix') {
                $syntaxhint = $this->parameters['syntaxHint'];
                if (trim($syntaxhint) != '') {
                    $tc = $this->maxima_to_array($syntaxhint);
                    if ($this->parameters['syntaxAttribute'] == '1') {
                        $useplaceholder = true;
                    }
                    $blank = false;
                }
            } 
            if ($this->get_size()=='var') {
                $current = $this->maxima_to_raw_input($this->parameters['syntaxHint']);
                if ($this->parameters['syntaxAttribute'] == '1') {
                    $attributes['placeholder'] = $current;
                    $current = '';
                }
            } 
        } elseif ($this->get_size()=='var') {
            $current = array();
            foreach ($state->contents as $row) {
                $current[] = implode(" ", $row);
            }
            $current = implode("\n", $current);
        }

        // Read matrix bracket style from options.
        $matrixbrackets = 'matrixsquarebrackets';
        $matrixparens = $this->options->get_option('matrixparens');
        if ($matrixparens == '(') {
            $matrixbrackets = 'matrixroundbrackets';
        } else if ($matrixparens == '|') {
            $matrixbrackets = 'matrixbarbrackets';
        } else if ($matrixparens == '') {
            $matrixbrackets = 'matrixnobrackets';
        }
        if ($this->get_size()=='fix') {
            // Build the html table to contain these values.
            $xhtml = '<div class="' . $matrixbrackets . '"><table class="matrixtable" id="' . $fieldname .
                    '_container" style="display:inline; vertical-align: middle;" ' .
                    'cellpadding="1" cellspacing="0"><tbody>';
            for ($i = 0; $i < $this->height; $i++) {
                $xhtml .= '<tr>';
                if ($i == 0) {
                    $xhtml .= '<td style="padding-top: 0.5em">&nbsp;</td>';
                } else if ($i == ($this->height - 1)) {
                    $xhtml .= '<td>&nbsp;</td>';
                } else {
                    $xhtml .= '<td>&nbsp;</td>';
                }

                for ($j = 0; $j < $this->width; $j++) {
                    $val = '';
                    if (!$blank) {
                        $val = trim($tc[$i][$j]);
                    }
                    if ($val === 'null' || $val === 'EMPTYANSWER') {
                        $val = '';
                    }
                    $field = 'value';
                    if ($useplaceholder) {
                        $field = 'placeholder';
                    }
                    $name = $fieldname.'_sub_'.$i.'_'.$j;
                    $xhtml .= '<td><input type="text" id="'.$name.'" name="'.$name.'" '.$field.'="'.$val.'" size="'.
                            $this->parameters['boxWidth'].'" autocapitalize="none" spellcheck="false" '.$attr.'></td>';
    
                }

                if ($i == 0) {
                    $xhtml .= '<td style="padding-top: 0.5em">&nbsp;</td>';
                } else if ($i == ($this->height - 1)) {
                    $xhtml .= '<td style="padding-bottom: 0.5em">&nbsp;</td>';
                } else {
                    $xhtml .= '<td>&nbsp;</td>';
                }
                $xhtml .= '</tr>';
            }
            $xhtml .= '</tbody></table></div>';
            return $xhtml;
        }

        if ($this->get_size()=='var') {
            // Sort out size of text area.
            $sizecontent = $current;
            $rows = stack_utils::list_to_array($sizecontent, false);
            $attributes['rows'] = max(5, count($rows) + 1);

            $boxwidth = $this->parameters['boxWidth'];
            foreach ($rows as $row) {
                $boxwidth = max($boxwidth, strlen($row) + 5);
            }
            $attributes['cols'] = $boxwidth;

            $xhtml = html_writer::tag('textarea', htmlspecialchars($current, ENT_COMPAT), $attributes);
            return html_writer::tag('div', $xhtml, array('class' => $matrixbrackets));
        }
    }

    public function render_api_data($tavalue) {
        if ($this->errors) {
            throw new stack_exception("Error rendering input: " . implode(',', $this->errors));
        }

        $data = [];

        $data['type'] = 'matrix';
        $data['boxWidth'] = $this->parameters['boxWidth'];
        $data['width'] = $this->width;
        $data['height'] = $this->height;

        $syntaxhint = $this->parameters['syntaxHint'];
        $data['syntaxHint'] = null;
        if (trim($syntaxhint) != '') {
            $data['syntaxHint'] = $this->maxima_to_array($syntaxhint);
        }
        if ($this->get_size()=='var'){
            $data['syntaxHint'] = $this->maxima_to_raw_input($syntaxhint);
        }

        // Read matrix bracket style from options.
        $matrixbrackets = 'matrixroundbrackets';
        $matrixparens = $this->options->get_option('matrixparens');
        if ($matrixparens == '[') {
            $matrixbrackets = 'matrixsquarebrackets';
        } else if ($matrixparens == '|') {
            $matrixbrackets = 'matrixbarbrackets';
        } else if ($matrixparens == '') {
            $matrixbrackets = 'matrixnobrackets';
        }

        $data['matrixbrackets'] = $matrixbrackets;

        return $data;
    }

    /**
     * Transforms a Maxima expression into an array of raw inputs which are part of a response.
     * Most inputs are very simple, but textarea and matrix need more here.
     *
     * @param array|string $in
     * @return string
     */
    public function maxima_to_response_array($in) {
        $response = array();
        $tc = $this->maxima_to_array($in);
        
        if ($this->get_size()=='fix') {
            for ($i = 0; $i < $this->height; $i++) {
                for ($j = 0; $j < $this->width; $j++) {
                    $val = trim($tc[$i][$j]);
                    if ('?' == $val) {
                        $val = '';
                    }
                    $response[$this->name.'_sub_'.$i.'_'.$j] = $val;
                }
            }
        }
        if ($this->get_size()=='var') {
            $response[$this->name] = $this->maxima_to_raw_input($in);
        }
        if ($this->requires_validation()) {
            $response[$this->name . '_val'] = $in;
        }
        return $response;

    }

    public function add_to_moodleform_testinput(MoodleQuickForm $mform) {
        $mform->addElement('text', $this->name, $this->name, array('size' => $this->parameters['boxWidth']));
        $mform->setDefault($this->name, $this->parameters['syntaxHint']);
        $mform->setType($this->name, PARAM_RAW);
    }

    /**
     * Return the default values for the parameters.
     * @return array parameters` => default value.
     */
    public static function get_parameters_defaults() {
        return array(
            'mustVerify'         => true,
            'showValidation'     => 1,
            'boxWidth'           => 5,
            'insertStars'        => 0,
            'syntaxHint'         => '',
            'syntaxAttribute'    => 0,
            'forbidWords'        => '',
            'allowWords'         => '',
            'forbidFloats'       => true,
            'lowestTerms'        => true,
            // This looks odd, but the teacher's answer is a list and the student's a matrix.
            'sameType'           => false, 
            'options'            => '',
            'matrixSize'         => 0
        );
    }

    /**
     * Each actual extension of this base class must decide what parameter values are valid.
     * @return array of parameters names.
     */
    public function internal_validate_parameter($parameter, $value) {
        $valid = true;
        switch($parameter) {
            case 'boxWidth':
                $valid = is_int($value) && $value > 0;
                break;
        }
        return $valid;
    }

    public function get_correct_response($value) {

        if (trim($value) == 'EMPTYANSWER' || $value === null) {
            $value = '';
        }
        // TODO: refactor this ast creation away.
        $cs = stack_ast_container::make_from_teacher_source($value, '', new stack_cas_security(), array());
        $cs->set_nounify(0);

        // Hard-wire to strict Maxima syntax.
        $decimal = '.';
        $listsep = ',';
        $params = array('checkinggroup' => true,
            'qmchar' => false,
            'pmchar' => 1,
            'nosemicolon' => true,
            'keyless' => true,
            'dealias' => false, // This is needed to stop pi->%pi etc.
            'nounify' => 0,
            'nontuples' => false,
            'decimal' => $decimal,
            'listsep' => $listsep
        );
        if ($cs->get_valid()) {
            $value = $cs->ast_to_string(null, $params);
        }
        $response = $this->maxima_to_response_array($value);

        if ($this->get_size()=='var') {
            return $response;
        }

        // Once we have the correct array, within the array, use the correct decimal separator.
        if ($this->options->get_option('decimals') === ',') {
            $params['decimal'] = ',';
            $params['listsep'] = ';';
        }

        foreach ($response as $ckey => $cell) {
            $cs = stack_ast_container::make_from_teacher_source($cell, '', new stack_cas_security(), array());
            $cs->set_nounify(0);
            if ($cs->get_valid()) {
                $response[$ckey] = $cs->ast_to_string(null, $params);
            }
        }
        return $response;
    }

    /**
     * The AJAX instant validation method mostly returns a Maxima expression.
     * Mostly, we need an array, labelled with the input name.
     *
     * The matrix type is different.  The javascript creates a JSON encoded object,
     * and we need to split this up into an array of individual elements.
     *
     * @param string $in
     * @return array
     */
    protected function ajax_to_response_array($in) {
        if ($this->get_size()=='fix') {
            $tc = json_decode($in);
            for ($i = 0; $i < $this->height; $i++) {
                for ($j = 0; $j < $this->width; $j++) {
                    $val = trim($tc[$i][$j]);
                    if ('?' == $val) {
                        $val = '';
                    }
                    $response[$this->name.'_sub_'.$i.'_'.$j] = $val;
                }
            }

            if ($this->requires_validation()) {
                $response[$this->name . '_val'] = $in;
            }
            return $response;
        }
        $in = explode('<br>', $in);
        $in = implode("\n", $in);
        return array($this->name => $in);
    }

    /**
     * Takes comma separated list of elements and returns them as an array
     * while at the same time making sure that the braces stay balanced
     *
     * _tokenizer("[1,2]") => array("[1,2]")
     * _tokenizer("1,2") = > array("1","2")
     * _tokenizer("1,1/sum([1,3]),matrix([1],[2])") => array("1","1/sum([1,3])","matrix([1],[2])")
     *
     * $t = trim("matrix([a,b],[c,d])");
     * $rows = _tokenizer(substr($t, 7, -1));  // array("[a,b]","[c,d]");
     * $firstRow = _tokenizer(substr($rows[0],1,-1)); // array("a","b");
     *
     * @author Matti Harjula
     *
     * @param string $in
     * @access private
     * @return array with the parsed elements, if no elements then array
     *         contains only the input string
     */
    public function modinput_tokenizer($in) {
        $bracecount = 0;
        $parenthesiscount = 0;
        $bracketcount = 0;

        $out = array ();

        $current = '';
        for ($i = 0; $i < strlen($in); $i++) {
            $char = $in[$i];
            switch ($char) {
                case '{':
                    $bracecount++;
                    $current .= $char;
                    break;
                case '}':
                    $bracecount--;
                    $current .= $char;
                    break;
                case '(':
                    $parenthesiscount++;
                    $current .= $char;
                    break;
                case ')':
                    $parenthesiscount--;
                    $current .= $char;
                    break;
                case '[':
                    $bracketcount++;
                    $current .= $char;
                    break;
                case ']':
                    $bracketcount--;
                    $current .= $char;
                    break;
                case ',':
                    if ($bracketcount == 0 && $parenthesiscount == 0 && $bracecount == 0) {
                        $out[] = $current;
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                    break;
                default;
                    $current .= $char;
            }
        }

        if ($bracketcount == 0 && $parenthesiscount == 0 && $bracecount == 0) {
            $out[] = $current;
        }

        return $out;
    }

    /**
     * Function added for API support
     */
    public function get_api_solution($ta)
    {
        // We dont want to include the inputname in the solution, therefore we clear the name,
        // and set it back later after saving the solution
        $name = $this->name;
        $this->name = '';

        $solution = $this->maxima_to_response_array($ta);

        $this->name = $name;

        return $solution;
    }

    protected function caslines_to_answer($caslines, $secrules = false) {
        if ($this->get_size()=='fix') {
            //do default 
            if (array_key_exists(0, $caslines)) {
                return $caslines[0];
            }
            throw new stack_exception('caslines_to_answer could not create the answer.');
        }
        $vals = array();
        foreach ($caslines as $line) {
            if ($line->get_valid()) {
                $vals[] = $line->get_inputform();
            } else {
                // This is an empty place holder for an invalid expression.
                $vals[] = 'EMPTYCHAR';
            }
        }
        $s = 'matrix('.implode(',', $vals).')';
        if (!$secrules) {
            $secrules = $caslines[0]->get_securitymodel();
        }
        return stack_ast_container::make_from_student_source($s, '', $secrules);
    }

    /**
     * Transforms a Maxima list into raw input.
     *
     * @param string $in
     * @return string
     */
    private function maxima_to_raw_input($in) {
        $decimal = '.';
        $listsep = ',';
        if ($this->options->get_option('decimals') === ',') {
            $decimal = ',';
            $listsep = ';';
        }
        $tostringparams = array('inputform' => true,
            'qmchar' => true,
            'pmchar' => 0,
            'nosemicolon' => true,
            'dealias' => false, // This is needed to stop pi->%pi etc.
            'nounify' => true,
            'nontuples' => false,
            'varmatrix' => true,
            'decimal' => $decimal,
            'listsep' => $listsep
        );
        $cs = stack_ast_container::make_from_teacher_source($in);
        return $cs->ast_to_string(null, $tostringparams);
    }

}
 