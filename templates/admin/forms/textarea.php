<?php
use Jigoshop\Admin\Helper\Forms;

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
$hasLabel = !empty($label);
?>
<div class="form-group <?php echo $id; ?>_field <?php echo join(' ',
    $classes); ?><?php $hidden and print ' not-active'; ?>">
    <div class="row">
        <div class="col-sm-<?php echo $size; ?>">
            <?php if ($hasLabel): $size -= 2; ?>
                <label for="<?php echo $id; ?>" class="col-xs-12 col-sm-2 margin-top-bottom-9">
                    <?php echo $label; ?>
                </label>
            <?php endif; ?>
            <div class="col-xs-12 col-sm-<?php echo $size ?> clearfix">
                <div class="tooltip-inline-badge">
                    <?php if (!empty($tip)): ?>
                        <span data-toggle="tooltip" class="badge margin-top-bottom-9" data-placement="top"
                              title="<?php echo $tip; ?>">?</span>
                    <?php endif; ?>
                </div>
                <div class="tooltip-inline-input">
                    <textarea rows="<?php echo $rows; ?>" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                              class="form-control <?php echo join(' ',
                                  $classes); ?>"<?php echo Forms::disabled($disabled); ?>><?php echo $value; ?></textarea>
                    <?php if (!empty($description)): ?>
                        <span class="help-block"><?php echo $description; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
