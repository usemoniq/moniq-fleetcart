<div class="row">
    <div class="col-md-8">
        {{ Form::checkbox('moniq_enabled', trans('setting::attributes.moniq_enabled'), trans('setting::settings.form.enable_moniq'), $errors, $settings) }}
        {{ Form::text('translatable[moniq_label]', trans('setting::attributes.translatable.moniq_label'), $errors, $settings, ['required' => true]) }}
        {{ Form::textarea('translatable[moniq_description]', trans('setting::attributes.translatable.moniq_description'), $errors, $settings, ['rows' => 3, 'required' => true]) }}

        <div class="{{ old('moniq_enabled', array_get($settings, 'moniq_enabled')) ? '' : 'hide' }}" id="moniq-fields">
            {{ Form::text('moniq_public_key', trans('setting::attributes.moniq_public_key'), $errors, $settings, ['required' => true]) }}
            {{ Form::password('moniq_api_secret', trans('setting::attributes.moniq_api_secret'), $errors, $settings, ['required' => true]) }}
        </div>

        <hr class="my-4">

        <h4 class="mb-3">{{ trans('setting::settings.form.komposa_chat_section') }}</h4>
        {{ Form::checkbox('moniq_enable_chat', trans('setting::attributes.moniq_enable_chat'), trans('setting::settings.form.enable_komposa_chat'), $errors, $settings) }}

        <p class="help-text text-muted mt-2">
            {{ trans('setting::settings.form.komposa_chat_help') }}
        </p>
    </div>
</div>
