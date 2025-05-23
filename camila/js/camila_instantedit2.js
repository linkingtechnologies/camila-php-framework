//inspired by a script by http://www.yvoschaap.com
var camila_inline_object;
var urlBase = "update.php";
var formVars = "";
var changing = false;
//var enterK = false;

//XMLHttpRequest class function
function datosServidor() {};

datosServidor.prototype.iniciar = function() {
    try {
        // Mozilla / Safari
        this._xh = new XMLHttpRequest();
    } catch (e) {
        // Explorer
        var _ieModelos = new Array(
            'MSXML2.XMLHTTP.5.0',
            'MSXML2.XMLHTTP.4.0',
            'MSXML2.XMLHTTP.3.0',
            'MSXML2.XMLHTTP',
            'Microsoft.XMLHTTP'
        );

        var success = false;
        for (var i = 0; i < _ieModelos.length && !success; i++) {
            try {
                this._xh = new ActiveXObject(_ieModelos[i]);
                success = true;
            } catch (e) {}
        }

        if (!success) {
            return false;
        }

        return true;
    }
}

datosServidor.prototype.ocupado = function() {
    estadoActual = this._xh.readyState;
    return (estadoActual && (estadoActual < 4));
}

datosServidor.prototype.procesa = function() {
    if (this._xh.readyState == 4 && this._xh.status == 200) {
        this.procesado = true;
    }
}

datosServidor.prototype.enviar = function(urlget, datos) {
    if (!this._xh) {
        this.iniciar();
    }

    if (!this.ocupado()) {
        this._xh.open("GET", urlget, false);
        this._xh.send(datos);
        if (this._xh.readyState == 4 && this._xh.status == 200) {
            return this._xh.responseText;
        }
    }
    return false;
}


function fieldBlur(campo, idfld) {
    //console.log('fieldBlur');
    if (enterK)
        return false;

    enterK = true;

    elem = document.getElementById(idfld);

    var url = camila_inline_script + "camila_inline&" + camila_inline_object["name"] + "=" + escape(campo.value) + "&time=" + new Date().getTime();

    for (var key in camila_inline_object) {
        if (key != 'name' && key != 'value');
        url = url + "&" + key + "=" + escape(camila_inline_object[key]);
    }

    $.ajax({
        url: url
    }).done(function(nt) {
        eval("var result = " + nt);

        if (result['result'] == "OK") {
            if (result['type'] == 'select') {
                elem.innerHTML = symbolsToEntities(result['options'][result['value']]);
            } else
                elem.innerHTML = symbolsToEntities(result['value']);
        } else {
            alert(result['error_desc']);
            elem.innerHTML = camila_inline_object['value'];
        }

        changing = false;
        $('#' + idfld).editable('hide', null);
        return false;

        elem = document.getElementById(idfld);

        if (camila_inline_object['type'] == 'select') {
            elem.innerHTML = symbolsToEntities(camila_inline_object['options'][campo.value]);
        } else
            elem.innerHTML = symbolsToEntities(campo.value);

    });
}

function camila_changeBool(idfld, value, imgsrc) {

    elem = document.getElementById(idfld);

    var url = camila_inline_script + "camila_inline&" + camila_inline_object["name"] + "=" + escape(value) + "&time=" + new Date().getTime();

    for (var key in camila_inline_object) {

        if (key != 'name' && key != 'value');
        url = url + "&" + key + "=" + escape(camila_inline_object[key]);
    }

    remotos = new datosServidor;
    nt = remotos.enviar(url, "");
    eval("var result = " + nt);
    if (result['result'] == "OK") {
        if (result['type'] == 'select') {
            var html = "<img src=\"" + imgsrc + "\" alt=\"\" style=\"vertical-align:middle; border-style:none\" />";
            elem.innerHTML = html;
        } else
            elem.innerHTML = symbolsToEntities(result['value']);
    } else {
        alert(result['error_desc']);
        elem.innerHTML = camila_inline_object['value'];
    }


    changing = false;
    return false;
}


