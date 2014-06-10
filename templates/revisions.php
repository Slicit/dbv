<h2 class="pull-left"><?php echo __('Revisions'); ?></h2>

	<div class="input-append pull-right">
		<input type="text" id="revision_id" value="" class="input-large" placeholder="revision #id" />
		<button id="add_revision" class="btn btn-info">New revision</button>
	</div>
	
	<div style="clear:both"></div>

	<form method="post" action="" class="nomargin" id="revisions">
		<div class="log">
			<?php if (!isset($this->revisions) || count($this->revisions)==0): ?>
			<div class="alert alert-error"><button class="close pull-right">×</button>
				<ul class="unstyled nomargin">
					<li class="last"><?php echo __('No revisions in #{path}', array('path' => '<strong>' . DBV_REVISIONS_PATH . '</strong>')) ?></li>
				</ul>
			</div>
			<?php endif; ?>
		</div>

		
		<h2>
			<input type="submit" class="btn btn-primary" value="Run selected revisions" />
		</h2>
		
		
		<table id="table-sorter" class="table table-condensed table-striped table-bordered">
			<thead>
				<tr>
					<th style="width: 13px;"><input type="checkbox" id="toggle-cb" style="margin-top: 0;" /></th>
					<th><?php echo __('Revision ID'); ?></th>
				</tr>
			</thead>
			<tbody id="body_revisions">
				<?php 
					foreach ($this->revisions as $revision) {
						include 'revision-single.php';
					} 
				?>
			</tbody>
		</table>
		<input type="submit" class="btn btn-primary" value="Run selected revisions" />
	</form>

	

<script type="text/javascript">
$(function(){
	/// Toggle SQL Files (+ refresh code mirror editor)
	$(document).on('click', '.revision-handle', function(event){
		var files = $(this).parents('td').children('.revision-files');
		files.toggle();

		$('textarea[data-role="editor"]').each( function(){
			$(this).data('editor').refresh();
		});
	});

	/** Add revision */
	$('#revision_id').on('keyup', function(event){
		var key = event.keyCode || event.which;
		if(key == 13){
			$('#add_revision').trigger('click');
		}
	});

	/** Toggle checkboxes to select "to run" revisions */
	$('#toggle-cb').on('click', function(){
		var toggleValue = $(this).prop('checked');
		$('.toggle-cb').each(function(){
			if( !$(this).parents('tr').hasClass('ran') ){
				$(this).prop('checked', toggleValue);
			}
		});
	});

	/** Handle the action to add a new revision */
	$(document).on('click', '#add_revision', function(event){
		event.preventDefault();

		var revision = $('#revision_id').val();
		if($.trim(revision) == ''){
			render_messages('error', '#revisions', '<?php echo __('Revision ID is empty !') ?>');
			return;
		}

		$.ajax({
			url: 'index.php?a=addRevisionFolder',
			data: {'revision': revision},
			success: function(response){
				if(response.ok){
					render_messages('success', '#revisions', response.message);

					$('#body_revisions').prepend(response.html);

					/// Remove the save buttons
					$(response.revision).each(function(value){
						value = value.split('/')[0];
						$('tr[data-revision='+value+'] button[data-role="editor-save"]').remove();
					});

					reloadCodeMirror();

					$("#table-sorter").trigger("update"); 
				}
				else{
					render_messages('error', '#revisions', response.message);
				}
			}
		});
	});

	/** Save SQL files modifications */
	$(document).on('click', 'button[data-role="editor-save"]', function(event){
		var button = $(this);
		var editor = $(button.parents('.heading').next('textarea').get(0)).data('editor');
		var container = button.parents('[id^="revision-file"]');

		button.attr('disabled', 'disabled');

		$.ajax({
			url: 'index.php?a=saveRevisionFile',
			type: 'POST',
			data: {
				revision: button.data('revision'),
				file: button.data('file'),
				content: editor.getValue()
			},
			success: function(response){
				button.removeAttr('disabled');

				if (response.error) {
					return render_messages('error', container, response.error);
				}

				render_messages('success', container, response.message);
			}
		});
	});

	/// Form Submit
	$('#revisions').on('submit', function (event){
		event.preventDefault();

		var form = $(this);

		$.ajax({
			url: 'index.php?a=revisions',
			type: 'POST',
			data: form.serializeArray(),
			success: function(response){
				if (response.messages.error) {
                    render_messages('error', '#revisions', response.messages.error, '<?php echo __('The following errors occured:'); ?>');
                }

                if (response.messages.success) {
                    render_messages('success', '#revisions', response.messages.success, '<?php echo __('The following actions completed successfuly:'); ?>');
                }

                <?php if(DBV_REVISION_INDEX == 'LAST'): ?>
                var revision = parseInt(response.revision);
                if (!isNaN(revision)) {
                	var rows = form.find('tr[data-revision]');

            		rows.each(function(){
                		var row = $(this);
                		row.removeClass('ran');
            			if (row.data('revision') > revision) {
            				return;
            			}
            			row.addClass('ran');
            			row.find('.revision-files').first().hide();
            			row.find('input[type="checkbox"]').first().prop('checked', false);
            		});
                }
                <?php else: ?>
                revisions = [];
                $(response.revision).each(function(index, value){
                    var id = value.split('/')[0];
                    if($.inArray(parseInt(id), revisions) == -1){
                    	revisions.push(parseInt(id));
                    }
                });
				var rows = form.find('tr[data-revision]');
				rows.each(function(){
					var row = $(this);
					if($.inArray(parseInt(row.data('revision')), revisions) != -1){ 
						row.addClass('ran');
                		row.find('input[type="checkbox"]').first().prop('checked', false);
					}
					else{
						row.removeClass('ran');
            			row.find('input[type="checkbox"]').first().prop('checked', true);
					}
				});	
                <?php endif; ?>
			}
		});
	
		
	});

	/// Table sorter simple by revision
	$('#table-sorter').tablesorter({
		headers: { 0: {'sorter' : false} },
		textExtraction: getRevision
	}); 

	/// Call the CodeMirror
	reloadCodeMirror();
});
</script>
