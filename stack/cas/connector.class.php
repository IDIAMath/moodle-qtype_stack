<?php
// This file is part of Stack - http://stack.bham.ac.uk/
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
 * Class which undertakes process control to connect to Maxima.
 *
 * @copyright  2012 The University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_cas_maxima_connector {
    protected static $config = null;

    protected $platform;
    protected $logs;
    protected $command;
    protected $init_command;
    protected $timeout;
    protected $debug;
    protected $version;

    /** @var string This collects all debug information.  */
    protected $debuginfo = '';

    public function __construct() {
        global $CFG;

        if (is_null(self::$config)) {
            self::$config = get_config('qtype_stack');
        }
        $settings = self::$config;

        $path = $CFG->dataroot . '/stack';

        $initcommand = 'load("' . $path . '/maximalocal.mac");' . "\n";
        $initcommand = str_replace("\\", "/", $initcommand);
        $initcommand .= "\n";

        $cmd = $settings->maximacommand;
        if ('' == trim($cmd) ) {
            if ('win'==$settings->platform) {
                $cmd = $path . '/maxima.bat';
                if (!is_readable($cmd)) {
                    throw new Exception("stack_cas_maxima_connector: maxima launch script {$cmd} does not exist.");
                }
            } else {
                if (is_readable('/Applications/Maxima.app/Contents/Resources/maxima.sh')) {
                    // This is the path on Macs, if Maxima has been installed following
                    // the instructions on Sourceforge.
                    $cmd = '/Applications/Maxima.app/Contents/Resources/maxima.sh';
                } else {
                    $cmd = 'maxima';
                }
            }
        }

        $this->platform     = $settings->platform;
        $this->logs         = $path;
        $this->command      = $cmd;
        $this->init_command = $initcommand;
        $this->timeout      = $settings->castimeout;
        $this->debug        = $settings->casdebugging;
        $this->version      = $settings->maximaversion;
    }

    public function get_debuginfo() {
        return $this->debuginfo;
    }

    protected function debug($heading, $message) {
        if (!$this->debug) {
            return;
        }
        if ($heading) {
            $this->debuginfo .= html_writer::tag('h3', $heading);
        }
        if ($message) {
            $this->debuginfo .= html_writer::tag('pre', s($message));
        }
    }

    /**
     * Deal with platforms, and send a string to Maxima.
     *
     * @param string $strin The raw Maxima command to be processed.
     * @return array
     */
    protected function send_to_maxima($command) {

        $this->debug('Maxima command', $command);

        $platform = $this->platform;

        if ($platform == 'win') {
            $result = $this->send_win($command);
        } else if (($platform == 'unix') || ($platform == 'server')) {
            // TODO:server mode currently falls back to launching a Maxima process.
            $result = $this->send_unix($command);
        } else {
            throw new Exception('stack_cas_maxima_connector: Unknown platform '.$platform);
        }

        $this->debug('CAS result', $result);

        return $result;
    }

    /**
     * Starts a instance of maxima and sends the maxima command under a Windows OS
     *
     * @param string $strin
     * @return string
     * @access public
     */
    protected function send_win($command) {
        $ret = false;

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('file', $this->logs."cas_errors.txt", 'a'));

        $cmd = '"'.$this->command.'"';
        $this->debug('Command line', $cmd);

        $casprocess = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($casprocess)) {
            throw new Exception('stack_cas_maxima_connector: Could not open a CAS process.');
        }

        if (!fwrite($pipes[0], $this->init_command)) {
            return(false);
        }
        fwrite($pipes[0], $command);
        fwrite($pipes[0], 'quit();\n\n');
        fflush($pipes[0]);

        // read output from stdout
        $ret = '';
        while (!feof($pipes[1])) {
            $out = fgets($pipes[1], 1024);
            if ('' == $out) {
                // PAUSE
                usleep(1000);
            }
            $ret .= $out;
        }
        fclose($pipes[0]);
        fclose($pipes[1]);

        return trim($ret);
    }

    /**
     * Connect directly to the CAS, and return the raw string result.
     * This does not use sockets, but calls a new CAS session each time.
     * Hence, this is not likely to be efficient.
     * Furthermore, since the system gives the webserver execute priviliges
     * to this is insecure.
     *
     * @param string $strin The string of CAS commands to be processed.
     * @return string|boolean The converted HTML string or FALSE if there was an error.
     */
    protected function send_unix($strin) {
        // Sends the $st to maxima.

        $ret = false;
        $err = '';
        $cwd = null;
        $env = array('why'=>'itworks');

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'));
        $casprocess = proc_open($this->command, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($casprocess)) {
            throw new Exception('stack_cas_maxima_connector: could not open a CAS process');
        }

        if (!fwrite($pipes[0], $this->init_command)) {
            echo "<br />Could not write to the CAS process!<br />\n";
            return(false);
        }
        fwrite($pipes[0], $strin);
        fwrite($pipes[0], 'quit();'."\n\n");

        $ret = '';
        // read output from stdout
        $start_time = microtime(true);
        $continue   = true;

        if (!stream_set_blocking($pipes[1], false)) {
            $this->debug('', 'Warning: could not stream_set_blocking to be FALSE on the CAS process.');
        }

        while ($continue and !feof($pipes[1])) {

            $now = microtime(true);

            if (($now-$start_time) > $this->timeout) {
                $proc_array = proc_get_status($casprocess);
                if ($proc_array['running']) {
                    proc_terminate($casprocess);
                }
                $continue = false;
            } else {
                $out = fread($pipes[1], 1024);
                if ('' == $out) {
                    // PAUSE
                    usleep(1000);
                }
                $ret .= $out;
            }

        }

        if ($continue) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            $this->debug('Timings', "Start: {$start_time}, End: {$now}, Taken = " .
                    ($now - $start_time));

        } else {
            // Add sufficient closing ]'s to allow something to be un-parsed from the CAS.
            $ret .=' The CAS timed out. ] ] ] ]';
        }

        return $ret;
    }

    public function maxima_session($command) {

        $result = $this->send_to_maxima($command);
        $unp = $this->maxima_raw_session($result);

        $this->debug('Unpacked result as', print_r($unp, true));

        return $unp;
    }

    /*
     * Top level Maxima-specific function used to parse CAS output into an array.
     *
     * @param array $strin Raw CAS output
     * @return array
     */
    protected function maxima_raw_session($strin) {
        $result = '';
        $errors = false;
        //check we have a timestamp & remove everything before it.
        $ts = substr_count($strin, '[TimeStamp');
        if ($ts != 1) {
            $this->debug('', 'receive_raw_maxima: no timestamp returned.');
            return array();
        } else {
            $result = strstr($strin, '[TimeStamp'); //remove everything before the timestamp
        }

        $result = trim(str_replace('#', '', $result));
        $result = trim(str_replace("\n", '', $result));

        $unp = $this->maxima_unpack_helper($result);

        if (array_key_exists('Locals', $unp)) {
            $uplocs = $unp['Locals']; // Grab the local variables
            unset($unp['Locals']);
        } else {
            $uplocs = '';
        }

        // Now we need to turn the (error,key,value,display) tuple into an array
        $locals = array();
        foreach ($this->maxima_unpack_helper($uplocs) as $var => $valdval) {
            if (is_array($valdval)) {
                $errors["CAS"] = "CAS failed to generate any useful output.";
            } else {
                if (preg_match('/.*\[.*\].*/', $valdval)) {
                    // There are some []'s in the string.
                    $loc = $this->maxima_unpack_helper($valdval);
                    if ('' == trim($loc['error'])) {
                        unset($loc['error']);
                    }
                    $locals[]=$loc;

                } else {
                    $errors["LocalVarGet$var"] = "Couldn't unpack the local variable $var from the string $valdval.";
                }
            }
        }

        // Next process and tidy up these values.
        for ($i=0; $i < count($locals); $i++) {

            if (isset($locals[$i]['error'])) {
                $locals[$i]['error'] = $this->tidy_error($locals[$i]['error']);
            } else {
                $locals[$i]['error'] = '';
            }
            // if theres a plot being returned
            $plot = isset($locals[$i]['display']) ? substr_count($locals[$i]['display'], '<img') : 0;
            if ($plot > 0) {
                //plots always contain errors, so remove
                $locals[$i]['error'] = '';
                //for mathml display, remove the mathml that is inserted wrongly round the plot.
                $locals[$i]['display'] = str_replace('<math xmlns=\'http://www.w3.org/1998/Math/MathML\'>',
                    '', $locals[$i]['display']);
                $locals[$i]['display'] = str_replace('</math>', '', $locals[$i]['display']);

                // for latex mode, remove the mbox
                // handles forms: \mbox{image} and (earlier?) \mbox{{} {image} {}}
                $locals[$i]['display'] = preg_replace("|\\\mbox{({})? (<html>.+</html>) ({})?}|", "$2", $locals[$i]['display']);
            }
        }
        return $locals;
    }


    protected function maxima_unpack_helper($strin) {
        // Take the raw string from the CAS, and unpack this into an array.
        $offset = 0;
        $strin_len = strlen($strin);
        $unparsed = '';
        $errors = '';

        if ($eqpos = strpos($strin, '=', $offset)) {
            // Check there are ='s
            do {
                $gb = stack_utils::substring_between($strin, '[', ']', $eqpos);
                $val = substr($gb[0], 1, strlen($gb[0])-2);
                $val = str_replace('"', '', $val);
                $val = trim($val);

                if (preg_match('/[A-Za-z0-9].*/', substr($strin, $offset, $eqpos-$offset), $regs)) {
                    $var = trim($regs[0]);
                } else {
                    $var = 'errors';
                    $errors['LOCVARNAME'] = "Couldn't get the name of the local variable.";
                }

                $unparsed[$var] = $val;
                $offset = $gb[2];
            } while (($eqpos = strpos($strin, '=', $offset)) && ($offset < $strin_len));

        } else {
            $errors['PREPARSE'] = "There are no ='s in the raw output from the CAS!";
        }

        if ('' != $errors) {
            $unparsed['errors'] = $errors;
        }

        return($unparsed);
    }

    /**
     * Deals with Maxima errors. Enables some translation.
     *
     * @param string $errstr a Maxima error string
     * @return string
     */
    protected function tidy_error($errstr) {
        if (strpos($errstr, '0 to a negative exponent') !== false) {
            $errstr = stack_string('Maxima_DivisionZero');
        }
        return $errstr;
    }
}
