<h2><?php echo __('Revisions'); ?></h2>
<?php if (isset($this->revisions) && count($this->revisions)) { ?>
	<form method="post" action="" class="nomargin" id="revisions">
		<div class="log"></div>

		
		<h2>
			<input type="submit" class="btn btn-primary" value="Run selected revisions" />
			&nbsp;
			<div class="input-append pull-right">
				<input type="text" id="revision_id" name="revision_id" value="" class="input-large" placeholder="revision #id" />
				<button id="add_revision" class="btn btn-success">New revision</button>
			</div>
		</h2>
		
		
		<table class="table table-condensed table-striped table-bordered">
			<thead>
				<tr>
					<th style="width: 13px;"><input type="checkbox" style="margin-top: 0;" /></th>
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
<?php } else { ?>
	<div class="alert alert-info nomargin">
		<?php echo __('No revisions in #{path}', array('path' => '<strong>' . DBV_REVISIONS_PATH . '</strong>')) ?>
	</div>
<?php } ?>
<script type="text/javascript">
	document.observe('dom:loaded', function () {
		var form = $('revisions');
		if (!form) {
			return;
		}

		var textareas = form.select('textarea');
		textareas.each(function (textarea) {
			textarea['data-editor'] = CodeMirror.fromTextArea(textarea, {
				mode: "text/x-mysql",
				tabMode: "indent",
				matchBrackets: true,
				autoClearEmptyLines: true,
				lineNumbers: true,
				theme: 'default'
			});
		});

		$$('.revision-handle').invoke('observe', 'click', function (event) {
			var element = event.findElement('.revision-handle');
			var container = element.up('td').down('.revision-files');
			if (container) {
				container.toggle();
				if (!container.visible()) {
					return;
				}

				var textareas = container.select('textarea[data-role="editor"]');
				if (textareas) {
					textareas.each(function (textarea) {
						textarea['data-editor'].refresh();
					});
				}
			}
		});

		$$('button[data-role="editor-save"]').invoke('observe', 'click', function (event) {
			var self = this;

			var editor = this.up('.heading').next('textarea')['data-editor'];
			var container = this.up('[id^="revision-file"]');

			this.disable();

			clear_messages(container);

			new Ajax.Request('index.php?a=saveRevisionFile', {
				parameters: {
					revision: this.getAttribute('data-revision'),
					file: this.getAttribute('data-file'),
					content: editor.getValue()
				},
				onSuccess: function (transport) {
					self.enable();

					var response = transport.responseText.evalJSON();

					if (response.error) {
						return render_messages('error', container, response.error);
					}

					render_messages('success', container, response.message);
				}
			});
		});


		/** Add revision */
		$j(document).on('click', '#add_revision', function(event){
			event.preventDefault();

			clear_messages('revisions');

			var revision = $j('#revision_id').val();
			if($j.trim(revision) == ''){
				render_messages('error', 'revisions', '<?php echo __('Revision ID is empty !') ?>');
				return;
			}

			$j.ajax({
				url: 'index.php?a=addRevisionFolder',
				data: {'revision': revision},
				success: function(response){
					render_messages('success', 'revisions', response.message);

					$j('#body_revisions').prepend(response.html);
				}
			});
		});

		form.on('submit', function (event) {
			event.stop();

			var data = form.serialize(true);

			clear_messages(this);

			if (!data.hasOwnProperty('revisions[]')) {
				render_messages('error', this, "<?php echo __("You didn't select any revisions to run") ?>");
				Effect.ScrollTo('log', {duration: 0.2});
				return false;
			}

			form.disable();

			new Ajax.Request('index.php?a=revisions', {
				parameters: {
					"revisions[]": data['revisions[]']
				},
				onSuccess: function (transport) {
					form.enable();

                    var response = transport.responseText.evalJSON();

                    if (typeof response.error != 'undefined') {
                        return APP.growler.error('<?php echo __('Error!'); ?>', response.error);
                    }

                    if (response.messages.error) {
                        render_messages('error', 'revisions', response.messages.error, '<?php echo __('The following errors occured:'); ?>');
                    }

                    if (response.messages.success) {
                        render_messages('success', 'revisions', response.messages.success, '<?php echo __('The following actions completed successfuly:'); ?>');
                    }

                    <?php if(DBV_REVISION_INDEX == 'LAST'): ?>
                    var revision = parseInt(response.revision);
                    if (!isNaN(revision)) {
                    	var rows = form.select('tr[data-revision]');

                		rows.each(function (row) {
                			row.removeClassName('ran');
                			if (row.getAttribute('data-revision') > revision) {
                				return;
                			}
                			row.addClassName('ran');
                			row.down('.revision-files').hide();
                			row.down('input[type="checkbox"]').checked = false;
                		});
                    }
                    <?php else: ?>
					var revisions = [];
					response.revision.each(function(value){
						revisions[value] = value;
					});
					var rows = form.select('tr[data-revision]');
					rows.each(function (row) {
						if(revisions.indexOf( row.getAttribute('data-revision') ) != -1){ 
							row.addClassName('ran');
                    		row.down('input[type="checkbox"]').checked = false;
						}
						else{
							row.removeClassName('ran');
                			row.down('input[type="checkbox"]').checked = true;
						}
					});
                    <?php endif; ?>

                    Effect.ScrollTo('log', {duration: 0.2});
				}
			});
		});
	});
</script>
