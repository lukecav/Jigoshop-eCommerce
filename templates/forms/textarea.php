<?php
/**
 * @var $id string Field ID.
 * @var $label string Field label.
 * @var $name string Field name.
 * @var $classes array List of classes to add to the field.
 * @var $rows int Number of rows to display.
 * @var $value mixed Current value.
 * @var $disabled bool Is field disabled?
 * @var $tip string Tip to show to the user.
 * @var $description string Field description.
 * @var $hidden boolean Whether the field is hidden.
 * @var $size int Size of form widget.
 */
?>
<div class="form-group <?php echo $id; ?>_field clearfix<?php $hidden and print ' not-active'; ?>">
	<label for="<?php echo $id; ?>" class="col-sm-<?php echo $size == 12 ? 12 : 12 - $size; ?> control-label">
		<?php echo $label; ?>
		<?php if(!empty($tip)): ?>
			<a href="#" data-toggle="tooltip" class="badge" data-placement="top" title="<?php echo $tip; ?>">?</a>
		<?php endif; ?>
	</label>
	<div class="col-sm-<?php echo $size; ?>">
		<textarea rows="<?php echo $rows; ?>" id="<?php echo $id; ?>" name="<?php echo $name; ?>" class="form-control <?php echo join(' ', $classes); ?>"<?php $disabled and print ' disabled'; ?>><?php echo $value; ?></textarea>
		<?php if(!empty($description)): ?>
			<span class="help-block"><?php echo $description; ?></span>
		<?php endif; ?>
	</div>
</div>
