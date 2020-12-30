<div class="form-group">
	<label class="custom-control custom-checkbox form-group mb-1 mr-0 d-block">
		<input type="hidden" name="say" value="0"/>
		<input type="checkbox" name="say"
		       class="custom-control-input form-check-input" value="1"
		       @if (array_get($app->getOptions(), 'say', false)) checked="CHECKED" @endif />
		<span class="custom-control-indicator"></span>
		Say something nice
	</label>
</div>