function camila_editBox(actualParent) {	
    if (changing) {
        return;
	}

    var changingBool = false;
    actual = xFirstChild(actualParent, 'span');

    enterK = false;
    var field = actual.id.substr(0, actual.id.indexOf("__cf__"));
    var url = camila_inline_script + camila_inline[actual.id.substr(actual.id.indexOf("__cf__"))] + "&camila_inline&camila_inline_field=" + field;
    $.ajax({
        url: url
    }).done(function(nt) {

        eval("camila_inline_object = " + nt);

        if (camila_inline_object == null)
            alert('null object...');

        if (camila_inline_object['result'] != 'OK')
		{
            return;
		}
		
        if (!changing) {
            var html = '';
            if (camila_inline_object['type'] == 'text') {
                $('#' + actual.id).editable({
                    type: 'text',
                    defaultValue: camila_inline_object['value']/*.replace(/[\"]/g, "&quot;")*/,
                    value: camila_inline_object['value']/*.replace(/[\"]/g, "&quot;")*/,
                    pk: actual.id,
                    display: false,
                    emptytext: '',
                    params: camila_inline_object,
                    success: function(response, newValue) {
                        var val = $('#' + actual.id).editable();
                        var campo = {
                            value: newValue
                        };
                        fieldBlur(campo, actual.id);
                    }
                });
                $('#' + actual.id).on('hidden', function(e, reason) {
                    changing = false;
                });
                $('#' + actual.id).editable('show');
				setTimeout(function() {
					$('.editable-input input').focus();
				}, 100);
            }

            if (camila_inline_object['type'] == 'textarea') {
                $('#' + actual.id).editable({
                    type: 'textarea',
                    pk: actual.id,
                    display: false,
                    emptytext: '',
                    params: camila_inline_object,
                    success: function(response, newValue) {
                        var val = $('#' + actual.id).editable();
                        var campo = {
                            value: newValue
                        };
                        fieldBlur(campo, actual.id);
                    }
                });
                $('#' + actual.id).on('hidden', function(e, reason) {
                    changing = false;
                });
                $('#' + actual.id).editable('show');
				setTimeout(function() {
					$('.editable-input textarea').focus();
				}, 100);
				
            }

            if (camila_inline_object['type'] == 'select') {
                if (field.substr(0, 8) == 'cf_bool_') {
                    changingBool = true;
                    var imgsrc = "";
                    var val = "";

                    for (var key in camila_inline_object['options']) {
                        if (key == camila_inline_object['value'])
                            html += "";
                        else {
                            imgsrc = "../../camila/images/png/" + field + "_" + key + ".png";
                            val = key;
                            html += "<img src=\"" + imgsrc + "\" alt=\"\" style=\"vertical-align:middle; border-style:none\" />";
                        }
                    }
                    html += '';
                    camila_changeBool(actual.id, val, imgsrc);
                    changing = false;
                } else {
                    var arr = [];
                    var defVal = "";
                    for (var key in camila_inline_object['options']) {
                        if (key == camila_inline_object['value'])
                            defVal = camila_inline_object['value'];
                        arr.push({
                            value: key/*.replace(/[\"]/g, "&quot;")*/,
                            text: camila_inline_object['options'][key]
                        });
                    }


                    $('#' + actual.id).editable({
                        type: 'select',
                        display: false,
                        emptytext: '',
                        defaultValue: defVal,
                        source: arr,
                        pk: actual.id,
                        params: camila_inline_object,
                        success: function(response, newValue) {
                            var campo = {
                                value: newValue
                            };
                            fieldBlur(campo, actual.id);
                        }
                    });
                    $('#' + actual.id).on('hidden', function(e, reason) {
                        changing = false;
                        //$('#' + actual.id).editable('hide',null);
                    });
                    $('#' + actual.id).editable('show');

                }
            }
        }
    });
}


