<?php
/**
 * eWAY Processor setup template.
 *
 */

if( ! is_ssl() ){
?>
	<div class="error" style="border-left-color: #FF0;">
		<p>
			<?php echo 'Your site is not using secure HTTPS. SSL/HTTPS is not required to use eWAY for Caldera Forms, but it is recommended.'; ?>
		</p>
	</div>
<?php } ?>

<div class="caldera-config-group">
	<label>API Key</label>
	<div class="caldera-config-field">		
		<input type="text" id="{{_id}}_key" class="block-input required field-config" name="{{_name}}[key]" value="{{key}}" required>
	</div>
</div>
<div class="caldera-config-group">
	<label>API Password</label>
	<div class="caldera-config-field">		
		<input type="text" id="{{_id}}_password" class="block-input required field-config" name="{{_name}}[password]" value="{{password}}" required>
	</div>
</div>

<div class="caldera-config-group">
	<label for="{{_id}}_sandbox">Sandbox Mode</label>
	<div class="caldera-config-field">
		<input id="{{_id}}_sandbox" type="checkbox" class="field-config" name="{{_name}}[sandbox]" value="1" {{#if sandbox}}checked="checked"{{/if}}>
	</div>
</div>

<div class="caldera-config-group">
	<label>Price Field</label>
	<div class="caldera-config-field">
		{{{_field slug="price" type="calculation,text,hidden" exclude="system"}}}
	</div>
</div>
<div class="caldera-config-group">
	<label>Currency</label>
	<div class="caldera-config-field">
		<select id="{{_id}}_currency" class="required field-config" name="{{_name}}[currency]">
			<option value="USD" {{#is currency value="USD"}}selected="selected"{{/is}}>USD</option>
			<option value="AUD" {{#is currency value="AUD"}}selected="selected"{{/is}}>AUD</option>
			<option value="BRL" {{#is currency value="BRL"}}selected="selected"{{/is}}>BRL</option>
			<option value="GBP" {{#is currency value="GBP"}}selected="selected"{{/is}}>GBP</option>
			<option value="CAD" {{#is currency value="CAD"}}selected="selected"{{/is}}>CAD</option>
			<option value="CZK" {{#is currency value="CZK"}}selected="selected"{{/is}}>CZK</option>
			<option value="DKK" {{#is currency value="DKK"}}selected="selected"{{/is}}>DKK</option>
			<option value="EUR" {{#is currency value="EUR"}}selected="selected"{{/is}}>EUR</option>
			<option value="HKD" {{#is currency value="HKD"}}selected="selected"{{/is}}>HKD</option>
			<option value="HUF" {{#is currency value="HUF"}}selected="selected"{{/is}}>HUF</option>
			<option value="ILS" {{#is currency value="ILS"}}selected="selected"{{/is}}>ILS</option>
			<option value="JPY" {{#is currency value="JPY"}}selected="selected"{{/is}}>JPY</option>
			<option value="MXN" {{#is currency value="MXN"}}selected="selected"{{/is}}>MXN</option>
			<option value="TWD" {{#is currency value="TWD"}}selected="selected"{{/is}}>TWD</option>
			<option value="NZD" {{#is currency value="NZD"}}selected="selected"{{/is}}>NZD</option>
			<option value="NOK" {{#is currency value="NOK"}}selected="selected"{{/is}}>NOK</option>
			<option value="PHP" {{#is currency value="PHP"}}selected="selected"{{/is}}>PHP</option>
			<option value="PLN" {{#is currency value="PLN"}}selected="selected"{{/is}}>PLN</option>
			<option value="RUB" {{#is currency value="RUB"}}selected="selected"{{/is}}>RUB</option>
			<option value="SGD" {{#is currency value="SGD"}}selected="selected"{{/is}}>SGD</option>
			<option value="SEK" {{#is currency value="SEK"}}selected="selected"{{/is}}>SEK</option>
			<option value="CHF" {{#is currency value="CHF"}}selected="selected"{{/is}}>CHF</option>
			<option value="THB" {{#is currency value="THB"}}selected="selected"{{/is}}>THB</option>
			<option value="MYR" {{#is currency value="MYR"}}selected="selected"{{/is}}>MYR</option>
			<option value="PHP" {{#is currency value="PHP"}}selected="selected"{{/is}}>PHP</option>
		</select>
	</div>
</div>
<div class="caldera-config-group">
	<label>Item Name</label>
	<div class="caldera-config-field">		
		<input type="text" id="{{_id}}_name" class="block-input field-config" name="{{_name}}[name]" value="{{name}}">
	</div>
</div>
<div class="caldera-config-group">
	<label>Item Description</label>
	<div class="caldera-config-field">		
		<input type="text" id="{{_id}}_desc" class="block-input field-config" name="{{_name}}[desc]" value="{{desc}}">
	</div>
</div>

<div class="caldera-config-group">
	<label>Quantity Field</label>
	<div class="caldera-config-field">
		{{{_field slug="qty" exclude="system"}}}
	</div>
</div>
