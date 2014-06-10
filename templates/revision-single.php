<?php
	$ran = $this->_isRan($revision);
	$class = array();
	if ($ran) {
		$class[] = 'ran';
	}

	$files = $this->_getRevisionFiles($revision);
	$class_files = count($files) ? 'files' : '';
?>
<tr data-revision="<?php echo $revision; ?>"<?php echo count($class) ? ' class="' . implode(' ', $class) . '"'  : ''; ?>>
	<td class="center">
		<input type="checkbox" name="revisions[]" class="toggle-cb" value="<?php echo $revision; ?>"<?php echo $ran ? '' : ' checked="checked"'; ?> style="margin-top: 7px;" />
	</td>
	<td>
		<h3 class="nomargin">
			<a href="javascript:" class="revision-handle <?php echo $class_files; ?>"><?php echo $revision; ?></a>
			<?php if(DBV_TRACKER_LINK === true): ?>
			<a href="<?php echo str_replace('%id%', $revision, DBV_TRACKER_URI) ?>" class="tracker" target="_blank"><?php echo __('Tracker') ?></a>
			<?php endif; ?>
		</h3>

		<?php if (count($files)) { ?>
			<div class="revision-files" style="display: none;">
				<?php $i = 0; ?>
				<?php foreach ($files as $file) { ?>
					<?php
						$extension = pathinfo($file, PATHINFO_EXTENSION);
						$content = htmlentities($this->_getRevisionFileContents($revision, $file), ENT_QUOTES, 'UTF-8');
						$lines = substr_count($content, "\n");
						$ranFile = $this->_isRanFile($revision.'/'.$file);
					?>
					<div id="revision-file-<?php echo $revision; ?>-<?php echo ++$i; ?>">
						<div class="log"></div>
						<div class="alert alert-info heading">
							<?php if(!$ran): ?>
								<button data-role="editor-save" data-revision="<?php echo $revision; ?>" data-file="<?php echo $file; ?>" type="button" class="btn btn-mini btn-info pull-right" style="margin-top: -1px; margin-left: 6px;"><?php echo __('Save file') ?></button>
							<?php endif; ?>
							<?php if(false && !$ranFile): ?>
								<button data-revision="<?php echo $revision; ?>" data-file="<?php echo $file; ?>" type="button" class="btn btn-mini btn-info pull-right" style="margin-top: -1px; margin-left: 6px;"><?php echo __('Run file') ?></button>
							<?php endif; ?>
							<strong class="alert-heading"><?php echo $file; ?></strong>
						</div>
						<textarea data-role="editor" name="revision_files[<?php echo $revision; ?>][<?php echo $file; ?>]" rows="<?php echo $lines + 1; ?>"><?php echo $content; ?></textarea>
					</div>
				<?php } ?>
			</div>
		<?php } ?>
	</td>
</tr>