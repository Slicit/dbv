/** Rendering messages inside the container */
function render_messages(type, container, messages, heading) {
	$('.alert-success, .alert-error').remove();
	
	var message = '';
	if(!$.isArray(messages)){
		messages = [messages];
	}

	var size = $(messages).length-1;
	$(messages).each(function(_index, _message){
		last = (_index == size) ? 'last' : '';
		message += '<li class="'+last+'">'+_message+'</li>';
	});
	
	if(message != ''){
		message = '<ul class="unstyled nomargin">'+message+'</ul>';
	}
	
	if(typeof heading == 'undefined'){
		heading = '';
	}

	var alert = 
	'<div class="alert alert-'+type+'">'+
		'<button class="close pull-right">&times;</button>'+
		heading+
		message+
	'</div>';

    $($(container).find('.log').get(0)).append( $(alert) );
}

/** Reload codemirror editors */
function reloadCodeMirror(){
	$('#revisions textarea').each(function(){
		$(this).data('editor', 
			CodeMirror.fromTextArea(this, {
				mode: "text/x-mysql",
				tabMode: "indent",
				matchBrackets: true,
				autoClearEmptyLines: true,
				lineNumbers: true,
				theme: 'default'
			})
		);
	});
}