function camila_inline_editbox_init() {
	$.fn.editableform.buttons = '<button type="submit" class="editable-submit">OK</button>'+
    '<button type="button" class="editable-cancel">Annulla</button>';
    tips = xGetElementsByClassName("cf_editText");
    for (i = 0; i < tips.length; ++i) {
        xParent(tips[i], false).onclick = function(e) {
			if (e.target.tagName.toLowerCase() != 'button' && e.target.tagName.toLowerCase() != 'i') {
				camila_editBox(this);
			}
			else {e.stopPropagation();}
        }
        tips[i].style.cursor = "pointer";
        tips[i].style.display = "block";
    }

}

//crossbrowser load function
function addEvent(elm, evType, fn, useCapture) {
    if (elm.addEventListener) {
        elm.addEventListener(evType, fn, useCapture);
        return true;
    } else if (elm.attachEvent) {
        var r = elm.attachEvent("on" + evType, fn);
        return r;
    } else {
        alert("Please upgrade your browser to use full functionality on this page");
    }
}


function highLight(span) {
    //span.parentNode.style.border = "2px solid #D1FDCD";
    //span.parentNode.style.padding = "0";
    span.style.border = "1px solid #54CE43";
}


function noLight(span) {
    //span.parentNode.style.border = "0px";
    //span.parentNode.style.padding = "2px";
    span.style.border = "0px";
}

//sets post/get vars for update
function setVarsForm(vars) {
    formVars = vars;
}

function symbolsToEntities(sText) {
    var sNewText = "";
    var iLen = sText.length;
    for (i = 0; i < iLen; i++) {
        iCode = sText.charCodeAt(i);
        sNewText += (iCode > 256 ? "&#" + iCode + ";" : sText.charAt(i));
    }
    return sNewText;
}

function uni2ent2ndTry(srcTxt) {
    var entTxt = '';
    var c, hi, lo;
    var len = 0;
    for (var i = 0, code; code = srcTxt.charCodeAt(i); i++) {
        // need to convert to HTML entity
        if (code > 255) {
            // values in this range are surrogate pairs
            if (0xD800 <= code && code <= 0xDBFF) {
                hi = code;
                lo = srcTxt.charCodeAt(i + 1);
                lo &= 0x03FF;
                hi &= 0x03FF;
                hi = hi << 10;
                code = (lo + hi) + 0x10000;
            }
            // wrap it up as a Hex entity
            c = "&#x" + code.toString(16).toUpperCase() + ";";
        }
        // smaller values can be used raw
        else {
            c = srcTxt.charAt(i);
        }
        entTxt += c;
    }
    return entTxt;
}

