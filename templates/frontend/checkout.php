<?php
/**
 *
 */
?>
<fieldset>
	<div class="row clearfix">
		<div class="form-group col-sm-6 clearfix">
			<?php \Jigoshop\Helper\Forms::text(array(
				'name' => 'paypal_pro[card][number]',
				'label' => __('Credit card number', 'jigoshop_paypal_pro_gateway'),
				'value' => ''
			)); ?>
			<div class="form-group col-xs-12 clearfix">
				<label for="cc-expire-month"><?php echo __("Expiration date", 'jigoshop_paypal_pro_gateway') ?></label>
				<div class="clear"></div>
				<select name="paypal_pro[card][exp_month]" id="cc-expire-month" class="col-xs-6">
					<option value=""><?php _e('Month', 'jigoshop_paypal_pro_gateway') ?></option>
					<?php foreach ($months as $num => $name): ?>
						<option value="<?php echo $num; ?>"><?php echo $name; ?></option>
					<?php endforeach; ?>
				</select>
				<select name="paypal_pro[card][exp_year]" id="cc-expire-year" class="col-xs-6">
					<option value=""><?php _e('Year', 'jigoshop_paypal_pro_gateway') ?></option>
					<?php foreach ($years as $year): ?>
						<option value="<?php echo $year; ?>"><?php echo $year; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="form-group col-sm-6 clearfix">
			<div class="form-group col-xs-12">
				<label for="paypal_pro_card_type"><?php echo __("Card type", 'jigoshop_paypal_pro_gateway') ?></label>
				<select name="paypal_pro[card][type]" id="paypal_pro_card_type" class="col-xs-12">
					<?php foreach ($availableCards as $card): ?>
						<option value="<?php echo $card ?>"><?php echo $card ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="clear"></div>
			<?php \Jigoshop\Helper\Forms::text(array(
				'name' => 'paypal_pro[card][csc]',
				'label' => __('Card security code', 'jigoshop_paypal_pro_gateway'),
				'description' => '<div class="paypal_pro_card_csc_description"></div>',
				'value' => ''
			)); ?>
		</div>
	</div>
</fieldset>
