/**
 * A javascript module to handle separation of author sourced scripts into
 * IFRAMES. All such scripts will have limited access to the actual document
 * on the VLE side and this script represents the VLE side endpoint for
 * message handling needed to give that access. When porting STACK onto VLEs
 * one needs to map this script to do the following:
 *
 *  1. Ensure that searches for target elements/inputs are limited to questions
 *     or their feedback and do not return any elements outside them.
 *
 *  2. Map any identifiers needed to identify inputs by name.
 *
 *  3. Any change handling related to input value modifications through this
 *     logic gets connected to any such handling on the VLE side.
 *
 *
 * This script is intenttionally ordered so that the VLE specific bits should
 * be at the top.
 *
 *
 * This script assumes the following:
 *
 *  1. Each relevant IFRAME has an `id`-attribute that will be told to this
 *     script.
 *
 *  2. Each such IFRAME exists within the question content itself, so that
 *     one can traverse up the DOM tree from that IFRAME to find the border of
 *     the question.
 *
 * @module     qtype_stack/stackjsvle
 * @copyright  2023 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    "qtype_stack/stackjsvle",
    ['core/event'], 
    function(
    CustomEvents
) {
    'use strict';
    // Note the VLE specific include of logic.

    /* All the IFRAMES have unique identifiers that they give in their
     * messages. But we only work with those that have been created by
     * our logic and are found from this map.
     */
    let IFRAMES = {};

    /* For event handling, lists of IFRAMES listening particular inputs.
     */
    let INPUTS = {};
    let BUTTONS = {};

    /* For event handling, lists of IFRAMES listening particular inputs
     * and their input events. By default we only listen to changes.
     * We report input events as changes to the other side.
     */
    let INPUTS_INPUT_EVENT = {};
    let BUTTONS_BUTTON_EVENT = {}; 

    /* A flag to disable certain things. */
    let DISABLE_CHANGES = false;


    /**
     * Returns an element with a given id, if an only if that element exists
     * inside a portion of DOM that represents a question or its feedback.
     *
     * If not found or exists outside the restricted area then returns `null`.
     *
     * @param {String} id the identifier of the element we want.
     */
    function vle_get_element(id) {
        /* In the case of Moodle we are happy as long as the element is inside
           something with the `formulation`-class. */
        let candidate = document.getElementById(id);
        let iter = candidate;
        while (iter && !iter.classList.contains('formulation') &&
               !iter.classList.contains('outcome')) {
            iter = iter.parentElement;
        }
        if (iter && (iter.classList.contains('formulation') ||
            iter.classList.contains('outcome'))) {
            return candidate;
        }

        return null;
    }

    /**
     * Returns an input element with a given name, if and only if that element
     * exists inside a portion of DOM that represents a question or its feedback.
     *
     * Note that, the input element may have a name that multiple questions
     * use and to pick the preferred element one needs to pick the one
     * within the same question as the IFRAME.
     *
     * Note that the input can also be a select. In the case of radio buttons
     * returning one of the possible buttons is enough.
     *
     * If not found or exists outside the restricted area then returns `null`.
     *
     * @param {String} name the name of the input we want
     * @param {String} srciframe the identifier of the iframe wanting it
     * @param {boolean} outside do we expand the search beyound the src question?
     */
    function vle_get_input_element(name, srciframe, outside) {
        /* In the case of Moodle we are happy as long as the element is inside
           something with the `formulation`-class. */
        if (outside === undefined) {
            // Old default was to search beyoudn the question.
            outside = true;
        }
        let initialcandidate = document.getElementById(srciframe);
        let iter = initialcandidate;
        while (iter && !iter.classList.contains('formulation') &&
               !iter.classList.contains('outcome')) {
            iter = iter.parentElement;
        }
        if (iter && (iter.classList.contains('formulation') ||
            iter.classList.contains('outcome'))) {
            // iter now represents the borders of the question containing
            // this IFRAME.
            let possible = iter.querySelector('input[id$="_' + name + '"]');
            if (possible !== null) {
                return possible;
            }
            possible = iter.querySelector('textarea[id$="_' + name + '"]');
            if (possible !== null) {
                return possible;
            }
            // Radios have interesting ids, but the name makes sense
            possible = iter.querySelector('input[id$="_' + name + '_1"][type=radio]');
            if (possible !== null) {
                return possible;
            }
            // Same for checkboxes, ntoe that non STACK checkbox can be targetted by
            // just the id using the topmost case here.
            possible = iter.querySelector('input[id$="_' + name + '_1"][type=checkbox]');
            if (possible !== null) {
                return possible;
            }
            possible = iter.querySelector('select[id$="_' + name + '"]');
            if (possible !== null) {
                return possible;
            }
        }
        if (!outside) {
            return null;
        }
        // If none found within the question itself, search everywhere.
        let possible = document.querySelector('.formulation input[id$="_' + name + '"]');
        if (possible !== null) {
            return possible;
        }
        possible = document.querySelector('.formulation textarea[id$="_' + name + '"]');
        if (possible !== null) {
            return possible;
        }
        // Radios have interesting ids, but the name makes sense
        possible = document.querySelector('.formulation input[id$="_' + name + '_1"][type=radio]');
        if (possible !== null) {
            return possible;
        }
        possible = document.querySelector('.formulation input[id$="_' + name + '_1"][type=checkbox]');
        if (possible !== null) {
            return possible;
        }
        possible = document.querySelector('.formulation select[id$="_' + name + '"]');
        if (possible !== null) {
            return possible;
        }

        // Also search from within the feedback and other "outcome".
        // Note that we do not search for STACK sourced checkboxes from the feedback,
        // they do not exist there so simply finding them with the id is enough.
        possible = document.querySelector('.outcome input[id$="_' + name + '"]');
        if (possible !== null) {
            return possible;
        }
        possible = document.querySelector('.outcome textarea[id$="_' + name + '"]');
        if (possible !== null) {
            return possible;
        }
        possible = document.querySelector('.outcome select[id$="_' + name + '"]');
        return possible;
    }

    /**
     * Returns a list of input elements targetting the same thing.
     *
     * Note that STACK checkboxes have interesting naming for this.
     * And we assume we are getting the ones that `vle_get_input_element` would return.
     *
     * @param {element} input element of type=radio or type=checkbox
     */
    function vle_get_others_of_same_input_group(input) {
        if (input.type === 'radio') {
            return document.querySelectorAll('.formulation input[name=' + CSS.escape(input.name) + ']');
        }
        // Is it a Moodle input or a fake? If Moodle then assume STACK and its pattern.
        if (input.name.startsWith('q') && input.name.indexOf(':') > -1 && input.name.endsWith('_1')) {
            return document.querySelectorAll('.formulation input[name^=' +
                CSS.escape(input.name.substring(0, input.name.length - 1)) + ']');
        }
        return document.querySelectorAll('.formulation input[name=' + CSS.escape(input.name) + ']');
    }


    /**
     * Returns the input element or null for a question level submit button.
     * Basically, the "Check" button that behaviours like adaptive-mode in Moodle have.
     * Not all questions have such buttons, and the behaviour will affect that.
     *
     * Will only return the button of the question containing that iframe.
     *
     * @param {String} srciframe the identifier of the iframe wanting it
     */
    function vle_get_submit_button(srciframe) {
        let initialcandidate = document.getElementById(srciframe);
        let iter = initialcandidate;
        // Note the submit button is most definitely not within "outcome".
        while (iter && !iter.classList.contains('formulation')) {
            iter = iter.parentElement;
        }
        if (iter && iter.classList.contains('formulation')) {
            // iter now represents the borders of the question containing
            // this IFRAME.
            // In Moodle inputs that are behaviour variables use `-` as a separator
            // for the name and usage id.
            let possible = iter.querySelector('input[id$="-submit"][type=submit]');
            return possible;
        }
        return null;
    }
    //For a button with access to input (adaptbutton)
    function vle_get_button_element(name, srciframe) {
        let initialcandidate = document.getElementById(srciframe);
        let iter = initialcandidate;
        while (iter && !iter.classList.contains('formulation')) {
            iter = iter.parentElement;
        }
        if (iter && iter.classList.contains('formulation')) {
            // iter now represents the borders of the question containing
            // this IFRAME.
            let possible = iter.querySelector('button[id$="' + name + '"]');
            if (possible !== null) {
                return possible;
            }
        }
        // If none found within the question itself, search everywhere.
        let possible = document.querySelector('.formulation button[id$="' + name + '"]');
        return possible;
    }
    /**
     * Triggers any VLE specific scripting related to updates of the given
     * input element.
     *
     * @param {HTMLElement} inputelement the input element that has changed
     * @param {HTMLElement} buttonelement the button element that has clicked
     */
    function vle_update_input(inputelement) {
        // Triggering a change event may be necessary.
        const c = new Event('change');
        inputelement.dispatchEvent(c);
        // Also there are those that listen to input events.
        const i = new Event('input');
        inputelement.dispatchEvent(i);
        if (inputelement.type === 'radio' || inputelement.type === 'checkbox') {
            const k = new Event('click');
            inputelement.dispatchEvent(k);
        } 
    }
    function vle_update_button(buttonelement) {
        // Triggering a click event may be necessary.
        const c = new Event('click');
        buttonelement.dispatchEvent(c);
    }

    /**
     * Triggers any VLE specific scripting related to DOM updates.
     *
     * @param {HTMLElement} modifiedsubtreerootelement element under which changes may have happened.
     */
    function vle_update_dom(modifiedsubtreerootelement) {
        CustomEvents.notifyFilterContentUpdated(modifiedsubtreerootelement);
    }

    /**
     * Does HTML-string cleaning, i.e., removes any script payload. Returns
     * a DOM version of the given input string. The DOM version returned is
     * an element of some sort containing the contents, possibly a `body`.
     *
     * This is used when receiving replacement content for a div.
     *
     * @param {String} src a raw string to sanitise
     */
    function vle_html_sanitize(src) {
        // This can be implemented with many libraries or by custom code
        // however as this is typically a thing that a VLE might already have
        // tools for we have it at this level so that the VLE can use its own
        // tools that do things that the VLE developpers consider safe.

        // As Moodle does not currently seem to have such a sanitizer in
        // the core libraries, here is one implementation that shows what we
        // are looking for.

        // TODO: look into replacing this with DOMPurify or some such.

        let parser = new DOMParser();
        let doc = parser.parseFromString(src, "text/html");

        // First remove all <script> tags. Also <style> as we do not want
        // to include too much style.
        for (let el of doc.querySelectorAll('script, style')) {
            el.remove();
        }

        // Check all elements for attributes.
        for (let el of doc.querySelectorAll('*')) {
            for (let {name, value} of el.attributes) {
                if (is_evil_attribute(name, value)) {
                    el.removeAttribute(name);
                }
            }
        }

        return doc.body;
    }

    /**
     * Utility function trying to determine if a given attribute is evil
     * when sanitizing HTML-fragments.
     *
     * @param {String} name the name of an attribute.
     * @param {String} value the value of an attribute.
     */
    function is_evil_attribute(name, value) {
        const lcname = name.toLowerCase();
        if (lcname.startsWith('on')) {
            // We do not allow event listeners to be defined.
            return true;
        }
        if (lcname === 'src' || lcname.endsWith('href')) {
            // Do not allow certain things in the urls.
            const lcvalue = value.replace(/\s+/g, '').toLowerCase();
            // Ignore es-lint false positive.
            /* eslint-disable no-script-url */
            if (lcvalue.includes('javascript:') || lcvalue.includes('data:text')) {
                return true;
            }
        }

        return false;
    }


    /*************************************************************************
     * Above this are the bits that one would probably tune when porting.
     *
     * Below is the actuall message handling and it should be left alone.
     */
    window.addEventListener("message", (e) => {
        // NOTE! We do not check the source or origin of the message in
        // the normal way. All actions that can bypass our filters to trigger
        // something are largely irrelevant and all traffic will be kept
        // "safe" as anyone could be listening.

        // All messages we receive are strings, anything else is for someone
        // else and will be ignored.
        if (!(typeof e.data === 'string' || e.data instanceof String)) {
            return;
        }

        // That string is a JSON encoded dictionary.
        let msg = null;
        try {
            msg = JSON.parse(e.data);
        } catch (e) {
            // Only JSON objects that are parseable will work.
            return;
        }

        // All messages we handle contain a version field with a particular
        // value, for now we leave the possibility open for that value to have
        // an actual version number suffix...
        if (!(('version' in msg) && msg.version.startsWith('STACK-JS'))) {
            return;
        }

        // All messages we handle must have a source and a type,
        // and that source must be one of the registered ones.
        if (!(('src' in msg) && ('type' in msg) && (msg.src in IFRAMES))) {
            return;
        }
        let element = null;
        let input = null;
        let button = null;

        let response = {
            version: 'STACK-JS:1.3.0'
        };

        switch (msg.type) {
        case 'register-input-listener':
            // 1. Find the input.
            input = vle_get_input_element(msg.name, msg.src, !msg['limit-to-question']);

            if (input === null) {
                // Requested something that is not available.
                response.type = 'error';
                response.msg = 'Failed to connect to input: "' + msg.name + '"';
                response.tgt = msg.src;
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
                return;
            }

            response.type = 'initial-input';
            response.name = msg.name;
            response.tgt = msg.src;

            // 2. What type of an input is this? Note that we do not
            // currently support all types in sensible ways. In particular,
            // anything with multiple values will be a problem.
            if (input.nodeName.toLowerCase() === 'select') {
                response.value = input.value;
                response['input-type'] = 'select';
                response['input-readonly'] = input.hasAttribute('disabled');
            } else if (input.nodeName.toLowerCase() === 'textarea') {
                response.value = input.value;
                response['input-type'] = 'textarea';
                response['input-readonly'] = input.hasAttribute('disabled');
            } else if (input.type === 'checkbox') {
                response.value = input.checked;
                response['input-type'] = 'checkbox';
                response['input-readonly'] = input.hasAttribute('disabled');
            } else {
                response.value = input.value;
                response['input-type'] = input.type;
                response['input-readonly'] = input.hasAttribute('readonly');
            }
            if (input.type === 'radio') {
                response['input-readonly'] = input.hasAttribute('disabled');
                response.value = '';
                for (let inp of document.querySelectorAll('input[type=radio][name=' + CSS.escape(input.name) + ']')) {
                    if (inp.checked) {
                        response.value = inp.value;
                    }
                }
            }

            // 3. Add listener for changes of this input.
            if (input.id in INPUTS) {
                if (msg.src in INPUTS[input.id]) {
                    // DO NOT BIND TWICE!
                    return;
                }
                if (input.type !== 'radio') {
                    INPUTS[input.id].push(msg.src);
                } else {
                    let radgroup = document.querySelectorAll('input[type=radio][name=' + CSS.escape(input.name) + ']');
                    for (let inp of radgroup) {
                        INPUTS[inp.id].push(msg.src);
                    }
                }
            } else {
                if (input.type !== 'radio') {
                    INPUTS[input.id] = [msg.src];
                } else {
                    let radgroup = document.querySelectorAll('input[type=radio][name=' + CSS.escape(input.name) + ']');
                    for (let inp of radgroup) {
                        INPUTS[inp.id] = [msg.src];
                    }
                }
                if (input.type !== 'radio') {
                    input.addEventListener('change', () => {
                        if (DISABLE_CHANGES) {
                            return;
                        }
                        let resp = {
                            version: 'STACK-JS:1.0.0',
                            type: 'changed-input',
                            name: msg.name
                        };
                        if (input.type === 'checkbox') {
                            resp['value'] = input.checked;
                        } else {
                            resp['value'] = input.value;
                        }
                        for (let tgt of INPUTS[input.id]) {
                            resp['tgt'] = tgt;
                            IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(resp), '*');
                        }
                    });
                } else {
                    // Assume that if we received a radio button that is safe
                    // then all its friends are also safe.
                    let radgroup = document.querySelectorAll('input[type=radio][name=' + CSS.escape(input.name) + ']');
                    radgroup.forEach((inp) => {
                        inp.addEventListener('change', () => {
                            if (DISABLE_CHANGES) {
                                return;
                            }
                            let resp = {
                                version: 'STACK-JS:1.0.0',
                                type: 'changed-input',
                                name: msg.name
                            };
                            if (inp.checked) {
                                resp.value = inp.value;
                            } else {
                                // What about unsetting?
                                return;
                            }
                            for (let tgt of INPUTS[inp.id]) {
                                resp['tgt'] = tgt;
                                IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(resp), '*');
                            }
                        });
                    });
                }
            }

            if (('track-input' in msg) && msg['track-input'] && input.type !== 'radio') {
                if (input.id in INPUTS_INPUT_EVENT) {
                    if (msg.src in INPUTS_INPUT_EVENT[input.id]) {
                        // DO NOT BIND TWICE!
                        return;
                    }
                    INPUTS_INPUT_EVENT[input.id].push(msg.src);
                } else {
                    INPUTS_INPUT_EVENT[input.id] = [msg.src];

                    input.addEventListener('input', () => {
                        if (DISABLE_CHANGES) {
                            return;
                        }
                        let resp = {
                            version: 'STACK-JS:1.0.0',
                            type: 'changed-input',
                            name: msg.name
                        };
                        if (input.type === 'checkbox') {
                            resp['value'] = input.checked;
                        } else {
                            resp['value'] = input.value;
                        }
                        for (let tgt of INPUTS_INPUT_EVENT[input.id]) {
                            resp['tgt'] = tgt;
                            IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(resp), '*');
                        }
                    });
                }
            }

            // 4. Let the requester know that we have bound things
            //    and let it know the initial value.
            if (!(msg.src in INPUTS[input.id])) {
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            }

            break;
        case 'register-button-listener':
            // 1. Find the button.
            button = vle_get_button_element(msg.name, msg.src);

            if (button === null) {
                // Requested something that is not available.
                response.type = 'error';
                response.msg = 'Failed to connect to button: "' + msg.name + '"';
                response.tgt = msg.src;
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
                return;
            }

            response.type = 'initial-button';
            response.name = msg.name;
            response.tgt = msg.src;
            response['button-type'] = 'button';

            // 2. Add listener for click of this button.
            if (button.id in BUTTONS) {
                if (msg.src in BUTTONS[button.id]) {
                    // DO NOT BIND TWICE!
                    return;
                }
                BUTTONS[button.id].push(msg.src);
            } else {
                BUTTONS[button.id] = [msg.src];
                button.addEventListener('click', () => {
                        if (DISABLE_CHANGES) {
                            return;
                        }
                        let resp = {
                            version: 'STACK-JS:1.0.0',
                            type: 'clicked-button',
                            name: msg.name,
                        };
                        for (let tgt of BUTTONS[button.id]) {
                            resp['tgt'] = tgt;
                            IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(resp), '*');
                        }
                    });
            }
            if (('track-button' in msg) && msg['track-button']) {
                if (button.id in BUTTONS_BUTTON_EVENT) {
                    if (msg.src in BUTTONS_BUTTON_EVENT[button.id]) {
                        // DO NOT BIND TWICE!
                        return;
                    }
                    BUTTONS_BUTTON_EVENT[button.id].push(msg.src);
                } else {
                    BUTTONS_BUTTON_EVENT[button.id] = [msg.src];

                    button.addEventListener('click', () => {
                        if (DISABLE_CHANGES) {
                            return;
                        }
                        let resp = {
                            version: 'STACK-JS:1.0.0',
                            type: 'clicked-button',
                            name: msg.name
                        };
                        for (let tgt of BUTTONS_BUTTON_EVENT[button.id]) {
                            resp['tgt'] = tgt;
                            IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(resp), '*');
                        }
                    });
                }
            }

            // 3. Let the requester know that we have bound things
            //    and let it know the initial value.
            if (!(msg.src in BUTTONS[button.id])) {
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            }
                
            break;
        case 'changed-input':
            // 1. Find the input.
            input = vle_get_input_element(msg.name, msg.src);

            if (input === null) {
                // Requested something that is not available.
                const ret = {
                    version: 'STACK-JS:1.0.0',
                    type: 'error',
                    msg: 'Failed to modify input: "' + msg.name + '"',
                    tgt: msg.src
                };
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(ret), '*');
                return;
            }

            // Disable change events.
            DISABLE_CHANGES = true;

            // TODO: Radio buttons should we check that value is possible?
            if (input.type === 'checkbox') {
                input.checked = msg.value;
            } else {
                input.value = msg.value;
            }

            // Trigger VLE side actions.
            vle_update_input(input);

            // Enable change tracking.
            DISABLE_CHANGES = false;

            // Tell all other frames, that care, about this.
            response.type = 'changed-input';
            response.name = msg.name;
            response.value = msg.value;

            for (let tgt of INPUTS[input.id]) {
                if (tgt !== msg.src) {
                    response.tgt = tgt;
                    IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(response), '*');
                }
            }
            break;
        case 'clear-input': 
            // 1. Find the input.
            input = vle_get_input_element(msg.name, msg.src);

            if (input.nodeName.toLowerCase() === 'select') {
                if (input.selectedIndex !== -1) {
                    input.selectedIndex = -1;
                    vle_update_input(input);
                }
                for(var i = 0; i < input.options.length; i++) {
                    if (input.options[i].hasAttribute('selected')) {
                        input.options[i].removeAttribute('selected');
                        vle_update_input(input);
                    }
                    if (input.options[i].value === '') {
                        // If we have the clear input option select that.
                        input.options[i].selected = true;
                        vle_update_input(input);
                    }
                }
            } else if (input.nodeName.toLowerCase() === 'textarea') {
                if (input.value !== '') {
                    input.value = '';
                    vle_update_input(input);
                }
            } else if (input.type === 'checkbox') {
                for (let inp of vle_get_others_of_same_input_group(input)) {
                    inp.checked = false;
                    vle_update_input(inp);
                }
            } else if (input.type === 'radio') {
                for (let inp of vle_get_others_of_same_input_group(input)) {
                    // If we have the clear value option select that.
                    inp.checked = inp.value === '';
                    vle_update_input(inp);
                }
            } else {
                if (input.value !== '') {
                    input.value = '';
                    vle_update_input(input);
                }
            }

            vle_update_input(input);
            break;
        case 'register-button-listener':
            // 1. Find the element.
            element = vle_get_element(msg.target);

            if (element === null) {
                // Requested something that is not available.
                const ret = {
                    version: 'STACK-JS:1.2.0',
                    type: 'error',
                    msg: 'Failed to find element: "' + msg.target + '"',
                    tgt: msg.src
                };
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(ret), '*');
                return;
            }

            // 2. Add a listener, no need to do anything more
            // complicated than this.
            element.addEventListener('click', (event) => {
                let resp = {
                    version: 'STACK-JS:1.2.0',
                    type: 'button-click',
                    name: msg.target,
                    tgt: msg.src
                };
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(resp), '*');
                // These listeners will stop the submissions of buttons which might be a problem.
                event.preventDefault();
            });

            break;

        case 'clicked-button':  
            // 1. Find the button.
            button = vle_get_button_element(msg.name, msg.src);

            if (button === null) {
                // Requested something that is not available.
                const ret = {
                    version: 'STACK-JS:1.0.0',
                    type: 'error',
                    msg: 'Failed to click button: "' + msg.name + '"',
                    tgt: msg.src
                };
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(ret), '*');
                return;
            }

            // Trigger VLE side actions.
            vle_update_button(button);

            // Tell all other frames, that care, about this.
            response.type = 'clicked-button';
            response.name = msg.name;

            for (let tgt of BUTTONS[button.id]) {
                if (tgt !== msg.src) {
                    response.tgt = tgt;
                    IFRAMES[tgt].contentWindow.postMessage(JSON.stringify(response), '*');
                }
            }

            break;
 
        case 'toggle-visibility':
            // 1. Find the element.
            element = vle_get_element(msg.target);

            if (element === null) {
                // Requested something that is not available.
                const ret = {
                    version: 'STACK-JS:1.0.0',
                    type: 'error',
                    msg: 'Failed to find element: "' + msg.target + '"',
                    tgt: msg.src
                };
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(ret), '*');
                return;
            }

            // 2. Toggle display setting.
            if (msg.set === 'show') {
                element.style.display = 'block';
                // If we make something visible we should let the VLE know about it.
                vle_update_dom(element);
            } else if (msg.set === 'hide') {
                element.style.display = 'none';
            }

            break;
        case 'change-content':
            // 1. Find the element.
            element = vle_get_element(msg.target);

            if (element === null) {
                // Requested something that is not available.
                response.type = 'error';
                response.msg = 'Failed to find element: "' + msg.target + '"';
                response.tgt = msg.src;
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
                return;
            }

            // 2. Secure content.
            // 3. Switch the content. Note the contents coming from `vle_html_sanitize`
            // are wrapped in an element possibly `<body>` and will need to be unwrapped.
            // We can simply use innerHTML here to also disconnect the content from
            // whatever it was before being sanitized.
            element.innerHTML = vle_html_sanitize(msg.content).innerHTML;
            // If we tune something we should let the VLE know about it.
            vle_update_dom(element);

            break;
        case 'get-content':
            // 1. Find the element.
            element = vle_get_element(msg.target);
            // 2. Build the message.
            response.type = 'xfer-content';
            response.tgt = msg.src;
            response.target = msg.target;
            response.content = null;
            if (element !== null) {
                // TODO: Should we sanitise the content? Probably not as using
                // this to interrogate neighbouring questions only allows
                // messing with the other questions and not anything outside
                // them. If we do not sanitise it we allow some interesting
                // question-analytics tooling, and if we do we really don't
                // gain anything sensible.
                // Matti's opinnion is to not sanitise at this point as
                // interraction between questions is not inherently evil
                // and could be of use even at the level of reading code from
                // from other questions.
                response.content = element.innerHTML;
            }
            IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            break;
        case 'resize-frame':
            // 1. Find the frames wrapper div.
            element = IFRAMES[msg.src].parentElement;

            // 2. Set the wrapper size.
            element.style.width = msg.width;
            element.style.height = msg.height;

            // 3. Reset the frame size.
            IFRAMES[msg.src].style.width = '100%';
            IFRAMES[msg.src].style.height = '100%';

            // Only touching the size but still let the VLE know.
            vle_update_dom(element);
            break;
        case 'ping':
            // This is for testing the connection. The other end will
            // send these untill it receives a reply.
            // Part of the logic for startup.
            response.type = 'ping';
            response.tgt = msg.src;

            IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            return;
        case 'query-submit-button':
            response.type = 'submit-button-info';
            response.tgt = msg.src;
            input = vle_get_submit_button(msg.src);
            if (input === null || input.hasAttribute('hidden')) {
                response['value'] = null;
            } else {
                response['value'] = input.value;
            }
            IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            return;
        case 'enable-submit-button':
            input = vle_get_submit_button(msg.src);
            if (input !== null) {
                if (msg.enabled) {
                    input.removeAttribute('disabled');
                } else {
                    input.disabled = true;
                }
            } else {
                // We generate this error just to push people to properly check if
                // the button even exists before trying to tune it.
                response.type = 'error';
                response.msg = 'Could not find matching submit button for this question.';
                response.tgt = msg.src;
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            }
            return;
        case 'relabel-submit-button':
            input = vle_get_submit_button(msg.src);
            if (input !== null) {
                input.value = msg.name;
            } else {
                // We generate this error just to push people to properly check if
                // the button even exists before trying to tune it.
                response.type = 'error';
                response.msg = 'Could not find matching submit button for this question.';
                response.tgt = msg.src;
                IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
            }
            return;
        case 'submit-button-info':
        case 'initial-input':
        case 'initial-button':
        case 'error':
            // These message types are for the other end.
            break;

        default:
            // If we see something unexpected, lets let the other end know
            // and make sure that they know our version. Could be that this
            // end has not been upgraded.
            response.type = 'error';
            response.msg = 'Unknown message-type: "' + msg.type + '"';
            response.tgt = msg.src;

            IFRAMES[msg.src].contentWindow.postMessage(JSON.stringify(response), '*');
        }

    });


    return {
        /* To avoid any logic that forbids IFRAMEs in the VLE output one can
           also create and register that IFRAME through this logic. This
           also ensures that all relevant security settigns for that IFRAME
           have been correctly tuned.

           Here the IDs are for the secrect identifier that may be present
           inside the content of that IFRAME and for the question that contains
           it. One also identifies a DIV element that marks the position of
           the IFRAME and limits the size of the IFRAME (all IFRAMEs this
           creates will be 100% x 100%).

           @param {String} iframeid the id that the IFRAME has stored inside
                  it and uses for communication.
           @param {String} the full HTML content of that IFRAME.
           @param {String} targetdivid the id of the element (div) that will
                  hold the IFRAME.
           @param {String} title a descriptive name for the iframe.
           @param {bool} scrolling whether we have overflow:scroll or
                  overflow:hidden.
           @param {bool} evil allows certain special cases to act without
                  sandboxing, this is a feature that will be removed so do
                  not rely on it only use it to test STACK-JS before you get your
                  thing to run in a sandbox.
         */
        create_iframe(iframeid, content, targetdivid, title, scrolling, evil) {
            const frm = document.createElement('iframe');
            frm.id = iframeid;
            frm.style.width = '100%';
            frm.style.height = '100%';
            frm.style.border = 0;
            if (scrolling === false) {
                frm.scrolling = 'no';
                frm.style.overflow = 'hidden';
            } else {
                frm.scrolling = 'yes';
            }
            frm.title = title;
            // Somewhat random limitation.
            frm.referrerpolicy = 'no-referrer';
            // We include that allow-downloads as an example of XLS-
            // document building in JS has been seen.
            // UNDER NO CIRCUMSTANCES DO WE ALLOW-SAME-ORIGIN!
            // That would defeat the whole point of this.
            if (!evil) {
                frm.sandbox = 'allow-scripts allow-downloads';
            }

            // As the SOP is intentionally broken we need to allow
            // scripts from everywhere.

            // NOTE: this bit commented out as long as the csp-attribute
            // is not supported by more browsers.
            // frm.csp = "script-src: 'unsafe-inline' 'self' '*';";
            // frm.csp = "script-src: 'unsafe-inline' 'self' '*';img-src: '*';";

            // Plug the content into the frame.
            frm.srcdoc = content;

            // The target DIV will have its children removed.
            // This allows that div to contain some sort of loading
            // indicator until we plug in the frame.
            // Naturally the frame will then start to load itself.
            document.getElementById(targetdivid).replaceChildren(frm);
            IFRAMES[iframeid] = frm;
        }

    };
});
 