function addSlashes(str) {
	return(str);
    // Escapes single quote, double quotes and backslash characters in a string with backslashes  
    // 
    // version: 908.406
    // discuss at: http://phpjs.org/functions/addslashes
    // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Ates Goral (http://magnetiq.com)
    // +   improved by: marrtins
    // +   improved by: Nate
    // +   improved by: Onno Marsman
    // +   input by: Denny Wardhana
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // *     example 1: addslashes("kevin's birthday");
    // *     returns 1: 'kevin\'s birthday'

    return (str + '').replace(/([\\"'])/g, "\\$1").replace(/\u0000/g, "\\0");
}

function urlencode(str) {
    // URL-encodes string  
    // 
    // version: 908.2210
    // discuss at: http://phpjs.org/functions/urlencode
    // +   original by: Philip Peterson
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      input by: AJ
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +      input by: travc
    // +      input by: Brett Zamir (http://brett-zamir.me)
    // +   bugfixed by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +   improved by: Lars Fischer
    // +      input by: Ratheous
    // +      reimplemented by: Brett Zamir (http://brett-zamir.me)
    // %          note 1: This reflects PHP 5.3/6.0+ behavior
    // *     example 1: urlencode('Kevin van Zonneveld!');
    // *     returns 1: 'Kevin+van+Zonneveld%21'
    // *     example 2: urlencode('http://kevin.vanzonneveld.net/');
    // *     returns 2: 'http%3A%2F%2Fkevin.vanzonneveld.net%2F'
    // *     example 3: urlencode('http://www.google.nl/search?q=php.js&ie=utf-8&oe=utf-8&aq=t&rls=com.ubuntu:en-US:unofficial&client=firefox-a');
    // *     returns 3: 'http%3A%2F%2Fwww.google.nl%2Fsearch%3Fq%3Dphp.js%26ie%3Dutf-8%26oe%3Dutf-8%26aq%3Dt%26rls%3Dcom.ubuntu%3Aen-US%3Aunofficial%26client%3Dfirefox-a'
    var hexStr = function(dec) {
        return '%' + dec.toString(16).toUpperCase();
    };

    var ret = '',
        unreserved = /[\w.-]/; // A-Za-z0-9_.- // Tilde is not here for historical reasons; to preserve it, use rawurlencode instead
    str = (str + '').toString();

    for (var i = 0, dl = str.length; i < dl; i++) {
        var ch = str.charAt(i);
        if (unreserved.test(ch)) {
            ret += ch;
        } else {
            var code = str.charCodeAt(i);
            // Reserved assumed to be in UTF-8, as in PHP
            if (code === 32) {
                ret += '+'; // %20 in rawurlencode
            } else if (code < 128) { // 1 byte
                ret += hexStr(code);
            } else if (code >= 128 && code < 2048) { // 2 bytes
                ret += hexStr((code >> 6) | 0xC0);
                ret += hexStr((code & 0x3F) | 0x80);
            } else if (code >= 2048 && code < 65536) { // 3 bytes
                ret += hexStr((code >> 12) | 0xE0);
                ret += hexStr(((code >> 6) & 0x3F) | 0x80);
                ret += hexStr((code & 0x3F) | 0x80);
            } else if (code >= 65536) { // 4 bytes
                ret += hexStr((code >> 18) | 0xF0);
                ret += hexStr(((code >> 12) & 0x3F) | 0x80);
                ret += hexStr(((code >> 6) & 0x3F) | 0x80);
                ret += hexStr((code & 0x3F) | 0x80);
            }
        }
    }
    return ret;
}


function camila_inline_update_selected(field, value) {

    if (value == '') {
        value = window.prompt("Inserire il nuovo valore:", "");

        if (value == '' || value == null) {
            alert('Operazione non eseguita!');
            return;
        }

    }


    var mySplitResult = camila_selectedIds.split(",");

    for (j = 0; j < mySplitResult.length; j++) {

        if (mySplitResult[j] != "") {
            //            camila_inline_update_by_id(mySplitResult[i],field,value);

            var id = mySplitResult[j];
            var url = camila_inline_script + camila_inline[id] + "&camila_inline&camila_inline_field=" + field;

            remotos = new datosServidor;
            nt = remotos.enviar(url);

            eval("camila_inline_object = " + nt);

            if (camila_inline_object == null)
                alert('null object...');

            if (camila_inline_object['result'] != 'OK')
                return;

            var url = camila_inline_script + "camila_inline&" + camila_inline_object["name"] + "=" + escape(value) + "&time=" + new Date().getTime();

            for (var key in camila_inline_object) {

                if (key != 'name' && key != 'value');
                url = url + "&" + key + "=" + escape(camila_inline_object[key]);
            }

            elem = document.getElementById(field + id);

            nt = remotos.enviar(url, "");
            eval("var result = " + nt);

            if (result['result'] == "OK") {
                if (result['type'] == 'select') {
                    elem.innerHTML = symbolsToEntities(result['options'][result['value']]);
                } else
                    elem.innerHTML = symbolsToEntities(result['value']);
            } else {
                alert(result['error_desc']);
                elem.innerHTML = camila_inline_object['value'];
            }

        }
    }

}