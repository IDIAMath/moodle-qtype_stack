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

// This script handles the various deploy/undeploy actions from questiontestrun.php.
//
// @copyright  2023 RWTH Aachen
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.

/**
 * Simulating Moodle global configuration variables.
 */

// Remove this line in your copy.
defined('MOODLE_INTERNAL') || die();
$CFG = new stdClass;
$PAGE = new stdClass;

// This is the directory into which you put the scripts.
$CFG->wwwroot = "/var/www/html";
// The base url of the installation.
// The server path of the installation.
$CFG->dirroot = realpath(dirname(__FILE__));
// You must have a data directory into which the webserver can write.  Don't put this in your web directory.
$CFG->dataroot = "/var/data/api";

// URL of your web server, e.g.
$CFG->dataurl = "http://localhost/";

$CFG->maximacommand = 'maxima';
$CFG->maximaversion = '5.44.0';
// Once you have compiled maxima you will need to change this.
$CFG->platform            = 'server';
$CFG->maximacommandopt    = 'timeout --kill-after=10s 10s ' . $CFG->dataroot . '/stack/maxima_opt_auto';
$CFG->maximacommandserver = getenv('MAXIMA_URL') ?: 'http://maxima:8080/maxima';
/*
 * These settings are hard-wired here.  See settings.php for more details.
 * You probably don't need to change many of the following.
 */
$CFG->maximalocalfolder = $CFG->dataroot . 'maxima/';

// Type (int).
$CFG->castimeout = 10;
$CFG->casdebugging = 1;
$CFG->casresultscache = 'none';
$CFG->maximalibraries = '';
$CFG->serveruserpass = '';

// Do not change this from zero.  The API has no parser cache.
$CFG->parsercacheinputlength = 0;

$CFG->caspreparse = 'true';
$CFG->plotcommand = "gnuplot";
$CFG->ajaxvalidation = 0;
$CFG->replacedollars = false;

$CFG->questionsimplify = 1;
$CFG->assumepositive = 0;
$CFG->assumereal = 0;
$CFG->prtcorrect = '';
$CFG->prtpartiallycorrect = '';
$CFG->prtincorrect = '';
$CFG->multiplicationsign = 'dot';
$CFG->sqrtsign = 1;
$CFG->complexno = 'i;';
$CFG->inversetrig = 'cos-1';
$CFG->matrixparens = "[";

$CFG->inputtype = 'algebraic';
$CFG->inputboxsize = 15;
$CFG->inputstrictsyntax = 1;
$CFG->inputinsertstars = 0;
$CFG->inputforbidwords = '';
$CFG->inputforbidfloat = 0;
$CFG->inputrequirelowestterms = 1;
$CFG->inputcheckanswertype = 1;
$CFG->inputmustverify = 1;
$CFG->inputshowvalidation = 1;

$CFG->stackmaximaversion = "2023121100";
$CFG->version = "2023121100";

// Do not change this setting.
$CFG->mathsdisplay = 'api';

$CFG->libdir = $CFG->dirroot . '/emulation/libdir';
