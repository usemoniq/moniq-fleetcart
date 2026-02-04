<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('moniq_enabled', trans('setting::attributes.moniq_enabled'), trans('setting::settings.form.enable_moniq'), $errors, $settings) }}
        {{ Form::text('translatable[moniq_label]', trans('setting::attributes.translatable.moniq_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[moniq_description]', trans('setting::attributes.translatable.moniq_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}

        <div class="{{ old('moniq_enabled', array_get($settings, 'moniq_enabled')) ? '' : 'hide' }}" id="moniq-fields">
            {{ Form::text('moniq_public_key', trans('setting::attributes.moniq_public_key'), $errors, $settings, ['required' => true]) }}
            {{ Form::password('moniq_api_secret', trans('setting::attributes.moniq_api_secret'), $errors, $settings, ['required' => true]) }}
        </div>
    </div>
</div>
