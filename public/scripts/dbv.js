/** Clear messages */
function clear_messages(container) {
    $(container).find('.alert-success, .alert-error').remove();
}

/** Rendering messages inside the container */
function render_messages(type, container, messages, heading) {
    /**var element = new Element('div', {
        className: 'alert alert-' + type
    });

    if (typeof heading != 'undefined') {
        heading = (new Element('strong', {
            className: 'alert-heading'
        })).update(heading);
    }

    var close = (new Element('button', {className: 'close pull-right'})).update('&times;');
        close.on('click', function () {
            this.up('.alert').remove();
        });
    element.insert(close);  

    if (typeof heading != 'undefined') {
        element.insert(heading);
    }

    if (!(messages instanceof Array)) {
        messages = [messages];
    }

    var list = new Element('ul', {className: 'unstyled nomargin'});
    for (var i = 0; i < messages.length; i++) {
        var item = new Element('li').update(messages[i]);
        if (i == messages.length - 1) {
            item.addClassName('last');
        }
        
        list.insert(item);
    }

    element.insert(list);

    $(container).down('.log').insert(element);*/
}

/** Reload codemirror editors */
function reloadCodeMirror(){
	var textareas = $('#revisions').find('textarea');
	textareas.each(function(){
		this['data-editor'] = CodeMirror.fromTextArea(this, {
			mode: "text/x-mysql",
			tabMode: "indent",
			matchBrackets: true,
			autoClearEmptyLines: true,
			lineNumbers: true,
			theme: 'default'
		});
	});
}

